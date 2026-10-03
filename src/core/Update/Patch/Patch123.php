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

class Patch123 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The invoice journal keeps a per-invoice audit trail (see
        // Service::recordJournalEvent()): one row per lifecycle transition
        // with a trimmed snapshot. Fresh installs get the table from entity
        // metadata and the portable sync covers non-MySQL drivers, so this
        // MySQL-only CREATE is just for existing installs. The guard makes
        // reruns a no-op.
        if ($patcher->tableExists('invoice_event')) {
            return;
        }

        $patcher->executeSql('CREATE TABLE `invoice_event` (`id` bigint(20) NOT NULL AUTO_INCREMENT, `invoice_id` bigint(20) DEFAULT NULL, `type` varchar(50) NOT NULL DEFAULT \'updated\', `admin_id` bigint(20) DEFAULT NULL, `client_id` bigint(20) DEFAULT NULL, `snapshot` JSON DEFAULT NULL, `created_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), KEY `invoice_event_invoice_id_idx` (`invoice_id`))');
    }
}
