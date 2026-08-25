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

use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Keeps a PostgreSQL/SQLite database's *structure* in sync with current Doctrine entity metadata,
 * for installs that were created after {@see SchemaInstaller} started generating their schema from
 * that same metadata.
 *
 * This exists because {@see \FOSSBilling\UpdatePatcher}'s ~80 historical patches are raw MySQL/
 * MariaDB DDL (and, in several cases, one-time data transformations tied to a specific historical
 * release) with no portable equivalent - see `UpdatePatcher::isMysqlDriver()`. Porting each of them
 * is out of scope here. What this class covers instead: every *future* structural change lands on
 * entity metadata first, the same source fresh installs already build from, so diffing live schema
 * against current metadata keeps an existing PostgreSQL/SQLite install from drifting out of date as
 * new releases add columns/tables/indexes - without replaying any historical raw SQL, and without
 * needing a hand-written patch for each change the way MySQL/MariaDB still does.
 *
 * Deliberately additive only. The diff Doctrine's Comparator produces can also contain drops
 * (a column/table/index no longer in metadata) - those are never applied, because a table with no
 * entity mapping isn't necessarily unused (e.g. a third-party extension module that predates this
 * mechanism, or manages its own schema), and a live install is exactly the wrong place to guess
 * wrong about that. Dropped structure is logged so an operator can see what didn't change and
 * decide by hand.
 *
 * One narrower exception to "additive is always safe": a foreign key constraint being added to an
 * *existing* table is never applied, even though it's technically additive. Unlike a new column or
 * index, MySQL/PostgreSQL both validate a new FK constraint against every existing row - and
 * FOSSBilling's schema has never had real FK constraints (`structure.sql` has none at all; it's
 * always been app-level referential integrity), so there's no guarantee years of production data
 * satisfy one. A brand-new table's own FK constraints are unaffected by this - those apply at
 * creation time, against zero rows, same as any fresh install.
 *
 * What this does NOT do: reproduce data migrations (patches that rewrite existing rows, split or
 * merge tables, or move data between columns). An existing PostgreSQL/SQLite install upgrading
 * through a release that includes one of those gets the new columns/tables this class can add, but
 * not the data transformation itself.
 */
