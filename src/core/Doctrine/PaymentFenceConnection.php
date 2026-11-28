<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use FOSSBilling\Core\Payment\OwnershipLost;
use FOSSBilling\Core\Payment\ProcessingFence;

/**
 * Check ownership under the claim's row lock in the SAME transaction as the SQL.
 * Autocommit SQL gets a short transaction; an existing transaction retains the
 * lock until its commit/rollback. Network requests do not hold a database lock.
 */
final class PaymentFenceConnection extends AbstractConnectionMiddleware
{
    private bool $inTransaction = false;
    private bool $broken = false;

    public function __construct(Connection $connection, private readonly string $driver)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        $statement = parent::prepare($sql);

        return new class($statement, $this, $sql) extends AbstractStatementMiddleware {
            public function __construct(Statement $statement, private readonly PaymentFenceConnection $connection, private readonly string $sql)
            {
                parent::__construct($statement);
            }

            public function execute(): Result
            {
                return $this->connection->fenced($this->sql, fn (): Result => parent::execute());
            }
        };
    }

    public function query(string $sql): Result
    {
        return $this->fenced($sql, fn (): Result => parent::query($sql));
    }

    public function exec(string $sql): int|string
    {
        return $this->fenced($sql, fn (): int|string => parent::exec($sql));
    }

    public function beginTransaction(): void
    {
        $this->startTransaction();
        $this->inTransaction = true;
    }

    private function startTransaction(): void
    {
        if ($this->driver === 'pdo_mysql' && ProcessingFence::claims() !== []) {
            // Row locks provide payment serialization. A fixed snapshot can
            // hide a receipt committed by another callback; MariaDB's snapshot
            // isolation can even reject the locking reread with ER_CHECKREAD.
            // SET TRANSACTION affects this transaction only, not the session.
            parent::exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }
        parent::beginTransaction();
    }

    public function commit(): void
    {
        try {
            parent::commit();
            $this->inTransaction = false;
        } catch (\Throwable $exception) {
            $this->broken = true;

            throw $exception;
        }
    }

    public function rollBack(): void
    {
        try {
            parent::rollBack();
        } catch (\Throwable $exception) {
            $this->broken = true;

            throw $exception;
        } finally {
            $this->inTransaction = false;
        }
    }

    public function getNativeConnection()
    {
        if (ProcessingFence::claims() !== []) {
            throw new \LogicException('Native database access bypasses payment fencing. Use the application DBAL connection.');
        }

        return parent::getNativeConnection();
    }

    public function fenced(string $sql, callable $execute): mixed
    {
        if ($this->broken) {
            throw new OwnershipLost('The payment database connection has an uncertain transaction outcome and must be replaced.');
        }
        $claims = ProcessingFence::claims();
        if ($claims === []) {
            return $execute();
        }
        // Only single statements are supported inside a payment scope. Data
        // belongs in bound parameters; an embedded COMMIT must never escape
        // the transaction that holds the ownership lock.
        $command = preg_replace('~\A(?:\s+|/\*.*?\*/|--[^\n]*(?:\n|$))*~s', '', $sql);
        $single = preg_replace('/;\s*$/', '', trim($command));
        if (str_contains($single, ';') || str_contains($sql, '/*!')) {
            throw new \LogicException('Multiple SQL statements are not permitted during payment processing.');
        }
        // These exact session/transaction controls cannot write financial
        // state. PostgreSQL isolation must precede our first locking read.
        $controls = [
            '/\ASELECT\s+(?:RELEASE_LOCK|pg_advisory_unlock)\s*\(\s*(?:\?|:[a-z_][a-z_0-9]*)\s*\)\s*\z/i',
            '/\A(?:SAVEPOINT|RELEASE\s+SAVEPOINT|ROLLBACK\s+TO\s+SAVEPOINT)\s+DOCTRINE_[0-9]+\s*\z/i',
            '/\ASET(?:\s+SESSION)?\s+(?:interactive_timeout|wait_timeout)\s*=\s*[0-9]+\s*\z/i',
            "/\ASET\s+time_zone\s*=\s*'[+-][0-9]{2}:[0-9]{2}'\s*\z/i",
            '/\ASET\s+TRANSACTION\s+ISOLATION\s+LEVEL\s+(?:READ COMMITTED|REPEATABLE READ|SERIALIZABLE)\s*\z/i',
            '/\ASET\s+SESSION\s+CHARACTERISTICS\s+AS\s+TRANSACTION\s+ISOLATION\s+LEVEL\s+(?:READ COMMITTED|REPEATABLE READ|SERIALIZABLE)\s*\z/i',
            '/\APRAGMA\s+busy_timeout\s*=\s*[0-9]+\s*\z/i',
        ];
        foreach ($controls as $control) {
            if (preg_match($control, $single)) {
                return $execute();
            }
        }
        // Permit only ordinary data statements and read-only inspection. A
        // blacklist misses vendor controls such as LOCK TABLES / XA COMMIT
        // that can implicitly commit and release the ownership lock.
        if (!preg_match('/\A(?:SELECT|INSERT|UPDATE|DELETE|REPLACE|WITH|SHOW|DESCRIBE|EXPLAIN|VALUES)\b/i', $single)) {
            throw new \LogicException('DDL and explicit transaction SQL are not permitted during payment processing.');
        }
        if (ProcessingFence::invalidated()) {
            throw new OwnershipLost('Payment processing ownership was revoked. This worker must stop.');
        }
        $ownTransaction = !$this->inTransaction;
        if ($ownTransaction) {
            $this->startTransaction();
        }

        try {
            // Deterministic order for nested charge/transaction processing.
            usort($claims, static fn (array $a, array $b): int => [$a['table'], $a['id']] <=> [$b['table'], $b['id']]);
            foreach ($claims as $claim) {
                $this->lockClaim($claim);
            }
            $result = $execute();
            if ($ownTransaction) {
                try {
                    parent::commit();
                } catch (\Throwable $exception) {
                    $this->broken = true;

                    throw $exception;
                }
            }

            return $result;
        } catch (\Throwable $exception) {
            if ($ownTransaction) {
                try {
                    parent::rollBack();
                } catch (\Throwable) {
                    $this->broken = true;
                }
            }

            throw $exception;
        }
    }

    private function lockClaim(array $claim): void
    {
        // All identifiers come exclusively from ProcessingFence, never input SQL.
        $quote = $this->driver === 'pdo_mysql' ? '`' : '"';
        $table = $quote . $claim['table'] . $quote;
        $column = $quote . $claim['column'] . $quote;
        if ($this->driver === 'pdo_sqlite') {
            $lock = parent::prepare("UPDATE {$table} SET {$column} = {$column} WHERE id = ?");
            $lock->bindValue(1, $claim['id'], is_int($claim['id']) ? ParameterType::INTEGER : ParameterType::STRING);
            $lock->execute();
        }
        $pending = ($claim['pending'] ?? false) ? " AND state = 'pending'" : '';
        $statement = parent::prepare("SELECT {$column} FROM {$table} WHERE id = ?{$pending}" . ($this->driver === 'pdo_sqlite' ? '' : ' FOR UPDATE'));
        $statement->bindValue(1, $claim['id'], is_int($claim['id']) ? ParameterType::INTEGER : ParameterType::STRING);
        $value = $statement->execute()->fetchOne();
        if (!is_string($value) || !hash_equals($claim['token'], $value)) {
            ProcessingFence::invalidate();

            throw new OwnershipLost('Payment processing ownership was revoked. This worker must stop.');
        }
    }
}
