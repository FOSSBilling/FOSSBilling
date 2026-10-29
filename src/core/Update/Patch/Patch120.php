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

class Patch120 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The invoice reissue release added two entity columns without a MySQL patch,
        // repeating the credit/debit-note pattern from patch119: installs that never
        // ran the ambient schema sync crash with "Unknown column 'replaces_invoice_id'"
        // instead. Create them explicitly here; the portable sync covers non-MySQL
        // drivers and same-version deploys via ensureSchemaInSync(). All guards make
        // reruns (and installs that already synced these) no-ops.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4392
        if (!$patcher->tableHasColumn('invoice', 'replaces_invoice_id')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `replaces_invoice_id` bigint(20) DEFAULT NULL AFTER `debit_note_for_invoice_id`');
        }
        if (!$patcher->tableHasIndex('invoice', 'invoice_replaces_invoice_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD INDEX `invoice_replaces_invoice_idx` (`replaces_invoice_id`)');
        }

        if (!$patcher->tableHasColumn('invoice', 'replaced_by_invoice_id')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `replaced_by_invoice_id` bigint(20) DEFAULT NULL AFTER `replaces_invoice_id`');
        }
        if (!$patcher->tableHasIndex('invoice', 'invoice_replaced_by_invoice_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD INDEX `invoice_replaced_by_invoice_idx` (`replaced_by_invoice_id`)');
        }
    }
}
