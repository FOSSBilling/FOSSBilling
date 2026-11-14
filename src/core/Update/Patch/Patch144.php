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

class Patch144 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Help desks and knowledge base categories hidden from clients.
        // Installs upgrading past it rely on this patch on MySQL; fresh
        // installs and other drivers get the tables from the portable schema
        // sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_helpdesk_hidden')) {
            $patcher->executeSql(
                'CREATE TABLE `support_helpdesk_hidden` (
                    `helpdesk_id` BIGINT NOT NULL,
                    `hidden_at` DATETIME NOT NULL,
                    PRIMARY KEY (`helpdesk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_kb_category_hidden')) {
            $patcher->executeSql(
                'CREATE TABLE `support_kb_category_hidden` (
                    `category_id` BIGINT NOT NULL,
                    `hidden_at` DATETIME NOT NULL,
                    PRIMARY KEY (`category_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
