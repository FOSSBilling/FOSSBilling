<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch142 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('client_balance', 'gateway_receipt_key')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD COLUMN `gateway_receipt_key` VARCHAR(64) DEFAULT NULL');
        }
        if (!$patcher->tableHasIndex('client_balance', 'uniq_gateway_receipt_key')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD UNIQUE INDEX `uniq_gateway_receipt_key` (`gateway_receipt_key`)');
        }
        if (!$patcher->tableHasColumn('transaction', 'original_invoice_id')) {
            $patcher->executeSql('ALTER TABLE `transaction` ADD COLUMN `original_invoice_id` BIGINT DEFAULT NULL');
        }
    }
}
