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

class Patch145 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Pinned tickets (staff list) and tickets assigned to a staff member.
        // Installs upgrading past it rely on this patch on MySQL; fresh
        // installs and other drivers get the tables from the portable schema
        // sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_ticket_pin')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_pin` (
                    `ticket_id` BIGINT NOT NULL,
                    `pinned_by` BIGINT DEFAULT NULL,
                    `pinned_at` DATETIME NOT NULL,
                    PRIMARY KEY (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_ticket_assignment')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_assignment` (
                    `ticket_id` BIGINT NOT NULL,
                    `admin_id` BIGINT NOT NULL,
                    `assigned_by` BIGINT DEFAULT NULL,
                    `assigned_at` DATETIME NOT NULL,
                    PRIMARY KEY (`ticket_id`),
                    KEY `admin` (`admin_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
