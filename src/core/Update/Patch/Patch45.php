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

class Patch45 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // Drop updated_at column from activity tables
        // Activity logs are never meant to be updated, only created
        foreach (['activity_admin_history', 'activity_client_email', 'activity_client_history', 'activity_system'] as $table) {
            if ($patcher->tableHasColumn($table, 'updated_at')) {
                $patcher->executeSql(sprintf('ALTER TABLE `%s` DROP COLUMN `updated_at`;', $patcher->quoteIdentifier($table)));
            }
        }
    }
}
