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

class Patch126 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Per-client renewal merge preference (#4118): tri-state column,
        // NULL inherits the global `invoice_merge_renewals` setting. Guarded
        // so reruns are no-ops; non-MySQL drivers get the column from the
        // portable schema sync.
        if (!$patcher->tableHasColumn('client', 'merge_renewals')) {
            $patcher->executeSql('ALTER TABLE `client` ADD COLUMN `merge_renewals` TINYINT(1) DEFAULT NULL');
        }
    }
}
