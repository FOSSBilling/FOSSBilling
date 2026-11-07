<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\Doctrine;

use Doctrine\DBAL\Connection;

/**
 * Single entry point for cross-database compatibility.
 *
 * Runtime code must go through here (or DQL/ORM) instead of hand-rolling
 * per-platform SQL.
 */
final class DbCompat
{
    public static function tableExists(Connection $connection, string $table): bool
    {
        return $connection->createSchemaManager()->tablesExist([$table]);
    }

    public static function tableHasColumn(Connection $connection, string $table, string $column): bool
    {
        if (!self::tableExists($connection, $table)) {
            return false;
        }

        foreach ($connection->createSchemaManager()->introspectTableColumnsByUnquotedName($table) as $col) {
            if (strtolower($col->getObjectName()->getIdentifier()->getValue()) === strtolower($column)) {
                return true;
            }
        }

        return false;
    }
}
