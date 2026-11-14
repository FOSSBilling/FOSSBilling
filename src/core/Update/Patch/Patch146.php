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

class Patch146 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Ticket escalation rules and the replies they already acted on.
        // Installs upgrading past it rely on this patch on MySQL; fresh
        // installs and other drivers get the tables from the portable schema
        // sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_escalation_rule')) {
            $patcher->executeSql(
                'CREATE TABLE `support_escalation_rule` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `name` VARCHAR(100) NOT NULL,
                    `active` TINYINT(1) NOT NULL DEFAULT 1,
                    `helpdesks` TEXT NOT NULL,
                    `statuses` TEXT NOT NULL,
                    `priorities` TEXT NOT NULL,
                    `minutes` INT NOT NULL,
                    `new_helpdesk_id` BIGINT DEFAULT NULL,
                    `new_status` VARCHAR(30) NOT NULL DEFAULT \'\',
                    `new_priority` VARCHAR(10) NOT NULL DEFAULT \'\',
                    `assign_to` BIGINT DEFAULT NULL,
                    `notify` TEXT NOT NULL,
                    `reply` TEXT NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_escalation_applied')) {
            $patcher->executeSql(
                'CREATE TABLE `support_escalation_applied` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `rule_id` BIGINT NOT NULL,
                    `ticket_id` BIGINT NOT NULL,
                    `reply_at` DATETIME NOT NULL,
                    `applied_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `rule_ticket` (`rule_id`, `ticket_id`),
                    KEY `ticket` (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
