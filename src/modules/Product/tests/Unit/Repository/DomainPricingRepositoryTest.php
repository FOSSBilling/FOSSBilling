<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Repository\DomainPricingRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

function domainPricingRepositoryCreateConnection(): Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE tld_registrar (id INTEGER PRIMARY KEY, name TEXT)');
    $connection->executeStatement('CREATE TABLE tld (id INTEGER PRIMARY KEY, tld TEXT, price_registration TEXT, price_renew TEXT, price_transfer TEXT, active INTEGER, allow_register INTEGER, allow_transfer INTEGER, min_years INTEGER, tld_registrar_id INTEGER)');

    return $connection;
}

test('getActivePricingByTld tolerates a tld table with no periods column yet', function (): void {
    // Regression test for FOSSBILLING-PK4: on an install whose "tld" table
    // hasn't picked up the "periods" column yet (added by #4115's schema
    // patch), `SELECT t.*` simply won't return that key at all. Reading it
    // must not trigger an "Undefined array key" warning.
    $connection = domainPricingRepositoryCreateConnection();
    $connection->executeStatement(
        'INSERT INTO tld (tld, price_registration, price_renew, price_transfer, active, allow_register, allow_transfer, min_years, tld_registrar_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['.com', '10.00', '10.00', '10.00', 1, 1, 1, 1, null]
    );

    $pricing = (new DomainPricingRepository($connection))->getActivePricingByTld();

    expect($pricing)->toHaveKey('.com');
    expect($pricing['.com']['periods'])->toBeNull();
});

test('getActivePricingByTld decodes a comma separated periods column', function (): void {
    $connection = domainPricingRepositoryCreateConnection();
    $connection->executeStatement('ALTER TABLE tld ADD COLUMN periods TEXT');
    $connection->executeStatement(
        'INSERT INTO tld (tld, price_registration, price_renew, price_transfer, active, allow_register, allow_transfer, min_years, tld_registrar_id, periods) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        ['.net', '12.00', '12.00', '12.00', 1, 1, 1, 1, null, '1,2,5']
    );

    $pricing = (new DomainPricingRepository($connection))->getActivePricingByTld();

    expect($pricing['.net']['periods'])->toBe([1, 2, 5]);
});
