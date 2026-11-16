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

class Patch154 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Secrets clients attach to tickets (encrypted) and their audit
        // trail. Installs upgrading past it rely on this patch on MySQL;
        // fresh installs and other drivers get the tables from the portable
        // schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_ticket_secret')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_secret` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `ticket_id` BIGINT NOT NULL,
                    `kind` VARCHAR(10) NOT NULL,
                    `label` VARCHAR(100) NOT NULL,
                    `data` TEXT NOT NULL,
                    `added_by_client_id` BIGINT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ticket` (`ticket_id`),
                    KEY `created` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }

        if (!$patcher->tableExists('support_ticket_secret_event')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_secret_event` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `ticket_id` BIGINT NOT NULL,
                    `secret_label` VARCHAR(100) NOT NULL,
                    `action` VARCHAR(10) NOT NULL,
                    `admin_id` BIGINT DEFAULT NULL,
                    `client_id` BIGINT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ticket` (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
