<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use FOSSBilling\Core\Doctrine\NamedLock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * SQLite has no session-scoped lock manager, so acquire()/release() fall back to flock() on a
 * file under PATH_DATA/locks.
 */
function sqliteLockFilePath(string $name): string
{
    return Path::join(PATH_DATA, 'locks', hash('sha256', $name) . '.lock');
}

beforeEach(function (): void {
    // The SQLite branch never queries the connection, it only uses getDatabasePlatform() to
    // select the flock() path, so a real in-memory connection is enough here.
    $this->sqliteConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
});

afterEach(function (): void {
    (new Filesystem())->remove(sqliteLockFilePath('fb-namedlock-test-lock'));
});

test('acquire and release use GET_LOCK/RELEASE_LOCK for MySQL and MariaDB', function (string $platformClass): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock($platformClass));
    $connection->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:name, :timeout)', ['name' => 'my-lock', 'timeout' => 7])
        ->andReturn(1);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('SELECT RELEASE_LOCK(:name)', ['name' => 'my-lock']);

    expect(NamedLock::acquire($connection, 'my-lock', 7))->toBeTrue();
    NamedLock::release($connection, 'my-lock');
})->with([MySQLPlatform::class, MariaDBPlatform::class]);

test('acquire returns false when GET_LOCK does not return 1', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    $connection->shouldReceive('fetchOne')->once()->andReturn(0);

    expect(NamedLock::acquire($connection, 'my-lock'))->toBeFalse();
});

test('acquire and release use pg_try_advisory_lock/pg_advisory_unlock for PostgreSQL, keyed by a stable hash of the name', function (): void {
    $expectedKey = crc32('my-lock');

    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT pg_try_advisory_lock(:key)', ['key' => $expectedKey])
        ->andReturn(true);
    $connection->shouldReceive('executeStatement')
        ->once()
        ->with('SELECT pg_advisory_unlock(:key)', ['key' => $expectedKey]);

    expect(NamedLock::acquire($connection, 'my-lock'))->toBeTrue();
    NamedLock::release($connection, 'my-lock');
});

test('acquire polls pg_try_advisory_lock until it succeeds', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')
        ->times(3)
        ->andReturn(false, false, true);

    expect(NamedLock::acquire($connection, 'my-lock', 5))->toBeTrue();
});

test('acquire treats PostgreSQL\'s own "f"/"t" driver strings correctly, not as PHP truthy/falsy', function (): void {
    // PDO's pgsql driver returns a boolean column as the literal string 't'/'f' by default, not a
    // native PHP bool - a bare (bool) cast on the raw fetchOne() result would treat *both* as
    // truthy (any non-empty PHP string is truthy), reporting the very first "busy" poll as a
    // successful lock. Mirrors the "polls until it succeeds" test above, but with the actual
    // driver-format values instead of native PHP booleans - Mockery's times(2) here fails if the
    // 'f' poll were (incorrectly) treated as an immediate success.
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')
        ->times(2)
        ->andReturn('f', 't');

    expect(NamedLock::acquire($connection, 'my-lock', 5))->toBeTrue();
});

test('acquire gives up on PostgreSQL once the timeout elapses', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')->atLeast()->once()->andReturn(false);

    // timeoutSeconds: 0 - the deadline is already in the past after the first poll, so this
    // returns quickly instead of actually waiting out a real multi-second timeout.
    expect(NamedLock::acquire($connection, 'my-lock', 0))->toBeFalse();
});

test('acquire and release use flock() on a lock file under PATH_DATA for SQLite', function (): void {
    expect(NamedLock::acquire($this->sqliteConnection, 'fb-namedlock-test-lock'))->toBeTrue();

    $lockPath = sqliteLockFilePath('fb-namedlock-test-lock');
    expect((new Filesystem())->exists($lockPath))->toBeTrue();

    // A second, independent file handle on the same lock file must fail to acquire an exclusive
    // lock while the first is still held - this is exactly how a second OS process attempting the
    // same lock would behave, not just a same-process re-entrancy check.
    $contendingHandle = fopen($lockPath, 'c');

    try {
        expect(flock($contendingHandle, LOCK_EX | LOCK_NB))->toBeFalse();
    } finally {
        fclose($contendingHandle);
    }

    NamedLock::release($this->sqliteConnection, 'fb-namedlock-test-lock');

    // Once released, an independent handle can take the lock immediately.
    $afterReleaseHandle = fopen($lockPath, 'c');

    try {
        expect(flock($afterReleaseHandle, LOCK_EX | LOCK_NB))->toBeTrue();
        flock($afterReleaseHandle, LOCK_UN);
    } finally {
        fclose($afterReleaseHandle);
    }
});

test('acquire times out on SQLite when another holder keeps the lock file locked', function (): void {
    $lockPath = sqliteLockFilePath('fb-namedlock-test-lock');
    (new Filesystem())->mkdir(dirname($lockPath));
    $externalHolder = fopen($lockPath, 'c');
    flock($externalHolder, LOCK_EX);

    try {
        // timeoutSeconds: 0 still tries once (matching the PostgreSQL "gives up" test above), so
        // this returns quickly instead of waiting out a real multi-second timeout.
        expect(NamedLock::acquire($this->sqliteConnection, 'fb-namedlock-test-lock', 0))->toBeFalse();
    } finally {
        flock($externalHolder, LOCK_UN);
        fclose($externalHolder);
    }
});

test('release on SQLite is a no-op when the lock was never acquired', function (): void {
    // Must not error - Stripe/Hook always call release() in a finally block, including on the
    // path where acquire() itself failed or threw before this lock was ever taken.
    NamedLock::release($this->sqliteConnection, 'fb-namedlock-test-lock-never-acquired');

    expect(true)->toBeTrue();
});
