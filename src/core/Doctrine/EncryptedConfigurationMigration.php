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

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Security\Crypt;

/** Upgrade existing global extension configuration to authenticated encryption. */
final class EncryptedConfigurationMigration
{
    /** @return list<string> Warnings for configurations that could not be migrated. */
    public function run(Connection $connection, Crypt $crypt, ?\Psr\Cache\CacheItemPoolInterface $cache = null): array
    {
        $schema = $connection->createSchemaManager();
        if (!$schema->tablesExist(['extension_meta'])) {
            return [];
        }
        $warnings = [];
        $cursor = null;
        do {
            $rows = $connection->fetchAllAssociative('SELECT id, meta_value, extension' . " FROM extension_meta WHERE meta_key = 'config' AND rel_type IS NULL AND rel_id IS NULL AND client_id IS NULL" . ($cursor === null ? '' : ' AND id > :cursor') . ' ORDER BY id LIMIT 100', $cursor === null ? [] : ['cursor' => $cursor]);
            foreach ($rows as $row) {
                $cursor = $row['id'];
                $stored = $row['meta_value'];
                if (isset($row['extension'])) {
                    $cache?->deleteItem('config_' . $row['extension']);
                }
                if ($stored === null || $stored === '') {
                    continue;
                }
                $plain = $crypt->decrypt($stored);
                if ($plain === false || !is_array(json_decode($plain, true))) {
                    if (isset($row['extension'])) {
                        $cache?->deleteItem('encrypted_config_' . $row['extension']);
                    }
                    $warnings[] = sprintf(
                        'Extension configuration id=%s (extension=%s) %s. The original value was retained.',
                        $cursor,
                        json_encode($row['extension'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
                        $plain === false ? 'cannot be decrypted' : 'is not a configuration document',
                    );

                    continue;
                }
                if (str_starts_with($stored, Crypt::CURRENT_FORMAT_PREFIX)) {
                    continue;
                }
                $changed = $connection->executeStatement('UPDATE extension_meta SET meta_value = ? WHERE id = ? AND meta_value = ?', [$crypt->encrypt($plain), $cursor, $stored]);
                if (isset($row['extension'])) {
                    $cache?->deleteItem('encrypted_config_' . $row['extension']);
                }
                if ($changed !== 1) {
                    throw new JsonMigrationException('Extension configuration changed during re-keying; retry update finalization.');
                }
            }
        } while (count($rows) === 100);

        return $warnings;
    }
}
