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

class Patch119 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The credit/debit-note releases added three entity columns without a MySQL patch,
        // relying on the ambient schema sync - which only runs inside version-gated
        // finalization, so code-only deploys (e.g. `git pull` with no Version::VERSION bump)
        // crash with "Unknown column 'credit_note_for_invoice_id'" instead. Create them
        // explicitly here; the portable sync covers non-MySQL drivers and same-version
        // deploys via ensureSchemaInSync(). All guards make reruns (and installs that
        // already synced these) no-ops.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/4392
        if (!$patcher->tableHasColumn('invoice', 'credit_note_for_invoice_id')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `credit_note_for_invoice_id` bigint(20) DEFAULT NULL AFTER `status`');
        }
        if (!$patcher->tableHasIndex('invoice', 'invoice_credit_note_for_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD INDEX `invoice_credit_note_for_idx` (`credit_note_for_invoice_id`)');
        }

        if (!$patcher->tableHasColumn('invoice', 'debit_note_for_invoice_id')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD COLUMN `debit_note_for_invoice_id` bigint(20) DEFAULT NULL AFTER `credit_note_for_invoice_id`');
        }
        if (!$patcher->tableHasIndex('invoice', 'invoice_debit_note_for_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice` ADD INDEX `invoice_debit_note_for_idx` (`debit_note_for_invoice_id`)');
        }

        if (!$patcher->tableHasColumn('invoice_item', 'refunded_item_id')) {
            $patcher->executeSql('ALTER TABLE `invoice_item` ADD COLUMN `refunded_item_id` bigint(20) DEFAULT NULL AFTER `rel_id`');
        }
    }
}
