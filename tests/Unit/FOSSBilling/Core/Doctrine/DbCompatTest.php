<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\DbCompat;

test('tableExists and tableHasColumn work on sqlite', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, role TEXT)');

    expect(DbCompat::tableExists($connection, 'admin'))->toBeTrue()
        ->and(DbCompat::tableExists($connection, 'missing'))->toBeFalse()
        ->and(DbCompat::tableHasColumn($connection, 'admin', 'role'))->toBeTrue()
        ->and(DbCompat::tableHasColumn($connection, 'admin', 'ROLE'))->toBeTrue()
        ->and(DbCompat::tableHasColumn($connection, 'admin', 'nope'))->toBeFalse()
        ->and(DbCompat::tableHasColumn($connection, 'missing', 'role'))->toBeFalse();
});
