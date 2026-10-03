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

class Patch121 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The invoice issue-terminology rename moves invoice.approved to
        // invoice.issued. Copy values into the new column, then drop the
        // legacy one; the portable rename covers non-MySQL drivers, and the
        // index name is swapped by renameInvoiceStatusIndex() below.
        // The copy runs on every pass until the legacy column is gone, not just on
        // creation: DDL auto-commits, so a crash between ADD and UPDATE leaves both
        // columns behind with issued still defaulted, and a create-only guard would
        // skip the copy forever afterward. Re-copying is idempotent.
        if ($patcher->tableHasColumn('invoice', 'approved')) {
            if (!$patcher->tableHasColumn('invoice', 'issued')) {
                $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `issued` TINYINT(1) NOT NULL DEFAULT 0 AFTER `approved`');
            }
            $patcher->executeSql('UPDATE `invoice` SET `issued` = `approved`');
            $patcher->executeSql('ALTER TABLE `invoice` DROP COLUMN `approved`');
        }
    }
}
