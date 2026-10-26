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

/** Crash-recovery state and encrypted originals, discarded after successful conversion. */
final readonly class JsonMigrationJournal
{
    public const string COMPLETE = 'complete:json-fields-v1';

    private Connection $connection;

    public function __construct(EntityManagerInterface $em, private Crypt $crypt)
    {
        $this->connection = $em->getConnection();
        SchemaSynchronizer::syncEntities($em, [JsonMigrationRecord::class]);
    }

    public function has(string $key): bool
    {
        return self::hasRecord($this->connection, $key);
    }

    public static function hasRecord(Connection $connection, string $key): bool
    {
        return $connection->fetchOne('SELECT id FROM json_migration_record WHERE record_key = ?', [hash('sha256', $key)], [Types::BINARY]) !== false;
    }

    public function complete(): void
    {
        $this->connection->transactional(function (): void {
            $this->connection->executeStatement('DELETE FROM json_migration_record');
            $this->save(self::COMPLETE, ['complete' => true]);
        });
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
            throw new JsonMigrationException('Recovery record could not be decrypted; retain the original installation salt.');
        }

        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
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
