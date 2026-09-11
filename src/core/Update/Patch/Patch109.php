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

class Patch109 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('product', 'suspension_grace_days')) {
            $patcher->executeSql("ALTER TABLE `product` ADD COLUMN `suspension_grace_days` int(11) NOT NULL DEFAULT '0' AFTER `quantity_in_stock`");
        }

        if (!$patcher->tableHasColumn('client_order', 'suspension_grace_days')) {
            $patcher->executeSql('ALTER TABLE `client_order` ADD COLUMN `suspension_grace_days` int(11) DEFAULT NULL AFTER `config`');
        }

        if (!$patcher->tableHasIndex('client_order', 'client_order_status_expires_at_idx')) {
            $patcher->executeSql('ALTER TABLE `client_order` ADD INDEX `client_order_status_expires_at_idx` (`status`, `expires_at`)');
        }
    }
}
