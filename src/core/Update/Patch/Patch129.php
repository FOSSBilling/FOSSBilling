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

class Patch129 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Pay gateways gained a custom logo field (#4570). The portable schema sync
        // covers non-MySQL drivers; the guard makes reruns a no-op.
        if (!$patcher->tableHasColumn('pay_gateway', 'custom_logo')) {
            $patcher->executeSql('ALTER TABLE `pay_gateway` ADD COLUMN `custom_logo` VARCHAR(255) DEFAULT NULL');
        }
    }
}
