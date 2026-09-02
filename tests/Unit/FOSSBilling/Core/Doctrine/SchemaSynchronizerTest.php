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
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Doctrine\SchemaInstaller;
use FOSSBilling\Core\Doctrine\SchemaSynchronizer;

/**
 * @return array{0: Connection, 1: EntityManager}
 */
function schemaSynchronizerFixture(): array
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    SchemaInstaller::createSchema($entityManager);

    // SchemaInstaller deliberately leaves non-core, non-default-active extensions' tables
    // uncreated (see ModuleEntityScope) - these tests exercise sync()/syncEntities() against a
    // database current with *all* entity metadata, the same state a real install reaches once
    // every extension it has is activated, so materialize those tables the same way each
    // module's own install() hook would.
    SchemaSynchronizer::syncEntities($entityManager, [
        Box\Mod\Custompages\Entity\CustomPage::class,
        Box\Mod\Massmailer\Entity\MassmailerMessage::class,
        Box\Mod\Serviceapikey\Entity\ServiceApiKey::class,
    ]);

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
/*
 * Regression test for a third real bug: several entities were renamed from a short,
 * per-table-scoped index name (matching legacy structure.sql, which an existing pre-cutover
 * MySQL install upgrading through this change still has on disk) to a table-prefixed one, to
 * satisfy SQLite/PostgreSQL's database-wide index-name uniqueness requirement (invoice_item's
 * `invoice_id_idx` -> `invoice_item_invoice_id_idx` is one of seven such renames). Doctrine's own
 * comparator represents an unambiguous same-columns rename as a rename, not an add+drop pair
 * (TableDiff::getRenamedIndexes(), separate from getAddedIndexes()/getDroppedIndexes()) -
 * splitAdditiveChanges() has to explicitly carry that through, or the new name is silently never
 * created at all, on every sync, forever.
 */
test('sync applies a pure index rename', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    // Simulate the legacy short index name a pre-cutover MySQL install still has.
    $connection->executeStatement('DROP INDEX invoice_item_invoice_id_idx');
    $connection->executeStatement('CREATE INDEX invoice_id_idx ON invoice_item (invoice_id)');

    $result = SchemaSynchronizer::sync($entityManager);

    $indexNames = array_keys($connection->createSchemaManager()->introspectTable('invoice_item')->getIndexes());
    expect($indexNames)->toContain('invoice_item_invoice_id_idx')
        ->and($indexNames)->not->toContain('invoice_id_idx')
        ->and($result['skipped'])->toBe([EXPECTED_PERMANENT_SKIP]);
});

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

test('syncEntities creates only the given entity\'s table, reporting no unrelated tables as missing', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = EntityManagerFactory::create($connection);

    expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeFalse();

    $result = SchemaSynchronizer::syncEntities($entityManager, [Box\Mod\Custompages\Entity\CustomPage::class]);

    expect($connection->createSchemaManager()->tablesExist(['custom_pages']))->toBeTrue()
        // Every other entity's table is absent from this brand-new database too, but none of
        // them were ever compared - scoping to just CustomPage means nothing outside it is
        // reported as "missing from metadata", unlike a full sync() against an empty database.
        ->and($result['skipped'])->toBe([]);
});

test('syncEntities is a no-op when the entity\'s table is already current', function (): void {
    [, $entityManager] = schemaSynchronizerFixture();

    $result = SchemaSynchronizer::syncEntities($entityManager, [Box\Mod\Custompages\Entity\CustomPage::class]);

    expect($result['applied'])->toBe([])
        ->and($result['skipped'])->toBe([]);
});

test('syncEntities never drops a column the target entity no longer maps, but leaves unrelated tables untouched', function (): void {
    [$connection, $entityManager] = schemaSynchronizerFixture();

    $connection->executeStatement('ALTER TABLE custom_pages ADD COLUMN legacy_unmapped_column TEXT');
    // A completely unrelated table is missing a column too - syncEntities() must never see it,
    // let alone report or fix it, since only CustomPage was asked for.
    $connection->executeStatement('ALTER TABLE post DROP COLUMN description');

    $result = SchemaSynchronizer::syncEntities($entityManager, [Box\Mod\Custompages\Entity\CustomPage::class]);

    $columnNames = array_map(
        static fn ($column) => $column->getName(),
        $connection->createSchemaManager()->listTableColumns('custom_pages'),
    );
    // introspectTableByUnquotedName() (what introspectOnly() uses) quotes the column name
    // differently than a full introspectSchema() would in the resulting skip message - cosmetic
    // only, the column itself is identical and just as untouched either way - so this asserts
    // on substrings rather than the exact string sync()'s equivalent test asserts on.
    expect($columnNames)->toContain('legacy_unmapped_column')
        ->and($result['skipped'])->toHaveCount(1)
        ->and($result['skipped'][0])->toContain('custom_pages')
        ->and($result['skipped'][0])->toContain('legacy_unmapped_column')
        ->and($result['skipped'][0])->toContain('exists in the database but not in entity metadata');
});

/*
 * Regression test for a real bug found wiring syncEntities() into UpdatePatcher::
 * applyCorePatches() against a live MariaDB database: Transaction::class maps to the literal
 * table name "`transaction`" (backtick-quoted in the name string itself, to escape the SQL
 * keyword) - Name::toString() renders that back out *quoted*, and passing that quoted string to
 * tablesExist()/introspectTableByUnquotedName() (which both want the raw unquoted name) made an
 * already-existing `transaction` table always look missing from introspection, so the comparator
 * tried to CREATE TABLE `transaction` again and failed outright with "table already exists".
 */
test('syncEntities is a no-op for an entity whose table name is itself quoted (a reserved SQL word)', function (): void {
    [, $entityManager] = schemaSynchronizerFixture();

    $result = SchemaSynchronizer::syncEntities($entityManager, [Box\Mod\Invoice\Entity\Transaction::class]);

    expect($result['applied'])->toBe([])
        ->and($result['skipped'])->toBe([]);
});
