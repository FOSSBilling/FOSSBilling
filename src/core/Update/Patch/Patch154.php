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
        // Files attached to support ticket messages. Installs upgrading past
        // it rely on this patch on MySQL; fresh installs and other drivers
        // get the table from the portable schema sync. Guarded rerun no-op.
        if (!$patcher->tableExists('support_ticket_file')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_file` (
                    `id` BIGINT NOT NULL AUTO_INCREMENT,
                    `token` CHAR(32) NOT NULL,
                    `ticket_id` BIGINT DEFAULT NULL,
                    `message_id` BIGINT DEFAULT NULL,
                    `client_id` BIGINT NOT NULL,
                    `uploader_kind` VARCHAR(10) NOT NULL,
                    `uploader_id` BIGINT NOT NULL,
                    `session_hash` CHAR(64) NOT NULL,
                    `name` VARCHAR(191) NOT NULL,
                    `mime` VARCHAR(100) NOT NULL,
                    `size` INT NOT NULL,
                    `created_at` DATETIME NOT NULL,
                    `attached_at` DATETIME DEFAULT NULL,
                    `removed_at` DATETIME DEFAULT NULL,
                    `removed_by` BIGINT DEFAULT NULL,
                    `removed_reason` VARCHAR(20) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `token` (`token`),
                    KEY `message` (`message_id`),
                    KEY `ticket` (`ticket_id`),
                    KEY `client` (`client_id`),
                    KEY `waiting` (`session_hash`, `message_id`),
                    KEY `uploader` (`uploader_kind`, `uploader_id`, `created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }
}
