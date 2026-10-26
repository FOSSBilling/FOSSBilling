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

/** Portable JSON conversion; automatic upgrades run under the updater lock. */
final class JsonMigration
{
    private array $nativeColumns = [];
    private ?bool $formHasType = null;
    private array $idTypes = [];

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

        try {
            return $this->convert($connection, fn (array $record) => $this->write($backup, $record), function () use ($backup): void {
                if (!fflush($backup) || !fsync($backup)) {
                    throw new JsonMigrationException('Cannot durably flush the JSON backup. No source data was changed.');
                }
            });
        } finally {
            fclose($backup);
        }
    }

    public function automatic(\Doctrine\ORM\EntityManagerInterface $em, \FOSSBilling\Crypt $crypt): array
    {
        if ($em->getConnection()->isTransactionActive()) {
            throw new JsonMigrationException('JSON migration cannot run inside an existing transaction.');
        }

        try {
            $journal = new JsonMigrationJournal($em, $crypt);
            $journal->rotate();
            $key = 'complete:' . hash('sha256', json_encode(JsonFields::FIELDS, JSON_THROW_ON_ERROR));
            if ($journal->has($key)) {
                return ['columns' => 0, 'empty_values' => 0];
            }
            $result = $this->convert($em->getConnection(), static function (array $record): void {}, static function (): void {}, $journal);
            $this->assertReady($em->getConnection());
            $journal->save($key, ['complete' => true]);

            return $result;
        } catch (\Doctrine\DBAL\Exception) {
            throw new JsonMigrationException('Automatic JSON conversion did not complete. Source data and migration progress were retained. Check database availability and constraints before retrying update finalization.');
        }
    }

    private function convert(Connection $connection, \Closure $write, \Closure $flush, ?JsonMigrationJournal $journal = null): array
    {
        $platform = $connection->getDatabasePlatform();
        $schema = $connection->createSchemaManager();
        $targets = [];
        $temp = 'fb_json_preflight_' . bin2hex(random_bytes(6));
        $quotedTemp = $connection->quoteSingleIdentifier($temp);

        try {
            $write(['format' => 'fossbilling-json-backup-v1', 'database' => $connection->getDatabase(), 'platform' => $platform::class]);
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
                    // cspell:ignore Notnull
                    if ($field->getNotnull() || $field->getDefault() !== null || $field->getColumnDefinition() !== null) {
                        throw new JsonMigrationException("Review customized definition for $table.$column before migration.");
                    }
                    foreach ($old->getIndexes() as $index) {
                        if (in_array($column, array_map(static fn ($indexed): string => $indexed->getColumnName()->getIdentifier()->getValue(), $index->getIndexedColumns()), true)) {
                            throw new JsonMigrationException("Review customized index for $table.$column before migration.");
                        }
                    }
                    if ($journal?->has('column:' . $table . '.' . $column)) {
                        continue;
                    }
                    $targets[] = [$table, $column];
                    $write(['table' => $table, 'column' => $column, 'definition' => $field->toArray(true)]);
                    if ($journal?->has('scanned:' . $table . '.' . $column)) {
                        continue;
                    }
                    $checkpoint = $journal?->find('scan:' . $table . '.' . $column);
                    $after = isset($checkpoint['cursor']) ? base64_decode($checkpoint['cursor'], true) : null;
                    $afterType = !empty($checkpoint['binary']) ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING;
                    $batch = [];
                    $bytes = 0;
                    $rowsRead = 0;
                    foreach ($this->rows($connection, $table, $column, $after, $afterType) as $row) {
                        ++$rowsRead;
                        $raw = $row['value'];
                        $write(['table' => $table, 'column' => $column, 'id' => $row['id'], 'base64' => $raw === null ? null : base64_encode($raw)]);
                        if ($raw !== null && trim($raw) !== '') {
                            try {
                                $prepared = $this->preparedValue($connection, $table, $column, $row, $journal);
                                JsonMigrationValidation::assertCompatible($prepared, $table === 'form_field' && $column === 'default_value', $table === 'form_field');
                                $batch[] = ['row' => $row, 'value' => $prepared];
                                $bytes += strlen($prepared);
                            } catch (\Throwable) {
                                throw new JsonMigrationException("JSON preflight failed for $table.$column id={$this->displayId($table, $row['id'])}; no source data was changed. Review this row with database:json:audit.");
                            }
                        }
                        if ($rowsRead >= 500 || $bytes >= 262144) {
                            $this->validateBatch($connection, $temp, $batch, $table, $column);
                            $journal?->checkpoint('scan:' . $table . '.' . $column, ['cursor' => base64_encode((string) $row['id']), 'binary' => $row['id_type'] === \Doctrine\DBAL\ParameterType::BINARY]);
                            $batch = [];
                            $bytes = 0;
                            $rowsRead = 0;
                        }
                    }
                    $this->validateBatch($connection, $temp, $batch, $table, $column);
                    $journal?->save('scanned:' . $table . '.' . $column, ['validated' => true]);
                }
            }
            $write(['complete' => true]);
            $flush();
        } finally {
            $connection->executeStatement('DROP TABLE IF EXISTS ' . $quotedTemp);
        }
        $empty = 0;
        foreach ($targets as [$table, $column]) {
            $native = in_array($schema->introspectTableByUnquotedName($table)->getColumn($column)->getTypeName(), [Types::JSON, Types::JSONB], true);
            if ($table === 'form_field' && $column === 'default_value' && !$native) {
                $journal ??= new JsonMigrationJournal(EntityManagerFactory::create($connection), new \FOSSBilling\Crypt());
                $originalDefinition = $schema->introspectTableByUnquotedName($table)->getColumn($column);
                $journal->save('schema:' . $table . '.' . $column, $originalDefinition->toArray(true));
                if ($platform instanceof PostgreSQLPlatform) {
                    $connection->executeStatement('ALTER TABLE form_field ALTER COLUMN default_value TYPE TEXT');
                } elseif ($platform instanceof AbstractMySQLPlatform && $originalDefinition->getTypeName() === Types::STRING) {
                    $old = $schema->introspectTableByUnquotedName($table);
                    $new = $old->edit()->modifyColumnByUnquotedName($column, static function (ColumnEditor $editor): void {
                        $editor->setTypeName(Types::TEXT)->setLength(null);
                    })->create();
                    $schema->alterTable($schema->createComparator()->compareTables($old, $new));
                }
                foreach ($this->rows($connection, $table, $column) as $row) {
                    if ($row['value'] === null || $journal->has('prepared:' . $table . '.' . $column . ':' . $row['id'])) {
                        continue;
                    }
                    $prepared = $this->preparedValue($connection, $table, $column, $row, $journal);
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
            foreach ($this->rows($connection, $table, $column) as $row) {
                if ($row['value'] !== null && trim($row['value']) === '') {
                    $connection->transactional(function () use ($connection, $journal, $table, $column, $row): void {
                        $journal?->save('original:' . $table . '.' . $column . ':' . $row['id'], ['table' => $table, 'column' => $column, 'id' => $row['id'], 'base64' => base64_encode($row['value'])]);
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
            $journal?->save('column:' . $table . '.' . $column, ['converted' => true]);
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
                foreach ($this->rows($connection, $table, $column) as $row) {
                    if ($row['value'] === null) {
                        continue;
                    }

                    try {
                        if (trim($row['value']) === '') {
                            throw new \JsonException('Empty JSON sentinel.');
                        }
                        JsonMigrationValidation::assertCompatible($row['value'], $table === 'form_field' && $column === 'default_value', $table === 'form_field');
                    } catch (\JsonException) {
                        throw new JsonMigrationException("Review $table.$column id={$this->displayId($table, $row['id'])} before retrying update finalization.");
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
        } catch (\Throwable) {
            $connection->executeStatement('DELETE FROM ' . $quoted);
            foreach ($batch as $entry) {
                try {
                    $connection->insert($temp, ['document' => $entry['value']]);
                    $connection->executeStatement('DELETE FROM ' . $quoted);
                } catch (\Throwable) {
                    throw new JsonMigrationException("Native JSON validation failed for $table.$column id={$this->displayId($table, $entry['row']['id'])}; source data was retained.");
                }
            }

            throw new JsonMigrationException('Native JSON batch validation failed; source data was retained. Check database limits and retry finalization.');
        }
    }

    private function preparedValue(Connection $connection, string $table, string $column, array $row, ?JsonMigrationJournal $journal): ?string
    {
        $raw = $row['value'];
        if ($table !== 'form_field' || $column !== 'default_value' || $raw === null) {
            return $raw;
        }
        if ($journal?->has('prepared:' . $table . '.' . $column . ':' . $row['id'])
            || ($this->nativeColumns[$table . '.' . $column] ??= in_array($connection->createSchemaManager()->introspectTableByUnquotedName($table)->getColumn($column)->getTypeName(), [Types::JSON, Types::JSONB], true))) {
            return $raw;
        }
        // Only checkbox defaults were historically encoded as collections. Text such as "false"
        // and JSON-looking textarea content must remain strings, not guessed scalar values.
        $type = $row['type'] ?? null;
        $decoded = $type === 'checkbox' ? json_decode($raw, true) : null;

        return json_encode(is_array($decoded) ? $decoded : $raw, JSON_THROW_ON_ERROR);
    }

    /** @return \Generator<int, array{id: int|string, value: ?string, type: ?string, id_type: \Doctrine\DBAL\ParameterType}> */
    private function rows(Connection $connection, string $table, string $column, int|string|null $after = null, \Doctrine\DBAL\ParameterType $afterType = \Doctrine\DBAL\ParameterType::STRING): \Generator
    {
        $cursor = $after;
        $cursorType = $afterType;
        $sqlite = $connection->getDatabasePlatform() instanceof SQLitePlatform;
        $type = $table === 'form_field' && $column === 'default_value' && ($this->formHasType ??= $connection->createSchemaManager()->introspectTableByUnquotedName('form_field')->hasColumn('type'));
        do {
            $rows = $connection->fetchAllAssociative('SELECT id, ' . $connection->quoteSingleIdentifier($column) . ' AS value' . ($type ? ', type' : '') . ($sqlite ? ', typeof(id) AS id_storage' : '') . ' FROM ' . $connection->quoteSingleIdentifier($table)
                . ($cursor === null ? '' : ' WHERE id > :cursor') . ' ORDER BY id LIMIT 500', $cursor === null ? [] : ['cursor' => $cursor], $cursor === null ? [] : ['cursor' => $cursorType]);
            foreach ($rows as $row) {
                $cursor = is_resource($row['id']) ? stream_get_contents($row['id']) : $row['id'];
                $cursorType = $sqlite ? (($row['id_storage'] ?? null) === 'blob' ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING) : $this->idType($connection, $table);
                $value = $row['value'];
                if (is_resource($value)) {
                    $value = stream_get_contents($value);
                }
                yield ['id' => $cursor, 'value' => $value, 'type' => $row['type'] ?? null, 'id_type' => $cursorType];
            }
        } while (count($rows) === 500);
    }

    private function idType(Connection $connection, string $table): \Doctrine\DBAL\ParameterType
    {
        return $this->idTypes[$table] ??= ($connection->getDatabasePlatform() instanceof PostgreSQLPlatform
            && in_array($connection->createSchemaManager()->introspectTableByUnquotedName($table)->getColumn('id')->getTypeName(), [Types::BINARY, Types::BLOB], true)
                ? \Doctrine\DBAL\ParameterType::BINARY : \Doctrine\DBAL\ParameterType::STRING);
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
