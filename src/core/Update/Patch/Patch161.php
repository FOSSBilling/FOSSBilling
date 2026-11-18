<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch161 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasColumn('transaction', 'processing_token')) {
            $patcher->executeSql('ALTER TABLE `transaction` ADD COLUMN `processing_token` VARCHAR(32) DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('invoice_gateway_charge_attempt', 'owner_token')) {
            $patcher->executeSql('ALTER TABLE `invoice_gateway_charge_attempt` ADD COLUMN `owner_token` VARCHAR(32) DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('transaction', 'credit_recovery_state')) {
            $patcher->executeSql('ALTER TABLE `transaction` ADD COLUMN `credit_recovery_state` SMALLINT DEFAULT NULL');
        }
        if (!$patcher->tableHasColumn('client_balance', 'payment_key')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD COLUMN `payment_key` VARCHAR(64) DEFAULT NULL');
        }
        if (!$patcher->tableHasIndex('client_balance', 'uniq_payment_credit')) {
            $patcher->executeSql('CREATE UNIQUE INDEX `uniq_payment_credit` ON `client_balance` (`payment_key`)');
        }
    }
}
