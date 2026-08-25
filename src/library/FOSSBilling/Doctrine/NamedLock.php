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
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Portable named/advisory locking, for serializing a critical section across concurrent
 * processes (not just concurrent transactions on the same connection) - e.g. "only one process
 * may rebuild this cache at a time".
 *
 * MySQL/MariaDB have session-scoped named locks (`GET_LOCK`/`RELEASE_LOCK`). PostgreSQL has
 * session-scoped advisory locks keyed by integer rather than string, so the name is folded to an
 * integer key; `pg_try_advisory_lock()` is polled rather than using the blocking
 * `pg_advisory_lock()`, since Postgres has no built-in wait-timeout for it.
 *
 * SQLite has no session-scoped lock manager, but {@see RowLock} shows that most call sites don't
 * actually need one: SQLite already takes a database-wide write lock for the duration of a write
 * transaction, so wrapping the whole guarded section in a single transaction is enough to
 * serialize it against other writers there. That reasoning breaks down for a critical section
 * spanning more than one transaction - e.g. Stripe::withStripeObjectLock(), which guards several
 * separate flushes plus an external API call - so on SQLite this falls back to a real
 * cross-process lock: `flock()` on a zero-byte file under PATH_DATA, one per lock name, polled
 * with `LOCK_EX|LOCK_NB` the same way the PostgreSQL path above polls `pg_try_advisory_lock()`
 * (plain blocking `LOCK_EX` has no way to time out).
 *
 * Lock files are deliberately never deleted. flock() locks the open file descriptor, not the
 * path, so removing the file out from under a lock a process still holds - or that another
 * process is about to open - would let a second process create and lock a fresh file at the same
 * path while the first still believes it holds the original, defeating mutual exclusion. Leaving
 * the (empty) files in place avoids that race entirely; a SQLite install's lock names are bounded
 * by the number of distinct Stripe objects and batch operations it ever processes, so the
 * footprint stays small - the files hold no data, only OS-level lock state, same as a MySQL named
 * lock or a Postgres advisory lock holds none in the database itself. This also relies on the
 * database file living on local storage rather than an NFS/network filesystem, but that is not a
 * new constraint: SQLite's own documentation already warns that its locking is unreliable there,
 * so a SQLite-backed install must avoid network filesystems regardless of this class.
 */
final class NamedLock
{
    private const int POLL_INTERVAL_MICROSECONDS = 100_000;

    private const string SQLITE_LOCK_SUBDIRECTORY = 'locks';

    /** @var array<string, resource> open file handles for locks currently held on SQLite, keyed by lock name */
    private static array $sqliteLockHandles = [];

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
            $platform instanceof SQLitePlatform => self::acquireSqliteFileLock($name, $timeoutSeconds),
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
            $platform instanceof SQLitePlatform => self::releaseSqliteFileLock($name),
            default => null,
        };
    }

    private static function acquirePostgresAdvisoryLock(Connection $connection, int $key, int $timeoutSeconds): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            // Not a bare (bool) cast: PDO's pgsql driver returns a boolean column as the literal
            // string 't'/'f' rather than a native PHP bool (Connection::fetchOne() on a raw SQL
            // string does no Doctrine type-mapping to normalize that) - and (bool) 'f' is true,
            // since any non-empty PHP string is truthy. That would report every failed/busy
            // acquisition attempt as successful on the very first poll.
            $result = $connection->fetchOne('SELECT pg_try_advisory_lock(:key)', ['key' => $key]);
            if (in_array($result, [true, 1, '1', 't'], true)) {
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

    private static function acquireSqliteFileLock(string $name, int $timeoutSeconds): bool
    {
        $handle = fopen(Path::join(self::sqliteLockDirectory(), self::sqliteLockFilename($name)), 'c');
        if ($handle === false) {
            return false;
        }

        $deadline = microtime(true) + $timeoutSeconds;

        do {
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                self::$sqliteLockHandles[$name] = $handle;

                return true;
            }
            usleep(self::POLL_INTERVAL_MICROSECONDS);
        } while (microtime(true) < $deadline);

        fclose($handle);

        return false;
    }

    private static function releaseSqliteFileLock(string $name): void
    {
        $handle = self::$sqliteLockHandles[$name] ?? null;
        if ($handle === null) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
        unset(self::$sqliteLockHandles[$name]);
    }

    private static function sqliteLockDirectory(): string
    {
        $directory = Path::join(\PATH_DATA, self::SQLITE_LOCK_SUBDIRECTORY);
        (new Filesystem())->mkdir($directory, 0o755);

        return $directory;
    }

    /**
     * Lock names aren't guaranteed to be filesystem-safe (Stripe's happen to be hex, but this is a
     * general-purpose primitive), so the name is hashed into a fixed-width, path-safe filename
     * rather than sanitized.
     */
    private static function sqliteLockFilename(string $name): string
    {
        return hash('sha256', $name) . '.lock';
    }
}
