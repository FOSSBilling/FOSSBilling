<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;
use Symfony\Component\Filesystem\Path;

class Patch65 implements PatchInterface
{
    private array $downloadableStorageMigrationMap = [];

    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('service_downloadable', 'stored_filename')) {
            $patcher->executeSql('ALTER TABLE `service_downloadable` ADD COLUMN `stored_filename` VARCHAR(100) DEFAULT NULL AFTER `filename`;');
        }

        $this->downloadableStorageMigrationMap = [];
        $this->migrateDownloadableProductStorageKeys($patcher);
        $this->migrateDownloadableServiceStorageKeys($patcher);
        $this->migrateDownloadableOrderStorageKeys($patcher);
    }

    private function generateDownloadableStoredFilename(Patcher $patcher): string
    {
        do {
            $storedFilename = bin2hex(random_bytes(32));
            $filePath = Path::join(PATH_UPLOADS, $storedFilename);
        } while ($patcher->filesystem->exists($filePath));

        return $storedFilename;
    }

    private function copyLegacyDownloadableFile(Patcher $patcher, string $filename): ?string
    {
        if (isset($this->downloadableStorageMigrationMap[$filename])) {
            return $this->downloadableStorageMigrationMap[$filename];
        }

        $legacyPath = Path::join(PATH_UPLOADS, md5($filename));
        if (!$patcher->filesystem->exists($legacyPath)) {
            return null;
        }

        $storedFilename = $this->generateDownloadableStoredFilename($patcher);
        $patcher->filesystem->copy($legacyPath, Path::join(PATH_UPLOADS, $storedFilename));
        $this->downloadableStorageMigrationMap[$filename] = $storedFilename;

        return $storedFilename;
    }

    private function migrateDownloadableProductStorageKeys(Patcher $patcher): void
    {
        $products = $patcher->fetchAll("SELECT id, config FROM product WHERE type = 'downloadable'");

        foreach ($products as $product) {
            $config = json_decode((string) $product['config'], true) ?: [];
            if (!isset($config['filename']) || isset($config['stored_filename'])) {
                continue;
            }

            $storedFilename = $this->copyLegacyDownloadableFile($patcher, (string) $config['filename']);
            if ($storedFilename === null) {
                continue;
            }

            $config['stored_filename'] = $storedFilename;
            $patcher->executeSql('UPDATE product SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $product['id'],
            ]);
        }
    }

    private function migrateDownloadableServiceStorageKeys(Patcher $patcher): void
    {
        $services = $patcher->fetchAll('SELECT sd.id, sd.filename, sd.stored_filename, co.id AS order_id, co.config AS order_config FROM service_downloadable sd LEFT JOIN client_order co ON sd.id = co.service_id AND co.service_type = "downloadable" WHERE sd.filename IS NOT NULL AND sd.filename != ""');
        $processedServiceUpdates = [];

        foreach ($services as $service) {
            if (!empty($service['stored_filename'])) {
                $storedFilename = (string) $service['stored_filename'];
            } else {
                $serviceId = (int) $service['id'];
                if (isset($processedServiceUpdates[$serviceId])) {
                    $storedFilename = $this->copyLegacyDownloadableFile($patcher, (string) $service['filename']);
                } else {
                    $storedFilename = $this->copyLegacyDownloadableFile($patcher, (string) $service['filename']);
                    if ($storedFilename === null) {
                        continue;
                    }

                    $patcher->executeSql('UPDATE service_downloadable SET stored_filename = :stored_filename, updated_at = :updated_at WHERE id = :id', [
                        'stored_filename' => $storedFilename,
                        'updated_at' => date('Y-m-d H:i:s'),
                        'id' => $service['id'],
                    ]);
                    $processedServiceUpdates[$serviceId] = true;
                }
            }

            if (empty($service['order_id'])) {
                continue;
            }

            $orderConfig = json_decode($service['order_config'] ?? '', true) ?: [];
            if (isset($orderConfig['stored_filename'])) {
                continue;
            }

            $orderConfig['filename'] ??= $service['filename'];
            $orderConfig['stored_filename'] = $storedFilename;
            $patcher->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($orderConfig),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $service['order_id'],
            ]);
        }
    }

    private function migrateDownloadableOrderStorageKeys(Patcher $patcher): void
    {
        $orders = $patcher->fetchAll("SELECT id, config FROM client_order WHERE service_type = 'downloadable' AND config LIKE '%filename%'");

        foreach ($orders as $order) {
            $config = json_decode($order['config'] ?? '', true) ?: [];
            if (!isset($config['filename']) || isset($config['stored_filename'])) {
                continue;
            }

            $storedFilename = $this->copyLegacyDownloadableFile($patcher, (string) $config['filename']);
            if ($storedFilename === null) {
                continue;
            }

            $config['stored_filename'] = $storedFilename;
            $patcher->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                'config' => json_encode($config),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $order['id'],
            ]);
        }
    }
}
