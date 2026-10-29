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

class Patch122 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // invoice.buyer_phone_cc was write-only dead data: nothing ever wrote
        // it except null-to-null copies, and nothing rendered it. The entity
        // no longer maps it, so drop the column; the portable drop below
        // covers non-MySQL drivers. The guard makes reruns a no-op.
        if ($patcher->tableHasColumn('invoice', 'buyer_phone_cc')) {
            $patcher->executeSql('ALTER TABLE `invoice` DROP COLUMN `buyer_phone_cc`');
        }
    }
}
