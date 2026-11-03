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

class Patch127 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Promotion columns #4386 (auto_apply, priority, stackable) and #4401
        // (requires_products) shipped without a migration, crashing promo
        // loads with "Unknown column 't0.requires_products'" on installs the
        // portable sync didn't heal. Definitions match Doctrine's DDL for the
        // Promo entity. Guards make reruns no-ops; non-MySQL drivers use the
        // portable sync.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4433
        if (!$patcher->tableHasColumn('promo', 'requires_products')) {
            $patcher->executeSql('ALTER TABLE `promo` ADD COLUMN `requires_products` LONGTEXT DEFAULT NULL');
        }

        if (!$patcher->tableHasColumn('promo', 'auto_apply')) {
            $patcher->executeSql('ALTER TABLE `promo` ADD COLUMN `auto_apply` TINYINT DEFAULT 0');
        }

        if (!$patcher->tableHasColumn('promo', 'priority')) {
            $patcher->executeSql('ALTER TABLE `promo` ADD COLUMN `priority` INT DEFAULT 0');
        }

        if (!$patcher->tableHasColumn('promo', 'stackable')) {
            $patcher->executeSql('ALTER TABLE `promo` ADD COLUMN `stackable` TINYINT DEFAULT 0');
        }

        if (!$patcher->tableHasIndex('promo', 'auto_apply_index_idx')) {
            $patcher->executeSql('ALTER TABLE `promo` ADD INDEX `auto_apply_index_idx` (`auto_apply`)');
        }
    }
}
