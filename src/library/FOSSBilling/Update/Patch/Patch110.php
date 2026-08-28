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

class Patch110 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        // These columns were declared int(11) in structure.sql while the primary key
        // column they reference is bigint(20), a width mismatch that predates this
        // patch. Widen them to match so large ids don't overflow the FK column.
        $narrowForeignKeys = [
            ['invoice', 'gateway_id'],
            ['transaction', 'gateway_id'],
            ['email_queue', 'client_id'],
            ['email_queue', 'admin_id'],
        ];

        foreach ($narrowForeignKeys as [$table, $column]) {
            // Match on the base type name, not the int(11) display width: MySQL 8.0.19+
            // deprecates (and 8.4+ drops) integer display widths, so SHOW COLUMNS can
            // report a bare "int" with no parenthesised length on newer servers.
            $type = $patcher->getColumnType($table, $column);
            if ($type !== null && str_starts_with($type, 'int')) {
                $patcher->executeSql(sprintf('ALTER TABLE `%s` MODIFY COLUMN `%s` bigint(20) DEFAULT NULL', $table, $column));
            }
        }
    }
}
