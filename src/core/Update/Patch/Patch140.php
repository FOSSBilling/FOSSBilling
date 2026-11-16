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

class Patch140 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // #4673 added base_refund_tax so the cash tax portion of a refund stays
        // derivable while base_refund nets out of income without tax. MySQL
        // upgrades must not depend on the ambient schema sync (see #4392):
        // create it explicitly here; the portable sync covers other drivers.
        // The guard makes reruns (and installs that already synced it) no-ops.
        if (!$patcher->tableHasColumn('invoice', 'base_refund_tax')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `base_refund_tax` decimal(18,2) DEFAULT NULL AFTER `base_refund`');
        }
    }
}
