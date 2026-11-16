<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\Core\Update\Patch;

use FOSSBilling\Core\Update\Patcher;

class Patch158 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if (!$patcher->tableExists('support_ticket_close_event')) {
            $patcher->executeSql(
                'CREATE TABLE `support_ticket_close_event` (
                    `id` VARCHAR(32) NOT NULL,
                    `ticket_id` BIGINT NOT NULL,
                    `actor` VARCHAR(10) NOT NULL,
                    `actor_id` BIGINT DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `attempted_at` DATETIME DEFAULT NULL,
                    `notification_queued_at` DATETIME DEFAULT NULL,
                    `feedback_queued_at` DATETIME DEFAULT NULL,
                    `delivered_at` DATETIME DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `ticket_close_event_pending_idx` (`delivered_at`, `created_at`),
                    KEY `ticket_close_event_ticket_idx` (`ticket_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
            );
        }
    }
}
