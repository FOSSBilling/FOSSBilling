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

/** Read-only, bounded inspection; reports identifiers and issue classes, never JSON payloads. */
final class JsonAudit
{
    /** @return \Generator<int, array{table: string, column: string, id: int|string, issue: string}> */
    public function inspect(Connection $connection, ?string $onlyTable = null, int $batchSize = 500): \Generator
    {
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new \InvalidArgumentException('Batch size must be between 1 and 10000.');
        }
        if ($onlyTable !== null && !isset(JsonFields::FIELDS[$onlyTable])) {
            throw new \InvalidArgumentException('Unknown JSON migration table.');
        }
        $schema = $connection->createSchemaManager();
        $hasJournal = $schema->tablesExist(['json_migration_record']);
        $complete = $hasJournal && JsonMigrationJournal::hasRecord($connection, JsonMigrationJournal::COMPLETE);
        foreach (JsonFields::FIELDS as $table => $definition) {
            if (($onlyTable !== null && $table !== $onlyTable) || !$schema->tablesExist([$table])) {
                continue;
            }
            $actual = $schema->introspectTableByUnquotedName($table);
            foreach (array_keys($definition['columns']) as $column) {
                if (!$actual->hasColumn($column)) {
                    continue;
                }
                $scalar = $table === 'form_field' && $column === 'default_value';
                $legacyDefault = $scalar && !$complete
                    && !in_array($actual->getColumn($column)->getTypeName(), [\Doctrine\DBAL\Types\Types::JSON, \Doctrine\DBAL\Types\Types::JSONB], true)
                    && !($hasJournal && JsonMigrationJournal::hasRecord($connection, 'column:' . $table . '.' . $column));
                foreach (JsonFields::rows($connection, $table, $column, $batchSize) as $row) {
                    $prepared = $legacyDefault && !($hasJournal && JsonMigrationJournal::hasRecord($connection, 'prepared:' . $table . '.' . $column . ':' . $row['id']));

                    try {
                        $raw = JsonFields::prepareValue($row, $prepared);
                        $issue = $this->issue($raw, $scalar);
                    } catch (\JsonException) {
                        $issue = 'invalid_json';
                    }
                    if ($issue !== null) {
                        yield ['table' => $table, 'column' => $column, 'id' => JsonFields::displayId($table, $row['id']), 'issue' => $issue];
                    }
                }
            }
        }
    }

    private function issue(?string $raw, bool $scalar = false): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (trim($raw) === '') {
            return 'empty_string';
        }

        try {
            $value = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return 'invalid_json';
        }
        if ($scalar || $value === null || is_array($value) || $value instanceof \stdClass) {
            try {
                JsonMigrationValidation::assertCompatible($raw, $scalar);
            } catch (\JsonException) {
                return 'unsafe_json';
            }

            return null;
        }
        if (is_string($value)) {
            try {
                $nested = json_decode($value, false, 512, JSON_THROW_ON_ERROR);
                if (is_array($nested) || $nested instanceof \stdClass) {
                    return 'double_encoded_json';
                }
            } catch (\JsonException) {
                // A plain scalar string is a different issue, not an implicit repair.
            }
        }

        return 'unexpected_root';
    }
}
