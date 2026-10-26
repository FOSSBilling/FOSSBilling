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
use Symfony\Component\Filesystem\Path;

/** Offline, explicit migration. Callers must stop every writer before invoking this class. */
final class JsonMigration
{
    /**
     * Back up and preflight the ENTIRE inventory before changing any source row or column.
     * DDL can commit implicitly on MySQL: this is deliberately resumable, not advertised as atomic.
     *
     * @return array{columns: int, empty_values: int}
     */
    public function migrate(Connection $connection, string $backupFile): array
    {
        $platform = $connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform && !$platform instanceof PostgreSQLPlatform && !$platform instanceof SQLitePlatform) {
            throw new JsonMigrationException('Unsupported JSON migration platform.');
        }
        if ($connection->isTransactionActive()) {
            throw new JsonMigrationException('Run the JSON migration outside an existing transaction.');
        }
        $parent = realpath(dirname($backupFile));
        if (!Path::isAbsolute($backupFile) || $parent === false || (defined('PATH_ROOT') && Path::isBasePath(realpath(PATH_ROOT), $parent))) {
            throw new JsonMigrationException('Choose an existing private backup directory outside the application root.');
        }
        // Exclusive creation prevents accidental replacement of the only pre-migration backup.
        $mask = umask(0o077);

        try {
            $backup = fopen($backupFile, 'xb');
        } finally {
            umask($mask);
        }
        if ($backup === false) {
            throw new JsonMigrationException('Cannot create the private JSON backup. Choose a new file.');
        }
        $schema = $connection->createSchemaManager();
        $targets = [];
        $temp = 'fb_json_preflight_' . bin2hex(random_bytes(6));
        $quotedTemp = $connection->quoteSingleIdentifier($temp);

        try {
            $this->write($backup, ['format' => 'fossbilling-json-backup-v1', 'database' => $connection->getDatabase(), 'platform' => $platform::class]);
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
                    if ($field->getNotnull() || $field->getDefault() !== null || $field->getColumnDefinition() !== null) {
                        throw new JsonMigrationException("Review customized definition for $table.$column before migration.");
                    }
                    foreach ($old->getIndexes() as $index) {
                        if (in_array($column, array_map(static fn ($indexed): string => $indexed->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()), true)) {
                            throw new JsonMigrationException("Review customized index for $table.$column before migration.");
                        }
                    }
                    $targets[] = [$table, $column];
                    $this->write($backup, ['table' => $table, 'column' => $column, 'definition' => $field->toArray(true)]);
                    foreach ($this->rows($connection, $table, $column) as $row) {
                        $raw = $row['value'];
                        $this->write($backup, ['table' => $table, 'column' => $column, 'id' => $row['id'], 'base64' => $raw === null ? null : base64_encode($raw)]);
                        if ($raw === null || trim($raw) === '') {
                            continue;
                        }

                        try {
                            JsonMigrationValidation::assertCompatible($raw);
                            $connection->insert($temp, ['document' => $raw]);
                            $connection->executeStatement('DELETE FROM ' . $quotedTemp);
                        } catch (\Throwable) {
                            // Do not include DB exception text: it can contain secrets from the document.
                            throw new JsonMigrationException("JSON preflight failed for $table.$column id={$this->displayId($table, $row['id'])}; no source data was changed. Review this row with database:json:audit.");
                        }
                    }
                }
            }
            $this->write($backup, ['complete' => true]);
            if (!fflush($backup) || !fsync($backup)) {
                throw new JsonMigrationException('Cannot durably flush the JSON backup. No source data was changed.');
            }
        } finally {
            fclose($backup);
            $connection->executeStatement('DROP TABLE IF EXISTS ' . $quotedTemp);
        }
        $empty = 0;
        foreach ($targets as [$table, $column]) {
            // Normalize only the empty sentinel; ambiguous documents were rejected during preflight.
            foreach ($this->rows($connection, $table, $column) as $row) {
                if ($row['value'] !== null && trim($row['value']) === '') {
                    $connection->update($connection->quoteSingleIdentifier($table), [$connection->quoteSingleIdentifier($column) => null], ['id' => $row['id']]);
                    ++$empty;
                }
            }
            $quotedTable = $connection->quoteSingleIdentifier($table);
            $quotedColumn = $connection->quoteSingleIdentifier($column);
            if (in_array($schema->introspectTableByUnquotedName($table)->getColumn($column)->getTypeName(), [Types::JSON, Types::JSONB], true)) {
                continue;
            }
            if ($platform instanceof PostgreSQLPlatform) {
                // PostgreSQL needs an explicit USING cast when the old column is TEXT.
                $connection->executeStatement("ALTER TABLE $quotedTable ALTER COLUMN $quotedColumn TYPE JSON USING $quotedColumn::json");
            } elseif ($platform instanceof AbstractMySQLPlatform) {
                $old = $schema->introspectTableByUnquotedName($table);
                $new = $old->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $editor): void {
                    $editor->setTypeName(Types::JSON)->setCharset(null)->setCollation(null)->setLength(null);
                })->create();
                $schema->alterTable($schema->createComparator()->compareTables($old, $new));
            }
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
                    throw new JsonMigrationException("Run database:json:migrate offline before completing this update ($table.$column is not JSON).");
                }
                foreach ($this->rows($connection, $table, $column) as $row) {
                    if ($row['value'] === null) {
                        continue;
                    }

                    try {
                        if (trim($row['value']) === '') {
                            throw new \JsonException('Empty JSON sentinel.');
                        }
                        JsonMigrationValidation::assertCompatible($row['value']);
                    } catch (\JsonException) {
                        throw new JsonMigrationException("Review $table.$column id={$this->displayId($table, $row['id'])} and run database:json:migrate offline before completing this update.");
                    }
                }
            }
        }
    }

    /** @return \Generator<int, array{id: int|string, value: ?string}> */
    private function rows(Connection $connection, string $table, string $column): \Generator
    {
        $cursor = null;
        do {
            $rows = $connection->fetchAllAssociative('SELECT id, ' . $connection->quoteSingleIdentifier($column) . ' AS value FROM ' . $connection->quoteSingleIdentifier($table)
                . ($cursor === null ? '' : ' WHERE id > :cursor') . ' ORDER BY id LIMIT 500', $cursor === null ? [] : ['cursor' => $cursor]);
            foreach ($rows as $row) {
                $cursor = $row['id'];
                $value = $row['value'];
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
                yield ['id' => $cursor, 'value' => $value];
            }
        } while (count($rows) === 500);
    }

    private function displayId(string $table, int|string $id): string
    {
        return $table === 'session' ? 'sha256:' . hash('sha256', (string) $id) : (string) $id;
    }

    /** @param resource $stream */
    private function write(mixed $stream, array $record): void
    {
        $line = json_encode($record, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";
        while ($line !== '') {
            $written = fwrite($stream, $line);
            if ($written === false || $written === 0) {
                throw new JsonMigrationException('Cannot write the JSON backup. No source data was changed.');
            }
            $line = substr($line, $written);
        }
    }
}
