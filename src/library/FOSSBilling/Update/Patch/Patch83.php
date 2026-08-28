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

class Patch83 implements PatchInterface
{
    public function getVersion(): int
    {
        return 83;
    }

    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableHasIndex('invoice_item', 'invoice_item_pending_renewal_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice_item` ADD INDEX `invoice_item_pending_renewal_idx` (`rel_id`(20), `type`, `task`, `status`, `invoice_id`)');
        }
    }
}
