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
use Symfony\Component\Filesystem\Path;

class Patch48 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        $filesystem = $patcher->filesystem;

        $oldUploadsPath = Path::join(PATH_ROOT, 'uploads');
        $newUploadsPath = Path::join(PATH_ROOT, 'data', 'uploads');

        if ($filesystem->exists($oldUploadsPath) && $filesystem->exists($newUploadsPath)) {
            foreach (glob($oldUploadsPath . '/*') ?: [] as $oldFile) {
                if (is_file($oldFile)) {
                    $filename = basename($oldFile);
                    $newFilePath = Path::join($newUploadsPath, $filename);
                    if (!$filesystem->exists($newFilePath)) {
                        $filesystem->rename($oldFile, $newFilePath);
                    }
                }
            }
        }

        $products = $patcher->fetchAll("SELECT p.id, p.config FROM product p WHERE p.type = 'downloadable'");

        foreach ($products as $product) {
            $productConfig = json_decode((string) $product['config'], true) ?: [];

            if (!empty($productConfig['filename'])) {
                continue;
            }

            $foundFilename = null;

            $orders = $patcher->fetchAll('SELECT co.id, co.config, co.service_id FROM client_order co WHERE co.product_id = :product_id', ['product_id' => $product['id']]);

            foreach ($orders as $order) {
                $orderConfig = json_decode($order['config'] ?? '', true);
                if (!is_array($orderConfig) || !isset($orderConfig['filename'])) {
                    continue;
                }

                $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                if ($filesystem->exists($filePath)) {
                    $foundFilename = $orderConfig['filename'];

                    break;
                }
            }

            if ($foundFilename === null) {
                $services = $patcher->fetchAll('SELECT sd.id, sd.filename FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE co.product_id = :product_id AND sd.filename IS NOT NULL AND sd.filename != ""', ['product_id' => $product['id']]);

                foreach ($services as $service) {
                    $filePath = Path::join(PATH_UPLOADS, md5((string) $service['filename']));
                    if ($filesystem->exists($filePath)) {
                        $foundFilename = $service['filename'];

                        break;
                    }
                }
            }

            if ($foundFilename !== null) {
                $productConfig['filename'] = $foundFilename;
                $patcher->executeSql('UPDATE product SET config = :config, updated_at = :updated_at WHERE id = :id', [
                    'config' => json_encode($productConfig),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $product['id'],
                ]);

                $patcher->executeSql('UPDATE service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id SET sd.filename = :filename WHERE co.product_id = :product_id', ['filename' => $foundFilename, 'product_id' => $product['id']]);

                $ordersToUpdate = $patcher->fetchAll('SELECT id, config FROM client_order WHERE product_id = :product_id AND config LIKE "%filename%"', ['product_id' => $product['id']]);

                foreach ($ordersToUpdate as $orderToUpdate) {
                    $orderConfig = json_decode($orderToUpdate['config'] ?? '', true);
                    if (is_array($orderConfig) && isset($orderConfig['filename'])) {
                        $orderConfig['filename'] = $foundFilename;
                        $patcher->executeSql('UPDATE client_order SET config = :config, updated_at = :updated_at WHERE id = :id', [
                            'config' => json_encode($orderConfig),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'id' => $orderToUpdate['id'],
                        ]);
                    }
                }
            }
        }

        $orphans = $patcher->fetchAll('SELECT sd.id, co.config as order_config FROM service_downloadable sd INNER JOIN client_order co ON sd.id = co.service_id WHERE sd.filename IS NULL OR sd.filename = ""');

        foreach ($orphans as $orphan) {
            $orderConfig = json_decode($orphan['order_config'] ?? '', true);
            if (isset($orderConfig['filename']) && !empty($orderConfig['filename'])) {
                $filePath = Path::join(PATH_UPLOADS, md5((string) $orderConfig['filename']));
                if ($filesystem->exists($filePath)) {
                    $patcher->executeSql('UPDATE service_downloadable SET filename = :filename WHERE id = :id', ['filename' => $orderConfig['filename'], 'id' => $orphan['id']]);
                }
            }
        }
    }
}
