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

class Patch151 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Account credit applied to unpaid invoices ships with a new
        // invoice_credit entity table. Installs upgrading past it rely on
        // this patch on MySQL; fresh installs and other drivers get the
        // table from the portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('invoice_credit')) {
            $patcher->executeSql(
                'CREATE TABLE `invoice_credit` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `invoice_id` BIGINT DEFAULT NULL,
                    `client_id` BIGINT DEFAULT NULL,
                    `amount` DECIMAL(18,2) DEFAULT \'0.00\',
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `invoice_credit_invoice_id_idx` (`invoice_id`),
                    KEY `invoice_credit_client_id_idx` (`client_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
}
