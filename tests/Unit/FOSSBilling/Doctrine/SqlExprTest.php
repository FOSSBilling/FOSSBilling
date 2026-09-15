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
use FOSSBilling\Doctrine\SqlExpr;

function connectionMockedToPlatform(string $platformClass): Connection
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock($platformClass));

    return $connection;
}

test('greatestOfTwo uses GREATEST on MySQL, MariaDB, and PostgreSQL', function (string $platformClass): void {
    $connection = connectionMockedToPlatform($platformClass);

    expect(SqlExpr::greatestOfTwo($connection, 'a', 'b'))->toBe('GREATEST(a, b)');
})->with([MySQLPlatform::class, MariaDBPlatform::class, PostgreSQLPlatform::class]);

test('addDays uses DATE_ADD on MySQL and MariaDB', function (string $platformClass): void {
    $connection = connectionMockedToPlatform($platformClass);

    expect(SqlExpr::addDays($connection, 'expires_at', 'grace_days'))
        ->toBe('DATE_ADD(expires_at, INTERVAL (grace_days) DAY)');
})->with([MySQLPlatform::class, MariaDBPlatform::class]);

test('addDays uses make_interval on PostgreSQL', function (): void {
    $connection = connectionMockedToPlatform(PostgreSQLPlatform::class);

    expect(SqlExpr::addDays($connection, 'expires_at', 'grace_days'))
        ->toBe('(expires_at + make_interval(days => (grace_days)::int))');
});

test('addHours uses DATE_ADD on MySQL and MariaDB', function (string $platformClass): void {
    $connection = connectionMockedToPlatform($platformClass);

    expect(SqlExpr::addHours($connection, 'updated_at', 'close_after'))
        ->toBe('DATE_ADD(updated_at, INTERVAL (close_after) HOUR)');
})->with([MySQLPlatform::class, MariaDBPlatform::class]);

test('addHours uses make_interval on PostgreSQL', function (): void {
    $connection = connectionMockedToPlatform(PostgreSQLPlatform::class);

    expect(SqlExpr::addHours($connection, 'updated_at', 'close_after'))
        ->toBe('(updated_at + make_interval(hours => (close_after)::int))');
});

test('addHours produces real, working SQLite syntax, including NULL propagation', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    $expr = SqlExpr::addHours($connection, "'2026-01-01 00:00:00'", '5');
    expect($expr)->toBe("datetime('2026-01-01 00:00:00', '+' || (5) || ' hours')")
        ->and($connection->fetchOne("SELECT {$expr}"))->toBe('2026-01-01 05:00:00');

    // A NULL close_after (e.g. an unmatched LEFT JOIN) must propagate to NULL - never a fatal
    // error and never a false match - matching MySQL's DATE_ADD(..., INTERVAL NULL HOUR).
    $nullExpr = SqlExpr::addHours($connection, "'2026-01-01 00:00:00'", 'NULL');
    expect($connection->fetchOne("SELECT {$nullExpr}"))->toBeNull();
});

test('dateOnly uses DATE() on MySQL and MariaDB', function (string $platformClass): void {
    $connection = connectionMockedToPlatform($platformClass);

    expect(SqlExpr::dateOnly($connection, 'created_at'))->toBe('DATE(created_at)');
})->with([MySQLPlatform::class, MariaDBPlatform::class]);

test('dateOnly uses a ::date cast on PostgreSQL', function (): void {
    $connection = connectionMockedToPlatform(PostgreSQLPlatform::class);

    expect(SqlExpr::dateOnly($connection, 'created_at'))->toBe('(created_at)::date');
});

test('dateOnly produces real, working SQLite syntax', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    $expr = SqlExpr::dateOnly($connection, "'2026-01-31 10:22:33'");
    expect($expr)->toBe("date('2026-01-31 10:22:33')")
        ->and($connection->fetchOne("SELECT {$expr}"))->toBe('2026-01-31');
});

test('dateDiffDays produces real, working SQLite syntax', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    $expr = SqlExpr::dateDiffDays($connection, "'2026-01-05 23:59:59'", "'2026-01-01 00:00:01'");
    expect((int) $connection->fetchOne("SELECT {$expr}"))->toBe(4);
});

test('greatestOfTwo and addDays produce real, working SQLite syntax', function (): void {
    // A real connection, not a mock: this is the regression test for portability - MySQL's
    // GREATEST()/DATE_ADD() would raise a syntax error on SQLite, so a plain pass here already
    // proves the composed expression is valid syntax there. Also pins the actual arithmetic.
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    $greatest = SqlExpr::greatestOfTwo($connection, '3', '7');
    expect($greatest)->toBe('MAX(3, 7)')
        ->and($connection->fetchOne("SELECT {$greatest}"))->toBe(7);

    $addDays = SqlExpr::addDays($connection, "'2026-01-31 10:00:00'", $greatest);
    expect($addDays)->toBe("datetime('2026-01-31 10:00:00', '+' || (MAX(3, 7)) || ' days')")
        ->and($connection->fetchOne("SELECT {$addDays}"))->toBe('2026-02-07 10:00:00');
});
