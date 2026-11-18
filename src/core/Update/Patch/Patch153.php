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

class Patch153 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Automatic saved-method charges count their tries per invoice so the
        // cron spaces retries and eventually stops. Installs upgrading past
        // it rely on this patch on MySQL; fresh installs and other drivers
        // get the table from the portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('invoice_card_charge')) {
            $patcher->executeSql(
                'CREATE TABLE `invoice_card_charge` (
                    `invoice_id` BIGINT NOT NULL,
                    `attempts` INT NOT NULL DEFAULT 0,
                    `last_attempt_at` DATETIME DEFAULT NULL,
                    `last_error` VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY (`invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
}
