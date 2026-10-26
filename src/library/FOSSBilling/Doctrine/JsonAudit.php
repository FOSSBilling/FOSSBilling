<?php

declare(strict_types=1);

namespace FOSSBilling\Doctrine;

use Doctrine\DBAL\Connection;

/** Read-only, bounded inspection; reports identifiers and issue classes, never JSON payloads. */
final class JsonAudit
{
    /** @return \Generator<int, array{table: string, column: string, id: int, issue: string}> */
    public function inspect(Connection $connection, ?string $onlyTable = null, int $batchSize = 500): \Generator
    {
        if ($batchSize < 1 || $batchSize > 10000) {
            throw new \InvalidArgumentException('Batch size must be between 1 and 10000.');
        }
        if ($onlyTable !== null && !isset(JsonFields::FIELDS[$onlyTable])) {
            throw new \InvalidArgumentException('Unknown JSON migration table.');
        }
        $schema = $connection->createSchemaManager();
        foreach (JsonFields::FIELDS as $table => $definition) {
            if (($onlyTable !== null && $table !== $onlyTable) || !$schema->tablesExist([$table])) {
                continue;
            }
            $existing = $schema->listTableColumns($table);
            $columns = array_keys(array_intersect_key($definition['columns'], $existing));
            if ($columns === []) {
                continue;
            }
            $selection = implode(', ', array_map($connection->quoteIdentifier(...), ['id', ...$columns]));
            $cursor = null;
            do {
                $rows = $connection->fetchAllAssociative(
                    'SELECT ' . $selection . ' FROM ' . $connection->quoteIdentifier($table)
                    . ($cursor === null ? '' : ' WHERE id > :cursor') . ' ORDER BY id ASC LIMIT ' . $batchSize,
                    $cursor === null ? [] : ['cursor' => $cursor],
                );
                foreach ($rows as $row) {
                    $cursor = (int) $row['id'];
                    foreach ($columns as $column) {
                        $raw = $row[$column];
                        if (is_resource($raw)) {
                            $raw = stream_get_contents($raw);
                        }
                        $issue = $this->issue($raw);
                        if ($issue !== null) {
                            yield ['table' => $table, 'column' => $column, 'id' => $cursor, 'issue' => $issue];
                        }
                    }
                }
            } while (count($rows) === $batchSize);
        }
    }

    private function issue(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (!is_string($raw)) {
            return 'unexpected_storage_type';
        }
        if (trim($raw) === '') {
            return 'empty_string';
        }

        try {
            $value = JsonDocument::fromJson($raw)->value();
        } catch (\JsonException) {
            return 'invalid_json';
        }
        if ($value === null || is_array($value) || $value instanceof \stdClass) {
            return null;
        }
        if (is_string($value)) {
            try {
                $nested = JsonDocument::fromJson($value)->value();
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
