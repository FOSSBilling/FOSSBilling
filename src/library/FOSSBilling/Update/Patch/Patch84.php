<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Update\Patch;

use FOSSBilling\Update\Patcher;

class Patch84 implements PatchInterface
{
    public function getVersion(): int
    {
        return 84;
    }

    public function apply(Patcher $patcher): void
    {
        // Admins can now edit ticket replies; this table snapshots a message's prior content on each edit.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/2317
        if (!$patcher->tableExists('support_ticket_message_history')) {
            $patcher->executeSql('CREATE TABLE `support_ticket_message_history` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `support_ticket_message_id` bigint(20) NOT NULL,
                `admin_id` bigint(20) DEFAULT NULL,
                `content` text,
                `created_at` datetime DEFAULT NULL,
                `updated_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `support_ticket_message_id_idx` (`support_ticket_message_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }
    }
}
