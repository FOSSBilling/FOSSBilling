<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch146 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Typed order linkage for invoice items (see https://github.com/FOSSBilling/FOSSBilling/issues/4688):
        // comparing TEXT rel_id to client_order.id converts the column per row and can never
        // use invoice_item_pending_renewal_idx. The new BIGINT column carries every database
        // predicate that joins invoice items to orders instead. PostgreSQL/SQLite installs get
        // the column and index from the portable schema sync; the row backfill runs for every
        // driver via Patcher::backfillInvoiceItemOrderIds(). All guards make reruns no-ops.
        if (!$patcher->tableHasColumn('invoice_item', 'order_id')) {
            $patcher->executeSql('ALTER TABLE `invoice_item` ADD COLUMN `order_id` bigint(20) DEFAULT NULL AFTER `rel_id`');
        }
        if (!$patcher->tableHasIndex('invoice_item', 'invoice_item_order_lookup_idx')) {
            $patcher->executeSql('ALTER TABLE `invoice_item` ADD INDEX `invoice_item_order_lookup_idx` (`order_id`, `type`, `task`, `status`, `invoice_id`)');
        }
    }
}
