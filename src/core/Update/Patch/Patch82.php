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

class Patch82 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Per-staff-group restriction of "sent to staff" email notifications.
        // @see https://github.com/FOSSBilling/FOSSBilling/issues/1247
        if (!$patcher->tableExists('email_template_group')) {
            $patcher->executeSql('CREATE TABLE `email_template_group` (
                `id` bigint(20) NOT NULL AUTO_INCREMENT,
                `email_template_id` bigint(20) NOT NULL,
                `admin_group_id` bigint(20) NOT NULL,
                `created_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email_template_group_unique` (`email_template_id`, `admin_group_id`),
                KEY `email_template_group_template_id_idx` (`email_template_id`),
                KEY `email_template_group_group_id_idx` (`admin_group_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;');
        }

        // Backfill: templates already sent to staff must keep reaching everyone
        // they used to reach, so link them to every staff group that already
        // exists. Templates created after this patch runs are auto-assigned by
        // Email\Service::assignAllGroupsToTemplate() when their row is first created.
        $patcher->executeSql("INSERT INTO email_template_group (email_template_id, admin_group_id, created_at)
            SELECT et.id, ag.id, NOW()
            FROM email_template et
            CROSS JOIN admin_group ag
            WHERE et.action_code IN ('mod_staff_client_order', 'mod_staff_ticket_open', 'mod_staff_ticket_reply', 'mod_staff_ticket_close', 'mod_staff_client_signup')
            AND NOT EXISTS (
                SELECT 1 FROM email_template_group etg
                WHERE etg.email_template_id = et.id AND etg.admin_group_id = ag.id
            )");
    }
}
