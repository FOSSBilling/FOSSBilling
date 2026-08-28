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

class Patch72 implements PatchInterface
{
    public function getVersion(): int
    {
        return 72;
    }

    public function apply(Patcher $patcher): void
    {
        $patcher->executeFileActions([
            Path::join(PATH_LIBRARY, 'Model', 'Product.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductCategory.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductCustom.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDomain.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDomainTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductDownloadable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductHosting.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductLicense.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductPayment.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'ProductTable.php') => 'unlink',
            Path::join(PATH_LIBRARY, 'Model', 'Promo.php') => 'unlink',
            Path::join(PATH_CACHE, 'classMap.php') => 'unlink',
            Path::join(PATH_CACHE, 'fallbackClassMap.php') => 'unlink',
        ]);

        if (!$patcher->tableExists('promo_redemption')) {
            $patcher->executeSql(
                "CREATE TABLE `promo_redemption` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `promo_id` bigint(20) DEFAULT NULL,
                    `client_id` bigint(20) DEFAULT NULL,
                    `client_order_id` bigint(20) DEFAULT NULL,
                    `invoice_id` bigint(20) DEFAULT NULL,
                    `phase` varchar(30) NOT NULL DEFAULT 'checkout',
                    `status` varchar(30) NOT NULL DEFAULT 'reserved',
                    `discount_amount` decimal(18,2) DEFAULT NULL,
                    `currency` varchar(20) DEFAULT NULL,
                    `committed_at` datetime DEFAULT NULL,
                    `released_at` datetime DEFAULT NULL,
                    `release_reason` varchar(100) DEFAULT NULL,
                    `created_at` datetime DEFAULT NULL,
                    `updated_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `promo_id_idx` (`promo_id`),
                    KEY `client_id_idx` (`client_id`),
                    KEY `client_order_id_idx` (`client_order_id`),
                    KEY `invoice_id_idx` (`invoice_id`),
                    KEY `phase_idx` (`phase`),
                    KEY `status_idx` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
            );
        }

        $schemaManager = $patcher->di['dbal']->createSchemaManager();
        $table = $schemaManager->introspectTable('promo_redemption');
        $columns = [];

        if (!$table->hasColumn('status')) {
            $columns[] = "ADD COLUMN `status` varchar(30) NOT NULL DEFAULT 'reserved' AFTER `phase`";
        }

        if (!$table->hasColumn('committed_at')) {
            $columns[] = 'ADD COLUMN `committed_at` datetime DEFAULT NULL AFTER `currency`';
        }

        if (!$table->hasColumn('released_at')) {
            $columns[] = 'ADD COLUMN `released_at` datetime DEFAULT NULL AFTER `committed_at`';
        }

        if (!$table->hasColumn('release_reason')) {
            $columns[] = 'ADD COLUMN `release_reason` varchar(100) DEFAULT NULL AFTER `released_at`';
        }

        if ($columns !== []) {
            $patcher->executeSql('ALTER TABLE promo_redemption ' . implode(', ', $columns));
        }

        $table = $schemaManager->introspectTable('promo_redemption');
        $expectedIndexes = [
            'promo_id_idx' => 'promo_id',
            'client_id_idx' => 'client_id',
            'client_order_id_idx' => 'client_order_id',
            'invoice_id_idx' => 'invoice_id',
            'phase_idx' => 'phase',
            'status_idx' => 'status',
        ];

        foreach ($expectedIndexes as $indexName => $columnName) {
            if (!$table->hasIndex($indexName)) {
                $patcher->executeSql(sprintf(
                    'ALTER TABLE promo_redemption ADD INDEX `%s` (`%s`)',
                    $indexName,
                    $columnName
                ));
            }
        }

        $existingRedemptions = (int) $patcher->di['dbal']->executeQuery('SELECT COUNT(id) FROM promo_redemption')->fetchOne();
        if ($existingRedemptions === 0) {
            $orders = $patcher->di['dbal']->executeQuery(
                'SELECT id, promo_id, client_id, promo_used, discount, currency, created_at, updated_at
                 FROM client_order
                 WHERE promo_id IS NOT NULL'
            )->fetchAllAssociative();

            foreach ($orders as $order) {
                $checkoutCreatedAt = $order['created_at'] ?? date('Y-m-d H:i:s');
                $renewalCreatedAt = $order['updated_at'] ?: $checkoutCreatedAt;
                $discountAmount = $order['discount'] !== null ? (float) $order['discount'] : null;

                $patcher->di['dbal']->insert('promo_redemption', [
                    'promo_id' => $order['promo_id'],
                    'client_id' => $order['client_id'],
                    'client_order_id' => $order['id'],
                    'invoice_id' => null,
                    'phase' => 'checkout',
                    'status' => 'committed',
                    'discount_amount' => $discountAmount,
                    'currency' => $order['currency'],
                    'committed_at' => $checkoutCreatedAt,
                    'released_at' => null,
                    'release_reason' => null,
                    'created_at' => $checkoutCreatedAt,
                    'updated_at' => $checkoutCreatedAt,
                ]);

                $renewalCount = max(0, ((int) ($order['promo_used'] ?? 1)) - 1);
                for ($i = 0; $i < $renewalCount; ++$i) {
                    $patcher->di['dbal']->insert('promo_redemption', [
                        'promo_id' => $order['promo_id'],
                        'client_id' => $order['client_id'],
                        'client_order_id' => $order['id'],
                        'invoice_id' => null,
                        'phase' => 'renewal',
                        'status' => 'committed',
                        'discount_amount' => $discountAmount,
                        'currency' => $order['currency'],
                        'committed_at' => $renewalCreatedAt,
                        'released_at' => null,
                        'release_reason' => null,
                        'created_at' => $renewalCreatedAt,
                        'updated_at' => $renewalCreatedAt,
                    ]);
                }
            }
        }

        // Normalize rows written by a previous partial migration run that pre-dated the
        // status column; those rows have a NULL/empty status.  Legitimate 'reserved'
        // rows created by concurrent checkouts are left untouched so the payment flow
        // can commit or release them normally.
        $patcher->executeSql("
            UPDATE promo_redemption
            SET
                status = 'committed',
                committed_at = COALESCE(committed_at, created_at),
                release_reason = NULL
            WHERE status IS NULL OR status = ''
        ");
    }
}
