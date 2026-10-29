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
use FOSSBilling\Core\Doctrine\RowLock;

function connectionWithPlatform(Doctrine\DBAL\Platforms\AbstractPlatform $platform): Connection
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn($platform);

    return $connection;
}

test('suffix appends FOR UPDATE for platforms that support row locking', function (string $platformClass): void {
    $connection = connectionWithPlatform(Mockery::mock($platformClass));

    expect(RowLock::suffix($connection))->toBe(' FOR UPDATE');
})->with([
    MySQLPlatform::class,
    MariaDBPlatform::class,
    PostgreSQLPlatform::class,
]);

test('suffix is empty for SQLite, which has no FOR UPDATE clause', function (): void {
    $connection = connectionWithPlatform(Mockery::mock(SQLitePlatform::class));

    expect(RowLock::suffix($connection))->toBe('');
});
