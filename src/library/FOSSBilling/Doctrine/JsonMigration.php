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
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Types\Types;

/** Portable JSON conversion; automatic upgrades run under the updater lock. */
final class JsonMigration
{
    /** Fresh schema already uses JSON, including SQLite's indistinguishable TEXT fallback. */
    public static function markInstalled(\Doctrine\ORM\EntityManagerInterface $em, \FOSSBilling\Crypt $crypt): void
    {
        (new JsonMigrationJournal($em, $crypt))->complete();
    }

    public function automatic(\Doctrine\ORM\EntityManagerInterface $em, \FOSSBilling\Crypt $crypt): array
    {
        if ($em->getConnection()->isTransactionActive()) {
            throw new JsonMigrationException('JSON migration cannot run inside an existing transaction.');
        }

        try {
            $journal = new JsonMigrationJournal($em, $crypt);
            if ($journal->has(JsonMigrationJournal::COMPLETE)) {
                return ['columns' => 0, 'empty_values' => 0];
            }
            $result = $this->convert($em->getConnection(), $journal);
            $this->assertReady($em->getConnection());
            $journal->complete();

            return $result;
        } catch (\Doctrine\DBAL\Exception) {
            throw new JsonMigrationException('Automatic JSON conversion did not complete. Source data and migration progress were retained. Check database availability and constraints before retrying update finalization.');
        }
    }

