<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Portable named/advisory locking, for serializing a critical section across concurrent
 * processes (not just concurrent transactions on the same connection) - e.g. "only one process
 * may rebuild this cache at a time".
 *
 * MySQL/MariaDB have session-scoped named locks (`GET_LOCK`/`RELEASE_LOCK`). PostgreSQL has
 * session-scoped advisory locks keyed by integer rather than string, so the name is folded to an
 * integer key; `pg_try_advisory_lock()` is polled rather than using the blocking
 * `pg_advisory_lock()`, since Postgres has no built-in wait-timeout for it. SQLite has no
 * multi-process lock manager at all, but it doesn't need one for this use case either: SQLite
 * already takes a database-wide write lock for the duration of a write transaction, so a
 * SQLite-backed install cannot actually run the guarded section concurrently in the first place -
 * acquire()/release() are safe no-ops there.
 */
final class NamedLock
{
    private const POLL_INTERVAL_MICROSECONDS = 100_000;

    /**
     * Attempts to acquire the named lock, waiting up to $timeoutSeconds. Returns true once
     * acquired, or false if the timeout elapsed first.
     */
    public static function acquire(Connection $connection, string $name, int $timeoutSeconds = 10): bool
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => (int) $connection->fetchOne(
                'SELECT GET_LOCK(:name, :timeout)',
                ['name' => $name, 'timeout' => $timeoutSeconds],
            ) === 1,
            $platform instanceof PostgreSQLPlatform => self::acquirePostgresAdvisoryLock($connection, self::advisoryLockKey($name), $timeoutSeconds),
            default => true,
        };
    }

    /**
     * Releases a lock previously acquired with {@see self::acquire()}.
     */
    public static function release(Connection $connection, string $name): void
    {
        $platform = $connection->getDatabasePlatform();

        match (true) {
            $platform instanceof AbstractMySQLPlatform => $connection->executeStatement('SELECT RELEASE_LOCK(:name)', ['name' => $name]),
            $platform instanceof PostgreSQLPlatform => $connection->executeStatement('SELECT pg_advisory_unlock(:key)', ['key' => self::advisoryLockKey($name)]),
            default => null,
        };
    }

    private static function acquirePostgresAdvisoryLock(Connection $connection, int $key, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            if ((bool) $connection->fetchOne('SELECT pg_try_advisory_lock(:key)', ['key' => $key])) {
                return true;
            }
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        return false;
    }

    /**
     * PostgreSQL advisory locks are keyed by a bigint, not a string. crc32() gives a stable
     * unsigned 32-bit value, comfortably within bigint's range, so distinct lock names don't
     * collide with each other (short of a crc32 collision between two names actually in use).
     */
    private static function advisoryLockKey(string $name): int
    {
        return crc32($name);
    }
}
