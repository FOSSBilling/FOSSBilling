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

class Patch97 implements PatchInterface
{
    public function getVersion(): int
    {
        return 97;
    }

    public function apply(Patcher $patcher): void
    {
        // Failed execution attempt counter for the bounded-retry logic in
        // ServiceInvoiceItem before an item is marked STATUS_FAILED.
        if (!$patcher->tableHasColumn('invoice_item', 'attempts')) {
            $patcher->executeSql("ALTER TABLE `invoice_item` ADD COLUMN `attempts` INT NOT NULL DEFAULT '0' AFTER `taxed`");
        }
    }
}
