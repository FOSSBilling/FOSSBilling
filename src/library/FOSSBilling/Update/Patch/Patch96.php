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

class Patch96 implements PatchInterface
{
    public function getVersion(): int
    {
        return 96;
    }

    public function apply(Patcher $patcher): void
    {
        // Unique constraint on client_balance.invoice_item_id prevents duplicate credits
        // for the same invoice item. MySQL treats multiple NULLs as distinct, so other
        // rows (transaction debits, default deductions) are unaffected.
        if (!$patcher->tableHasColumn('client_balance', 'invoice_item_id')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD COLUMN `invoice_item_id` BIGINT DEFAULT NULL AFTER `rel_id`');
        }

        if (!$patcher->tableHasIndex('client_balance', 'uniq_invoice_item_credit')) {
            $patcher->executeSql('ALTER TABLE `client_balance` ADD UNIQUE INDEX `uniq_invoice_item_credit` (`invoice_item_id`)');
        }
    }
}
