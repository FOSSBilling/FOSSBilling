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

class Patch88 implements PatchInterface
{
    public function getVersion(): int
    {
        return 88;
    }

    public function apply(Patcher $patcher): void
    {
        // Invoice reminder batches now query unpaid/approved invoices by due date on every cron
        // run (instead of once a day), so index the columns those queries filter on.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/3963
        if (!$patcher->tableHasIndex('invoice', 'invoice_status_approved_due_at_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD INDEX `invoice_status_approved_due_at_idx` (`status`, `approved`, `due_at`)');
        }
    }
}
