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
use FOSSBilling\Crypt;

/** Re-key only the encrypted, global extension-config scope; other metadata remains opaque. */
final class EncryptedConfigurationMigration
{
    public function run(Connection $connection, Crypt $crypt, ?\Psr\Cache\CacheItemPoolInterface $cache = null): void
    {
        $schema = $connection->createSchemaManager();
        if (!$schema->tablesExist(['extension_meta'])) {
            return;
        }
        $table = $schema->introspectTableByUnquotedName('extension_meta');
        foreach (['id', 'meta_key', 'meta_value', 'rel_type', 'rel_id', 'client_id'] as $column) {
            if (!$table->hasColumn($column)) {
                return;
            }
        }
        $cursor = null;
        do {
            $rows = $connection->fetchAllAssociative('SELECT id, meta_value' . ($table->hasColumn('extension') ? ', extension' : '') . " FROM extension_meta WHERE meta_key = 'config' AND rel_type IS NULL AND rel_id IS NULL AND client_id IS NULL" . ($cursor === null ? '' : ' AND id > :cursor') . ' ORDER BY id LIMIT 100', $cursor === null ? [] : ['cursor' => $cursor]);
            foreach ($rows as $row) {
                $cursor = $row['id'];
                $stored = $row['meta_value'];
                if (isset($row['extension'])) {
                    $cache?->deleteItem('config_' . $row['extension']);
                }
                if ($stored === null || $stored === '' || !$crypt->needsReEncryption($stored)) {
                    continue;
                }
                $plain = $crypt->decrypt($stored);
                if ($plain === false) {
                    throw new JsonMigrationException('Encrypted extension configuration id=' . $cursor . ' cannot be decrypted; existing data was retained.');
                }
                if (!json_validate($plain) || !is_array(json_decode($plain, true))) {
                    throw new JsonMigrationException('Encrypted extension configuration id=' . $cursor . ' is not a configuration document; existing data was retained.');
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
    }
}
