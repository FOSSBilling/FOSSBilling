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

class Patch101 implements PatchInterface
{
    public function getVersion(): int
    {
        return 101;
    }

    public function apply(Patcher $patcher): void
    {
        // findByUnpaidInvoiceId() (invoice cancellation/deletion) filters client_order by this
        // column, previously unindexed.
        if (!$patcher->tableHasIndex('client_order', 'client_order_unpaid_invoice_id_idx')) {
            $patcher->executeSql('ALTER TABLE `client_order` ADD INDEX `client_order_unpaid_invoice_id_idx` (`unpaid_invoice_id`)');
        }
    }
}
