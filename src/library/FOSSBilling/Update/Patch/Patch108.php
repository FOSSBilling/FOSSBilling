<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch108 implements PatchInterface
{
    public function getVersion(): int
    {
        return 108;
    }

    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableExists('service_downloadable_file')) {
            $patcher->executeSql(
                'CREATE TABLE `service_downloadable_file` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `service_downloadable_id` BIGINT NOT NULL,
                    `file_key` VARCHAR(32) NOT NULL,
                    `filename` VARCHAR(255) NOT NULL,
                    `stored_filename` VARCHAR(64) NOT NULL,
                    `label` VARCHAR(255) DEFAULT NULL,
                    `description` TEXT DEFAULT NULL,
                    `downloads` INT NOT NULL DEFAULT 0,
                    `sort_order` INT NOT NULL DEFAULT 0,
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `service_downloadable_file_key_idx` (`service_downloadable_id`, `file_key`),
                    KEY `service_downloadable_file_stored_filename_idx` (`stored_filename`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        $productFiles = [];
        $products = $patcher->fetchAll("SELECT id, config FROM product WHERE type = 'downloadable'");
        foreach ($products as $product) {
            $config = json_decode((string) $product['config'], true) ?: [];
            if (!isset($config['files']) && !empty($config['filename']) && !empty($config['stored_filename'])) {
                $config['files'] = [[
                    'id' => bin2hex(random_bytes(16)),
                    'filename' => $config['filename'],
                    'stored_filename' => $config['stored_filename'],
                    'label' => null,
                    'description' => null,
                ]];
            }
            unset($config['filename'], $config['stored_filename']);
            $productFiles[(int) $product['id']] = $config['files'] ?? [];
            $patcher->executeSql('UPDATE product SET config = :config WHERE id = :id', [
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'id' => $product['id'],
            ]);
        }

        $orders = $patcher->fetchAll("SELECT id, product_id, config FROM client_order WHERE service_type = 'downloadable'");
        foreach ($orders as $order) {
            $config = json_decode((string) $order['config'], true) ?: [];
            if (!isset($config['files']) && !empty($config['filename']) && !empty($config['stored_filename'])) {
                $matchingProductFile = null;
                foreach ($productFiles[(int) $order['product_id']] ?? [] as $productFile) {
                    if (($productFile['stored_filename'] ?? null) === $config['stored_filename']) {
                        $matchingProductFile = $productFile;

                        break;
                    }
                }
                $config['files'] = [$matchingProductFile ?? [
                    'id' => bin2hex(random_bytes(16)),
                    'filename' => $config['filename'],
                    'stored_filename' => $config['stored_filename'],
                    'label' => null,
                    'description' => null,
                ]];
            }
            unset($config['filename'], $config['stored_filename']);
            $patcher->executeSql('UPDATE client_order SET config = :config WHERE id = :id', [
                'config' => json_encode($config, JSON_THROW_ON_ERROR),
                'id' => $order['id'],
            ]);
        }

        if ($patcher->tableHasColumn('service_downloadable', 'filename')) {
            $services = $patcher->fetchAll(
                "SELECT sd.id, sd.filename, sd.stored_filename, sd.downloads, sd.created_at, sd.updated_at, co.config
                 FROM service_downloadable sd
                 LEFT JOIN client_order co ON co.service_type = 'downloadable' AND co.service_id = sd.id"
            );
            foreach ($services as $service) {
                $config = json_decode((string) ($service['config'] ?? ''), true) ?: [];
                $files = $config['files'] ?? [];
                if ($files === [] && !empty($service['filename']) && !empty($service['stored_filename'])) {
                    $files = [[
                        'id' => bin2hex(random_bytes(16)),
                        'filename' => $service['filename'],
                        'stored_filename' => $service['stored_filename'],
                        'label' => null,
                        'description' => null,
                    ]];
                }

                foreach ($files as $position => $file) {
                    if (empty($file['id']) || empty($file['filename']) || empty($file['stored_filename'])) {
                        continue;
                    }
                    $patcher->executeSql(
                        'INSERT INTO service_downloadable_file (service_downloadable_id, file_key, filename, stored_filename, label, description, downloads, sort_order, created_at, updated_at)
                         VALUES (:service_id, :file_key, :filename, :stored_filename, :label, :description, :downloads, :sort_order, :created_at, :updated_at)
                         ON DUPLICATE KEY UPDATE filename = VALUES(filename), stored_filename = VALUES(stored_filename), label = VALUES(label), description = VALUES(description), downloads = VALUES(downloads), sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)',
                        [
                            'service_id' => $service['id'],
                            'file_key' => $file['id'],
                            'filename' => $file['filename'],
                            'stored_filename' => $file['stored_filename'],
                            'label' => $file['label'] ?? null,
                            'description' => $file['description'] ?? null,
                            'downloads' => $position === 0 ? (int) ($service['downloads'] ?? 0) : 0,
                            'sort_order' => $position,
                            'created_at' => $service['created_at'],
                            'updated_at' => $service['updated_at'],
                        ]
                    );
                }
            }
        }

        foreach (['filename', 'stored_filename', 'downloads'] as $column) {
            if ($patcher->tableHasColumn('service_downloadable', $column)) {
                $patcher->executeSql(sprintf('ALTER TABLE `service_downloadable` DROP COLUMN `%s`', $column));
            }
        }
    }
}