final class SchemaSynchronizer
{
    /**
     * Applies any additive schema changes and returns what happened. Never drops or alters
     * existing tables, columns, indexes, or foreign keys - only creates what's missing.
     *
     * @return array{applied: list<string>, skipped: list<string>}
     */
    public static function sync(EntityManagerInterface $entityManager): array
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return ['applied' => [], 'skipped' => []];
        }

        $connection = $entityManager->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $platform = $connection->getDatabasePlatform();

        $toSchema = (new SchemaTool($entityManager))->getSchemaFromMetadata($metadata);
        $fromSchema = $schemaManager->introspectSchema();

        $comparator = class_exists(ComparatorConfig::class)
            ? $schemaManager->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false))
            : $schemaManager->createComparator();

        $diff = $comparator->compareSchemas($fromSchema, $toSchema);

        [$safeDiff, $skipped] = self::splitAdditiveChanges($diff);

        $applied = $platform->getAlterSchemaSQL($safeDiff);
        foreach ($applied as $statement) {
            $connection->executeStatement($statement);
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    /**
     * Splits a schema diff into an additive-only diff safe to execute, plus a human-readable list
     * of the drops/changes that were left untouched.
     *
     * @return array{0: SchemaDiff, 1: list<string>}
     */
    private static function splitAdditiveChanges(SchemaDiff $diff): array
    {
        $skipped = [];

        foreach ($diff->getDroppedTables() as $table) {
            $skipped[] = sprintf('table `%s` exists in the database but not in entity metadata', $table->getObjectName()->toString());
        }

        foreach ($diff->getDroppedSequences() as $sequence) {
            $skipped[] = sprintf('sequence `%s` exists in the database but not in entity metadata', $sequence->getObjectName()->toString());
        }

        $safeAlteredTables = [];
        foreach ($diff->getAlteredTables() as $tableDiff) {
            $tableName = $tableDiff->getOldTable()->getObjectName()->toString();

            $droppedColumnNames = array_map(
                static fn ($column): string => $column->getObjectName()->toString(),
                $tableDiff->getDroppedColumns(),
            );
            $droppedIndexNames = array_map(
                static fn ($index): string => $index->getObjectName()->toString(),
                $tableDiff->getDroppedIndexes(),
            );

            foreach ($tableDiff->getDroppedColumns() as $column) {
                $skipped[] = sprintf('column `%s`.`%s` exists in the database but not in entity metadata', $tableName, $column->getObjectName()->toString());
            }

            foreach ($tableDiff->getChangedColumns() as $columnDiff) {
                $skipped[] = sprintf('column `%s`.`%s` differs from entity metadata (type/length/default)', $tableName, $columnDiff->getOldColumn()->getObjectName()->toString());
            }

            foreach ($tableDiff->getDroppedIndexes() as $index) {
                $skipped[] = sprintf('index `%s` on `%s` exists in the database but not in entity metadata', $index->getObjectName()->toString(), $tableName);
            }

            foreach ($tableDiff->getDroppedForeignKeyConstraintNames() as $foreignKeyName) {
                $skipped[] = sprintf('foreign key `%s` on `%s` exists in the database but not in entity metadata', $foreignKeyName->toString(), $tableName);
            }

            // Foreign keys being ADDED to an already-existing table are never applied here, name
            // collision or not. Unlike a new column or index, a new FK constraint is checked against
            // every existing row: years-old data with no constraint enforcing it (structure.sql has
            // never had a single FOREIGN KEY clause - the whole schema has always been app-level
            // referential integrity only) can easily contain orphaned references, and even where it
            // doesn't, retrofitting a constraint changes DELETE/UPDATE behavior the application was
            // never built expecting. A brand-new table's own FK constraints (via createdTables,
            // below) stay untouched - those are the safe case, same as any fresh install.
            foreach ($tableDiff->getAddedForeignKeys() as $foreignKey) {
                $name = $foreignKey->getObjectName()?->toString() ?? '(unnamed)';
                $skipped[] = sprintf('foreign key `%s` on `%s` is defined in entity metadata but was left uncreated (would apply against existing rows)', $name, $tableName);
            }

            // An "added" column/index whose name collides with one just logged as dropped isn't a
            // pure addition - it's Doctrine's Comparator representing a *modification* (same name,
            // different definition: e.g. a plain index promoted to unique) as a drop+add pair.
            // Applying only the add half either fails outright (duplicate key name) or silently
            // replaces existing structure with different semantics - neither of which belongs in an
            // additive-only sync. Skip those too, logging once via the drop message above.
            $safeAddedColumns = array_values(array_filter(
                $tableDiff->getAddedColumns(),
                static fn ($column): bool => !in_array($column->getObjectName()->toString(), $droppedColumnNames, true),
            ));
            $safeAddedIndexes = array_values(array_filter(
                $tableDiff->getAddedIndexes(),
                static fn ($index): bool => !in_array($index->getObjectName()->toString(), $droppedIndexNames, true),
            ));

            $safeTableDiff = new TableDiff(
                oldTable: $tableDiff->getOldTable(),
                addedColumns: $safeAddedColumns,
                addedIndexes: $safeAddedIndexes,
            );

            if (!$safeTableDiff->isEmpty()) {
                $safeAlteredTables[] = $safeTableDiff;
            }
        }

        $safeDiff = new SchemaDiff(
            createdSchemas: $diff->getCreatedSchemas(),
            droppedSchemas: [],
            createdTables: $diff->getCreatedTables(),
            alteredTables: $safeAlteredTables,
            droppedTables: [],
            createdSequences: $diff->getCreatedSequences(),
            alteredSequences: [],
            droppedSequences: [],
        );

        return [$safeDiff, $skipped];
    }
}
