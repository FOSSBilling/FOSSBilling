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
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use FOSSBilling\Doctrine\NamedLock;

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

test('acquire gives up on PostgreSQL once the timeout elapses', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')->atLeast()->once()->andReturn(false);

    // timeoutSeconds: 0 - the deadline is already in the past after the first poll, so this
    // returns quickly instead of actually waiting out a real multi-second timeout.
    expect(NamedLock::acquire($connection, 'my-lock', 0))->toBeFalse();
});

test('acquire and release are safe no-ops on SQLite', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(SQLitePlatform::class));
    $connection->shouldNotReceive('fetchOne');
    $connection->shouldNotReceive('executeStatement');

    expect(NamedLock::acquire($connection, 'my-lock'))->toBeTrue();
    NamedLock::release($connection, 'my-lock');
});
