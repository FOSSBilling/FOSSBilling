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

class Patch133 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if ($patcher->fetchOne('SELECT @@character_set_connection') !== 'utf8mb4') {
            $patcher->logUpdate('warning', 'The database connection uses a legacy character set. Set db.charset to utf8mb4 in config.php to store full Unicode after the schema repair.');
        }

        // #4579: repair legacy authored text and defaults for future columns.
        // Only first-party tables are included; absent extension tables are skipped.
        foreach ($patcher->legacyUnicodeColumns() as $table => $columns) {
            $patcher->widenLegacyTextColumns($table, $columns, repairTableDefault: true);
        }
    }
}
