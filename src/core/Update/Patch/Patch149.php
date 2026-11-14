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

class Patch149 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Reply ratings, closing feedback and its request links, and the per
        // help desk feedback switch. Installs upgrading past it rely on this
        // patch on MySQL; fresh installs and other drivers get the tables
        // from the portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_ticket_message_rating')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_message_rating` (
                    `message_id` BIGINT NOT NULL,
                    `ticket_id` BIGINT NOT NULL,
                    `admin_id` BIGINT NOT NULL,
                    `rating` SMALLINT NOT NULL,
                    `rated_by_kind` VARCHAR(10) NOT NULL,
                    `rated_by_id` BIGINT NOT NULL,
                    `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
                    `rated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`message_id`),
                    KEY `ticket` (`ticket_id`),
                    KEY `admin_rated` (`admin_id`, `rated_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_ticket_feedback')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_feedback` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `ticket_id` BIGINT NOT NULL,
                    `admin_id` BIGINT DEFAULT NULL,
                    `rating` SMALLINT DEFAULT NULL,
                    `comments` TEXT NOT NULL,
                    `ip` VARCHAR(45) NOT NULL DEFAULT \'\',
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ticket` (`ticket_id`),
                    KEY `admin_created` (`admin_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_ticket_feedback_request')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_feedback_request` (
                    `ticket_id` BIGINT NOT NULL,
                    `token` CHAR(64) NOT NULL,
                    `sent_at` DATETIME NOT NULL,
                    `answered_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`ticket_id`),
                    UNIQUE KEY `token` (`token`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_helpdesk_feedback')) {
            $patcher->executeSql(
                'CREATE TABLE `support_helpdesk_feedback` (
                    `helpdesk_id` BIGINT NOT NULL,
                    `feedback_request` TINYINT(1) NOT NULL DEFAULT 1,
                    `updated_at` DATETIME NOT NULL,
                    PRIMARY KEY (`helpdesk_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
