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

class Patch142 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Older releases created this cache lazily. If it was never used,
        // the portable schema sync below the patch loop creates the full table.
        if (!$patcher->tableExists('pay_gateway_customer')) {
            return;
        }

        // Saved payment methods for automatic charges live on the gateway
        // customer row. Installs upgrading past it rely on this patch on
        // MySQL; fresh installs and other drivers get the columns from the
        // portable schema sync. Guarded rerun no-op.
        // test_mode is part of the row's identity: a gateway's test and live
        // keys are separate customer namespaces, so the unique key gains the
        // column. Existing rows predate test namespaces and stay live (0).
        if (!$patcher->tableHasColumn('pay_gateway_customer', 'test_mode')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD COLUMN `test_mode` TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$patcher->tableHasColumn('pay_gateway_customer', 'saved_cards')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD COLUMN `saved_cards` TINYINT(1) NOT NULL DEFAULT 0');
        }
        if (!$patcher->tableHasColumn('pay_gateway_customer', 'default_payment_method')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD COLUMN `default_payment_method` VARCHAR(255) DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('pay_gateway_customer', 'default_card')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD COLUMN `default_card` VARCHAR(100) DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('pay_gateway_customer', 'auto_pay_at')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD COLUMN `auto_pay_at` DATETIME DEFAULT NULL');
        }
        if ($patcher->tableHasIndex('pay_gateway_customer', 'pay_gateway_customer_gateway_client')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` DROP INDEX `pay_gateway_customer_gateway_client`');
        }
        if (!$patcher->tableHasIndex('pay_gateway_customer', 'pay_gateway_customer_gateway_client_mode')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway_customer` ADD UNIQUE KEY `pay_gateway_customer_gateway_client_mode` (`pay_gateway_id`, `client_id`, `test_mode`)');
        }
    }
}
