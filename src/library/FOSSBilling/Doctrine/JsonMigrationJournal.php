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

use Box\Mod\System\Entity\JsonMigrationRecord;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Crypt;

/** Per-column checkpoints and encrypted originals for automatic transformations. */
final readonly class JsonMigrationJournal
{
    private Connection $connection;

    public function __construct(EntityManagerInterface $em, private Crypt $crypt)
    {
        $this->connection = $em->getConnection();
        SchemaSynchronizer::syncEntities($em, [JsonMigrationRecord::class]);
    }

    public function has(string $key): bool
    {
        return $this->connection->fetchOne('SELECT id FROM json_migration_record WHERE record_key = ?', [hash('sha256', $key)], [Types::BINARY]) !== false;
    }

    public function find(string $key): ?array
    {
        $payload = $this->connection->fetchOne('SELECT payload FROM json_migration_record WHERE record_key = ?', [hash('sha256', $key)], [Types::BINARY]);
        if ($payload === false) {
            return null;
        }
        if (is_resource($payload)) {
            $payload = stream_get_contents($payload);
        }
        $plain = $this->crypt->decrypt($payload);
        if ($plain === false) {
            throw new JsonMigrationException('Recovery record could not be decrypted; retain its encryption key.');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    public function rotate(): void
    {
        $id = $this->crypt->currentKeyId();
        if (($this->find('encryption-key')['id'] ?? null) === $id) {
            return;
        }
        $cursor = null;
        do {
            $rows = $this->connection->fetchAllAssociative('SELECT id, payload FROM json_migration_record' . ($cursor === null ? '' : ' WHERE id > ?') . ' ORDER BY id LIMIT 100', $cursor === null ? [] : [$cursor]);
            foreach ($rows as $row) {
                $cursor = $row['id'];
                $stored = is_resource($row['payload']) ? stream_get_contents($row['payload']) : $row['payload'];
                if (!$this->crypt->needsReEncryption($stored)) {
                    continue;
                }
                $plain = $this->crypt->decrypt($stored);
                if ($plain === false) {
                    throw new JsonMigrationException('Retain the previous encryption key to rotate recovery records.');
                }
                $this->connection->update('json_migration_record', ['payload' => $this->crypt->encrypt($plain)], ['id' => $cursor], ['payload' => Types::BLOB]);
            }
        } while (count($rows) === 100);
        if ($this->has('encryption-key')) {
            $this->connection->update('json_migration_record', ['payload' => $this->crypt->encrypt(json_encode(['id' => $id], JSON_THROW_ON_ERROR))], ['record_key' => hash('sha256', 'encryption-key')], ['payload' => Types::BLOB, 'record_key' => Types::BINARY]);
        } else {
            $this->save('encryption-key', ['id' => $id]);
        }
    }

    public function checkpoint(string $key, array $payload): void
    {
        if (!$this->has($key)) {
            $this->save($key, $payload);

            return;
        }
        $this->connection->update('json_migration_record', ['payload' => $this->crypt->encrypt(json_encode($payload, JSON_THROW_ON_ERROR))], ['record_key' => hash('sha256', $key)], ['payload' => Types::BLOB, 'record_key' => Types::BINARY]);
    }

    public function save(string $key, array $payload): void
    {
        if ($this->has($key)) {
            return;
        }
        $this->connection->insert('json_migration_record', [
            'record_key' => hash('sha256', $key),
            'payload' => $this->crypt->encrypt(json_encode($payload, JSON_THROW_ON_ERROR)),
        ], ['record_key' => Types::BINARY, 'payload' => Types::BLOB]);
    }
}
