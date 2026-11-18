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

class Patch147 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Cancellation requests ship with a new client_order_cancel_request
        // entity table. Installs upgrading past it rely on this patch on
        // MySQL; fresh installs and other drivers get the table from the
        // portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('client_order_cancel_request')) {
            $patcher->executeSql(
                'CREATE TABLE `client_order_cancel_request` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `client_order_id` BIGINT DEFAULT NULL,
                    `client_id` BIGINT DEFAULT NULL,
                    `type` VARCHAR(32) DEFAULT NULL,
                    `reason` TEXT DEFAULT NULL,
                    `status` VARCHAR(32) DEFAULT NULL,
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `client_order_cancel_request_order_id_idx` (`client_order_id`),
                    KEY `client_order_cancel_request_status_idx` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
}
