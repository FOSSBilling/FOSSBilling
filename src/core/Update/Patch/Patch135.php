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

class Patch135 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // The activity retention sweep deletes by created_at on every cron run. Without an index each
        // sweep is a full scan of the table.
        foreach (['activity_admin_history', 'activity_client_email', 'activity_client_history', 'activity_system'] as $table) {
            $index = $table . '_created_at_idx';
            if (!$patcher->tableHasIndex($table, $index)) {
                $patcher->executeSql(sprintf('ALTER TABLE `%s` ADD INDEX `%s` (`created_at`)', $table, $index));
            }
        }
    }
}
