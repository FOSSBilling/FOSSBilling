<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Payment;

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Doctrine\PaymentFenceMiddleware;

/** Request-local ownership, captured at claim time; never inferred from refreshed entities. */
final class ProcessingFence
{
    /** @var list<array{table: string, id: int|string, token: string, column: string, pending?: bool}> */
    private static array $claims = [];
    private static bool $invalidated = false;

    public static function claims(): array
    {
        return self::$claims;
    }

    public static function invalidate(): void
    {
        self::$invalidated = true;
    }

    public static function invalidated(): bool
    {
        return self::$invalidated;
    }

    public static function transaction(Connection $db, int $id, string $token, callable $work): mixed
    {
        return self::run($db, ['table' => 'transaction', 'id' => $id, 'column' => 'processing_token', 'token' => $token], $work);
    }

    public static function charge(Connection $db, string $id, string $token, callable $work): mixed
    {
        return self::run($db, ['table' => 'invoice_gateway_charge_attempt', 'id' => $id, 'column' => 'owner_token', 'token' => $token, 'pending' => true], $work);
    }

    private static function run(Connection $db, array $claim, callable $work): mixed
    {
        $installed = false;
        foreach ($db->getConfiguration()->getMiddlewares() as $middleware) {
            $installed = $installed || $middleware instanceof PaymentFenceMiddleware;
        }
        if (!$installed) {
            throw new \LogicException('Payment processing requires the fenced application database connection.');
        }
        $level = $db->getTransactionNestingLevel();
        if ($level !== 0) {
            throw new \LogicException('Payment processing must begin outside a database transaction.');
        }
        if (self::$claims === []) {
            self::$invalidated = false;
        }
        self::$claims[] = $claim;

        try {
            // Also fence adapters/listeners whose first action is external I/O.
            // This cannot revoke a request already sent to a remote provider.
            $db->fetchOne('SELECT 1');
            $result = $work();
            if (self::$invalidated) {
                throw new OwnershipLost('A displaced payment worker cannot resume after suppressing an ownership error.');
            }
            if ($db->getTransactionNestingLevel() !== $level) {
                throw new \LogicException('The payment worker left an unbalanced database transaction.');
            }

            return $result;
        } finally {
            try {
                while ($db->getTransactionNestingLevel() > $level) {
                    $db->rollBack();
                }
            } finally {
                array_pop(self::$claims);
                if (self::$claims === []) {
                    self::$invalidated = false;
                }
            }
        }
    }
}