    private function convert(Connection $connection, JsonMigrationJournal $journal): array
    {
        $platform = $connection->getDatabasePlatform();
        $schema = $connection->createSchemaManager();
        $targets = [];
        $temp = 'fb_json_preflight_' . bin2hex(random_bytes(6));
        $quotedTemp = $connection->quoteSingleIdentifier($temp);

        try {
            // The database itself validates every nonempty value using its native declaration.
            $connection->executeStatement('CREATE TEMPORARY TABLE ' . $quotedTemp . ' (document ' . $platform->getJsonTypeDeclarationSQL([]) . ')');
            foreach (JsonFields::FIELDS as $table => $definition) {
                if (!$schema->tablesExist([$table])) {
                    continue;
                }
                $old = $schema->introspectTableByUnquotedName($table);
                foreach (array_keys($definition['columns']) as $column) {
                    if (!$old->hasColumn($column)) {
                        continue;
                    }
                    $field = $old->getColumn($column);
                    $legacyDefault = $table === 'form_field' && $column === 'default_value' && !in_array($field->getTypeName(), [Types::JSON, Types::JSONB], true);
                    // cspell:ignore Notnull
                    if ($field->getNotnull() || $field->getDefault() !== null || $field->getColumnDefinition() !== null) {
                        throw new JsonMigrationException("Review customized definition for $table.$column before migration.");
                    }
                    foreach ($old->getIndexes() as $index) {
                        if (in_array($column, array_map(static fn ($indexed): string => $indexed->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()), true)) {
                            throw new JsonMigrationException("Review customized index for $table.$column before migration.");
                        }
                    }
                    if ($journal->has('column:' . $table . '.' . $column)) {
                        continue;
                    }
                    $targets[] = [$table, $column, $legacyDefault];
                    if ($journal->has('scanned:' . $table . '.' . $column)) {
                        continue;
                    }
                    $checkpoint = $journal->find('scan:' . $table . '.' . $column);
                    $after = isset($checkpoint['cursor']) ? base64_decode($checkpoint['cursor'], true) : null;
                    $afterType = !empty($checkpoint['binary']) ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING;
                    $batch = [];
                    $bytes = 0;
                    $rowsRead = 0;
                    foreach (JsonFields::rows($connection, $table, $column, after: $after, afterType: $afterType) as $row) {
                        ++$rowsRead;
                        $raw = $row['value'];
                        if ($raw !== null && trim($raw) !== '') {
                            try {
                                $prepared = JsonFields::prepareValue($row, $legacyDefault && !$journal->has('prepared:' . $table . '.' . $column . ':' . $row['id']));
                                JsonMigrationValidation::assertCompatible($prepared, $table === 'form_field' && $column === 'default_value');
                                $batch[] = ['row' => $row, 'value' => $prepared];
                                $bytes += strlen($prepared);
                            } catch (\JsonException) {
                                throw new JsonMigrationException("JSON preflight failed for $table.$column id=" . JsonFields::displayId($table, $row['id']) . '; no source data was changed. Review this row with database:json:audit.');
                            }
                        }
                        if ($rowsRead >= 500 || $bytes >= 262144) {
                            $this->validateBatch($connection, $temp, $batch, $table, $column);
                            $journal->checkpoint('scan:' . $table . '.' . $column, ['cursor' => base64_encode((string) $row['id']), 'binary' => $row['id_type'] === \Doctrine\DBAL\ParameterType::BINARY]);
                            $batch = [];
                            $bytes = 0;
                            $rowsRead = 0;
                        }
                    }
                    $this->validateBatch($connection, $temp, $batch, $table, $column);
                    $journal->save('scanned:' . $table . '.' . $column, ['validated' => true]);
                }
            }
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS ' . $quotedTemp);
        }
        $empty = 0;
        foreach ($targets as [$table, $column, $legacyDefault]) {
            if ($legacyDefault) {
                $originalDefinition = $schema->introspectTableByUnquotedName($table)->getColumn($column);
                if ($platform instanceof PostgreSQLPlatform) {
                    $connection->executeStatement('ALTER TABLE form_field ALTER COLUMN default_value TYPE TEXT');
                } elseif ($platform instanceof AbstractMySQLPlatform && $originalDefinition->getTypeName() === Types::STRING) {
                    $old = $schema->introspectTableByUnquotedName($table);
                    $new = $old->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $editor): void {
                        $editor->setTypeName(Types::TEXT)->setLength(null);
                    })->create();
                    $schema->alterTable($schema->createComparator()->compareTables($old, $new));
                }
                foreach (JsonFields::rows($connection, $table, $column) as $row) {
                    if ($row['value'] === null || $journal->has('prepared:' . $table . '.' . $column . ':' . $row['id'])) {
                        continue;
                    }
                    $prepared = JsonFields::prepareValue($row, true);
                    $connection->transactional(function () use ($connection, $journal, $table, $column, $row, $prepared): void {
                        $journal->save('prepared:' . $table . '.' . $column . ':' . $row['id'], ['table' => $table, 'column' => $column, 'id' => $row['id'], 'base64' => base64_encode($row['value'])]);
                        $changed = $connection->executeStatement('UPDATE form_field SET default_value = ? WHERE id = ? AND default_value = ?', [$prepared, $row['id'], $row['value']]);
                        if ($changed !== 1) {
                            throw new JsonMigrationException('Form default changed during migration; retry finalization.');
                        }
                    });
                }
            }
            // Normalize only the empty sentinel; ambiguous documents were rejected during preflight.
            foreach (JsonFields::rows($connection, $table, $column) as $row) {
                if ($row['value'] !== null && trim($row['value']) === '') {
                    $connection->transactional(function () use ($connection, $journal, $table, $column, $row): void {
                        $journal->save('original:' . $table . '.' . $column . ':' . $row['id'], ['table' => $table, 'column' => $column, 'id' => $row['id'], 'base64' => base64_encode($row['value'])]);
                        $connection->executeStatement('UPDATE ' . $connection->quoteSingleIdentifier($table) . ' SET ' . $connection->quoteSingleIdentifier($column) . ' = NULL WHERE id = ? AND ' . $connection->quoteSingleIdentifier($column) . ' = ?', [$row['id'], $row['value']], [$row['id_type'], \Doctrine\DBAL\ParameterType::STRING]);
                    });
                    ++$empty;
                }
            }
            $quotedTable = $connection->quoteSingleIdentifier($table);
            $quotedColumn = $connection->quoteSingleIdentifier($column);
            $native = in_array($schema->introspectTableByUnquotedName($table)->getColumn($column)->getTypeName(), [Types::JSON, Types::JSONB], true);
            if (!$native && $platform instanceof PostgreSQLPlatform) {
                // PostgreSQL needs an explicit USING cast when the old column is TEXT.
                $connection->executeStatement("ALTER TABLE $quotedTable ALTER COLUMN $quotedColumn TYPE JSON USING $quotedColumn::json");
            } elseif (!$native && $platform instanceof AbstractMySQLPlatform) {
                $old = $schema->introspectTableByUnquotedName($table);
                $new = $old->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $editor): void {
                    $editor->setTypeName(Types::JSON)->setCharset(null)->setCollation(null)->setLength(null);
                })->create();
                $schema->alterTable($schema->createComparator()->compareTables($old, $new));
            }
            $journal->save('column:' . $table . '.' . $column, ['converted' => true]);
            // SQLite's standard Doctrine JSON declaration is already CLOB/TEXT; no table rebuild.
        }

        return ['columns' => count($targets), 'empty_values' => $empty];
    }

    /** Prevent update completion from reopening an installation with unconverted JSON fields. */
    public function assertReady(Connection $connection): void
    {
        $schema = $connection->createSchemaManager();
        foreach (JsonFields::FIELDS as $table => $definition) {
            if (!$schema->tablesExist([$table])) {
                continue;
            }
            $actual = $schema->introspectTableByUnquotedName($table);
            foreach (array_keys($definition['columns']) as $column) {
                if (!$actual->hasColumn($column)) {
                    continue;
                }
                if (!$connection->getDatabasePlatform() instanceof SQLitePlatform
                    && !in_array($actual->getColumn($column)->getTypeName(), [Types::JSON, Types::JSONB], true)) {
                    throw new JsonMigrationException("JSON conversion has not completed; retry update finalization ($table.$column is not JSON).");
                }
                foreach (JsonFields::rows($connection, $table, $column) as $row) {
                    if ($row['value'] === null) {
                        continue;
                    }

                    try {
                        if (trim($row['value']) === '') {
                            throw new \JsonException('Empty JSON sentinel.');
                        }
                        JsonMigrationValidation::assertCompatible($row['value'], $table === 'form_field' && $column === 'default_value');
                    } catch (\JsonException) {
                        throw new JsonMigrationException("Review $table.$column id=" . JsonFields::displayId($table, $row['id']) . ' before retrying update finalization.');
                    }
                }
            }
        }
    }

    /** Native validation is batched and byte-bounded; isolate a failing row only on errors. */
    private function validateBatch(Connection $connection, string $temp, array $batch, string $table, string $column): void
    {
        if ($batch === []) {
            return;
        }
        $quoted = $connection->quoteSingleIdentifier($temp);

        try {
            $connection->executeStatement('INSERT INTO ' . $quoted . ' (document) VALUES ' . implode(', ', array_fill(0, count($batch), '(?)')), array_column($batch, 'value'));
            $connection->executeStatement('DELETE FROM ' . $quoted);
        } catch (\Doctrine\DBAL\Exception) {
            $connection->executeStatement('DELETE FROM ' . $quoted);
            foreach ($batch as $entry) {
                try {
                    $connection->insert($temp, ['document' => $entry['value']]);
                    $connection->executeStatement('DELETE FROM ' . $quoted);
                } catch (\Doctrine\DBAL\Exception) {
                    throw new JsonMigrationException("Native JSON validation failed for $table.$column id=" . JsonFields::displayId($table, $entry['row']['id']) . '; source data was retained.');
                }
            }

            throw new JsonMigrationException('Native JSON batch validation failed; source data was retained. Check database limits and retry finalization.');
        }
    }
}
