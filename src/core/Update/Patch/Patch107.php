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

class Patch107 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Move product_payment's fixed w/m/q/b/a/bia/tria recurring pricing columns into a
        // proper one-row-per-period table, so admins can configure arbitrary billing periods
        // (e.g. 45 days, 18 months, 5 years) instead of being limited to exactly 7 presets.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4098
        if (!$patcher->tableExists('product_payment_period')) {
            $patcher->executeSql(
                'CREATE TABLE `product_payment_period` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `product_payment_id` bigint(20) NOT NULL,
                    `code` varchar(10) NOT NULL,
                    `price` decimal(18,2) NOT NULL DEFAULT \'0.00\',
                    `setup_price` decimal(18,2) NOT NULL DEFAULT \'0.00\',
                    `enabled` tinyint(1) NOT NULL DEFAULT \'1\',
                    `sort_order` int(11) NOT NULL DEFAULT \'0\',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `product_payment_period_unique` (`product_payment_id`,`code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;'
            );
        }

        if (!$patcher->tableHasColumn('product_payment', 'w_price')) {
            return;
        }

        // Legacy DB column prefix => billing period code.
        $legacyPeriods = [
            'w' => '1W',
            'm' => '1M',
            'q' => '3M',
            'b' => '6M',
            'a' => '1Y',
            'bia' => '2Y',
            'tria' => '3Y',
        ];

        $columns = implode(', ', array_map(
            static fn (string $prefix): string => "`{$prefix}_price`, `{$prefix}_setup_price`, `{$prefix}_enabled`",
            array_keys($legacyPeriods)
        ));

        $rows = $patcher->fetchAll("SELECT `id`, {$columns} FROM `product_payment`");
        foreach ($rows as $row) {
            $sortOrder = 0;
            foreach ($legacyPeriods as $prefix => $code) {
                // ON DUPLICATE KEY UPDATE makes this safe to rerun if a prior attempt at
                // this patch inserted some rows before failing partway through.
                $patcher->executeSql(
                    'INSERT INTO `product_payment_period` (`product_payment_id`, `code`, `price`, `setup_price`, `enabled`, `sort_order`)
                     VALUES (:product_payment_id, :code, :price, :setup_price, :enabled, :sort_order)
                     ON DUPLICATE KEY UPDATE `price` = VALUES(`price`), `setup_price` = VALUES(`setup_price`), `enabled` = VALUES(`enabled`), `sort_order` = VALUES(`sort_order`)',
                    [
                        'product_payment_id' => $row['id'],
                        'code' => $code,
                        'price' => $row["{$prefix}_price"],
                        'setup_price' => $row["{$prefix}_setup_price"],
                        'enabled' => $row["{$prefix}_enabled"],
                        'sort_order' => $sortOrder++,
                    ]
                );
            }
        }

        $columnsToDrop = [];
        foreach (array_keys($legacyPeriods) as $prefix) {
            $columnsToDrop[] = "DROP COLUMN `{$prefix}_price`";
            $columnsToDrop[] = "DROP COLUMN `{$prefix}_setup_price`";
            $columnsToDrop[] = "DROP COLUMN `{$prefix}_enabled`";
        }

        $patcher->executeSql('ALTER TABLE `product_payment` ' . implode(', ', $columnsToDrop) . ';');
    }
}
