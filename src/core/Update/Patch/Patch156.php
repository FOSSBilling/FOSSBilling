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

class Patch156 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Tickets merged into another one. Installs upgrading past it rely
        // on this patch on MySQL; fresh installs and other drivers get the
        // table from the portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_ticket_merge')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_merge` (
                    `ticket_id` BIGINT NOT NULL,
                    `merged_into` BIGINT NOT NULL,
                    `access_hash` VARCHAR(255) DEFAULT NULL,
                    `subject` VARCHAR(255) NOT NULL DEFAULT \'\',
                    `merged_by` BIGINT DEFAULT NULL,
                    `merged_at` DATETIME NOT NULL,
                    PRIMARY KEY (`ticket_id`),
                    KEY `merged_into` (`merged_into`),
                    KEY `access_hash` (`access_hash`(191))
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
