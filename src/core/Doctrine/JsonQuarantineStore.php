<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

use Box\Mod\System\Entity\JsonQuarantine as JsonQuarantineRecord;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Core\Security\Crypt;

/**
 * Durable quarantine for JSON values the automatic migration nulled instead of
 * converting. Unlike migration-progress records, these survive finalization
 * until an administrator restores or dismisses them. Originals stay encrypted
 * at rest and are only decrypted for explicit single-row inspection.
 */
final readonly class JsonQuarantineStore
{
    public const string TABLE = 'json_quarantine';

    public function __construct(private Connection $connection, private Crypt $crypt)
    {
    }

    public static function sync(EntityManagerInterface $em): void
    {
        SchemaSynchronizer::syncEntities($em, [JsonQuarantineRecord::class]);
    }

    public function exists(): bool
    {
        return $this->connection->createSchemaManager()->tablesExist([self::TABLE]);
    }

    /**
     * @return list<array{table: string, column: string, id: string, issue: string, quarantined_at: string}>
     */
    public function list(?string $table = null, ?int $limit = null): array
    {
        if (!$this->exists()) {
            return [];
        }
        $sql = 'SELECT table_name, column_name, display_id, issue, created_at FROM ' . self::TABLE . ($table === null ? '' : ' WHERE table_name = ?') . ' ORDER BY id';
        $params = $table === null ? [] : [$table];
        $types = $table === null ? [] : [Types::STRING];
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }
        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);
        $entries = [];
        foreach ($rows as $row) {
            $created = $row['created_at'];
            if ($created instanceof \DateTimeInterface) {
                $created = $created->format('Y-m-d H:i:s');
            }
            $entries[] = [
                'table' => (string) $row['table_name'],
                'column' => (string) $row['column_name'],
                'id' => (string) $row['display_id'],
                'issue' => (string) $row['issue'],
                'quarantined_at' => (string) $created,
            ];
        }

        return $entries;
    }

    public function count(?string $table = null): int
    {
        if (!$this->exists()) {
            return 0;
        }
        $sql = 'SELECT COUNT(*) FROM ' . self::TABLE . ($table === null ? '' : ' WHERE table_name = ?');
        $params = $table === null ? [] : [$table];
        $types = $table === null ? [] : [Types::STRING];

        return (int) $this->connection->fetchOne($sql, $params, $types);
    }

    /** Store an encrypted original; false when an entry already exists. */
    public function save(string $table, string $column, int|string $rowId, bool $idBinary, string $displayId, string $issue, string $original): bool
    {
        if ($this->findRow($table, $column, (string) $rowId, $displayId) !== null) {
            return false;
        }
        $this->connection->insert(self::TABLE, [
            'table_name' => $table,
            'column_name' => $column,
            'row_id' => (string) $rowId,
            'id_binary' => $idBinary,
            'display_id' => $displayId,
            'issue' => $issue,
            'original' => $this->crypt->encrypt($original),
            'created_at' => new \DateTime(),
        ], [
            'table_name' => Types::STRING,
            'column_name' => Types::STRING,
            'row_id' => Types::BLOB,
            'id_binary' => Types::BOOLEAN,
            'display_id' => Types::STRING,
            'issue' => Types::STRING,
            'original' => Types::BLOB,
            'created_at' => Types::DATETIME_MUTABLE,
        ]);

        return true;
    }

    /**
     * @return array{entry: array{table: string, column: string, id: string, issue: string}, original: string, id_binary: bool, row_id: string}|null
     */
    public function find(string $table, string $column, string $identifier): ?array
    {
        $row = $this->findRow($table, $column, $identifier, $identifier);
        if ($row === null) {
            return null;
        }
        $stored = $row['original'];
        if (is_resource($stored)) {
            $stored = stream_get_contents($stored);
        }
        $plain = $this->crypt->decrypt(is_string($stored) ? $stored : null);
        if ($plain === false) {
            throw new JsonMigrationException('Quarantined value could not be decrypted; retain the original installation salt.');
        }
        $rawId = $row['row_id'];
        if (is_resource($rawId)) {
            $rawId = stream_get_contents($rawId);
        }

        return [
            'entry' => [
                'table' => (string) $row['table_name'],
                'column' => (string) $row['column_name'],
                'id' => (string) $row['display_id'],
                'issue' => (string) $row['issue'],
            ],
            'original' => $plain,
            'id_binary' => (bool) $row['id_binary'],
            'row_id' => (string) $rawId,
        ];
    }

    public function delete(string $table, string $column, string $identifier): int
    {
        if (!$this->exists()) {
            return 0;
        }

        return $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE . ' WHERE table_name = ? AND column_name = ? AND (row_id = ? OR display_id = ?)',
            [$table, $column, $identifier, $identifier],
            [Types::STRING, Types::STRING, Types::BLOB, Types::STRING]
        );
    }

    /**
     * Metadata-only lookup: identifies the record without decrypting the
     * original, so dismissal and validated restores work even when the
     * payload can no longer be decrypted.
     *
     * @return array{entry: array{table: string, column: string, id: string, issue: string}, id_binary: bool, row_id: string}|null
     */
    public function locate(string $table, string $column, string $identifier): ?array
    {
        $row = $this->findRow($table, $column, $identifier, $identifier);
        if ($row === null) {
            return null;
        }
        $rawId = $row['row_id'];
        if (is_resource($rawId)) {
            $rawId = stream_get_contents($rawId);
        }

        return [
            'entry' => [
                'table' => (string) $row['table_name'],
                'column' => (string) $row['column_name'],
                'id' => (string) $row['display_id'],
                'issue' => (string) $row['issue'],
            ],
            'id_binary' => (bool) $row['id_binary'],
            'row_id' => (string) $rawId,
        ];
    }

    /** @return array<string, mixed>|null */
    private function findRow(string $table, string $column, string $rowId, string $displayId): ?array
    {
        if (!$this->exists()) {
            return null;
        }
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE table_name = ? AND column_name = ? AND (row_id = ? OR display_id = ?) ORDER BY id LIMIT 1',
            [$table, $column, $rowId, $displayId],
            [Types::STRING, Types::STRING, Types::BLOB, Types::STRING]
        );

        return $row === false ? null : $row;
    }
}
