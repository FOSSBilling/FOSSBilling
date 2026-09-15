<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Portable row-locking for SELECT statements that must serialize concurrent writers against a
 * single row (e.g. "claim this row before acting on it").
 *
 * MySQL, MariaDB, and PostgreSQL all support `SELECT ... FOR UPDATE` identically. SQLite doesn't
 * support the clause at all - but it also doesn't need it: SQLite takes a database-wide write
 * lock for the whole duration of a write transaction, so any SELECT made inside one is already
 * serialized against every other writer. Every call site using this must already run inside an
 * active transaction for that guarantee to hold.
 */
final class RowLock
{
    /**
     * Returns ' FOR UPDATE' for platforms that support it, or an empty string otherwise. Append
     * this to the end of a SELECT statement: `'SELECT ... WHERE ...' . RowLock::suffix($connection)`.
     */
    public static function suffix(Connection $connection): string
    {
        return $connection->getDatabasePlatform() instanceof SQLitePlatform ? '' : ' FOR UPDATE';
    }
}
