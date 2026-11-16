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

class Patch146 implements PatchInterface
{
    public function apply(Patcher $patcher): void
    {
        if ($patcher->fetchOne('SELECT @@character_set_connection') !== 'utf8mb4') {
            $patcher->logUpdate('warning', 'The database connection uses a legacy character set. Set db.charset to utf8mb4 in config.php to store full Unicode after the schema repair.');
        }

        // Full utf8mb3 -> utf8mb4 migration, normalizing every text column
        // and table default to utf8mb4_unicode_ci (the fresh-install
        // collation). Indexed columns convert only when the wider characters
        // fit the server's index key length; anything else is skipped with a
        // logged warning.
        $patcher->repairDatabaseDefaultCharset();

        foreach ($patcher->legacyUnicodeColumns() as $table => $columns) {
            $patcher->widenLegacyTextColumns($table, $columns, repairTableDefault: true);
        }
    }
}
