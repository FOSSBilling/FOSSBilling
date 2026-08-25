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
use Doctrine\ORM\EntityManager;
use FOSSBilling\Doctrine\EntityManagerFactory;
use FOSSBilling\Doctrine\SchemaInstaller;
use FOSSBilling\Doctrine\SchemaSynchronizer;

/**
 * @return array{0: Connection, 1: EntityManager}
 */
function schemaSynchronizerFixture(): array
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    SchemaInstaller::createSchema($entityManager);

    return [$connection, $entityManager];
}

/**
 * invoice_item_pending_renewal_idx carries a MySQL-only column-length index prefix (see
 * InvoiceItem::class's #[ORM\Index] options) - required there because InnoDB can't index a full
 * TEXT column at all. SQLite ignores that option and applies the full, unprefixed 5-column index
 * (confirmed directly: it's strictly more capable than MySQL's, not narrower), but introspecting
 * it back never recovers the `lengths` option, so the comparator always sees it as "different
 * from metadata" even on a database with nothing actually out of sync. This is the one expected,
 * permanent, harmless skip (skipped items are never applied - see SchemaSynchronizer's docblock)
 * a truly-current SQLite database will always report.
 */
const EXPECTED_PERMANENT_SKIP = 'index `invoice_item_pending_renewal_idx` on `invoice_item` exists in the database but not in entity metadata';

test('sync is a no-op against a database already current with entity metadata', function (): void {
    [, $entityManager] = schemaSynchronizerFixture();

    $result = SchemaSynchronizer::sync($entityManager);

    expect($result['applied'])->toBe([])
        ->and($result['skipped'])->toBe([EXPECTED_PERMANENT_SKIP]);
});

test('sync recreates a table that entity metadata knows about but the database is missing', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('DROP TABLE custom_pages');
    expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeFalse();

    $result = SchemaSynchronizer::sync($entityManager);

    expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeTrue()
        ->and($result['applied'])->not->toBe([])
        ->and($result['skipped'])->toBe([EXPECTED_PERMANENT_SKIP]);
});

test('sync adds a column that entity metadata knows about but the database is missing, without touching existing rows', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement(
        'INSERT INTO currency (code, is_default, conversion_rate) VALUES (:code, 1, 1.0)',
        ['code' => 'USD'],
    );
    $connection->executeStatement('ALTER TABLE currency DROP COLUMN format_pattern');
    expect(in_array('format_pattern', array_map(
        static fn ($column) => $column->getName(),
        $connection->createSchemaManager()->listTableColumns('currency'),
    ), true))->toBeFalse();

    $result = SchemaSynchronizer::sync($entityManager);

    $columnNames = array_map(
        static fn ($column) => $column->getName(),
        $connection->createSchemaManager()->listTableColumns('currency'),
    );
    expect($columnNames)->toContain('format_pattern')
        ->and($result['applied'])->not->toBe([])
        ->and($connection->fetchOne("SELECT code FROM currency WHERE code = 'USD'"))->toBe('USD');
});

test('sync never drops a table that exists in the database but has no entity metadata', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('CREATE TABLE third_party_extension_table (id INTEGER PRIMARY KEY)');

    $result = SchemaSynchronizer::sync($entityManager);

    expect($connection->createSchemaManager()->tablesExist(['third_party_extension_table']))->toBeTrue()
        ->and($result['skipped'])->toContain('table `third_party_extension_table` exists in the database but not in entity metadata');
});

test('sync never drops a column that exists in the database but has no entity metadata', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('ALTER TABLE currency ADD COLUMN legacy_unmapped_column TEXT');

    $result = SchemaSynchronizer::sync($entityManager);

    $columnNames = array_map(
        static fn ($column) => $column->getName(),
        $connection->createSchemaManager()->listTableColumns('currency'),
    );
    expect($columnNames)->toContain('legacy_unmapped_column')
        ->and($result['skipped'])->toContain('column `currency`.`legacy_unmapped_column` exists in the database but not in entity metadata');
});

/*
 * Regression test for a real bug found wiring this into a live MySQL/MariaDB database: entity
 * metadata declares `cart.session_id_idx` as a UNIQUE index, but production schema (structure.sql)
 * has always had it as a plain, non-unique index of the same name - reused-name drift, not a
 * missing index. Applying just the "added" half of that (without dropping the old one first, which
 * this class never does) fails outright on MySQL ("Duplicate key name") and would silently change
 * uniqueness semantics on platforms that allow it. Reproduced here on SQLite by manually reverting
 * the index to non-unique after a normal createSchema().
 */
test('sync never applies an index whose name is reused with a different definition', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('DROP INDEX session_id_idx');
    $connection->executeStatement('CREATE INDEX session_id_idx ON cart (session_id)');
    $connection->executeStatement("INSERT INTO cart (session_id) VALUES ('dup'), ('dup')");

    $result = SchemaSynchronizer::sync($entityManager);

    $index = $connection->createSchemaManager()->introspectTable('cart')->getIndex('session_id_idx');
    expect($index->isUnique())->toBeFalse()
        ->and($result['skipped'])->toContain('index `session_id_idx` on `cart` exists in the database but not in entity metadata')
        ->and((int) $connection->fetchOne("SELECT COUNT(*) FROM cart WHERE session_id = 'dup'"))->toBe(2);
});

/*
 * Regression test for a second real bug found against a live MySQL/MariaDB database: entity
 * metadata declares real ManyToOne relations (e.g. CartProduct -> Cart), but structure.sql has
 * never had a single FOREIGN KEY clause anywhere - production referential integrity has always
 * been application-level only. Retrofitting a real FK constraint onto an *existing* table is
 * checked against every current row, which years of unconstrained data has no guarantee of
 * satisfying - so this must never happen automatically, even though it's technically additive.
 */
test('sync never adds a foreign key constraint to an existing table', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('DROP TABLE cart_product');
    $connection->executeStatement(
        'CREATE TABLE cart_product (id INTEGER PRIMARY KEY AUTOINCREMENT, cart_id BIGINT DEFAULT NULL, product_id BIGINT DEFAULT NULL, config CLOB DEFAULT NULL)',
    );

    $result = SchemaSynchronizer::sync($entityManager);

    $foreignKeys = $connection->createSchemaManager()->introspectTable('cart_product')->getForeignKeys();
    $skippedForeignKeyMessages = array_values(array_filter(
        $result['skipped'],
        static fn (string $line): bool => str_contains($line, 'on `cart_product` is defined in entity metadata but was left uncreated'),
    ));
    expect($foreignKeys)->toBe([])
        ->and($skippedForeignKeyMessages)->toHaveCount(1);
});
