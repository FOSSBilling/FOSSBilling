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

enum Driver: string
{
    case PdoMysql = 'pdo_mysql';
    case PdoPgsql = 'pdo_pgsql';
    case PdoSqlite = 'pdo_sqlite';

    public static function tryFromAlias(string $driver): ?self
    {
        $normalized = match ($driver) {
            'mysql', 'mariadb' => 'pdo_mysql',
            'pgsql', 'postgres', 'postgresql' => 'pdo_pgsql',
            'sqlite', 'sqlite3' => 'pdo_sqlite',
            default => $driver,
        };

        return self::tryFrom($normalized);
    }

    public function defaultPort(): ?int
    {
        return match ($this) {
            self::PdoMysql => 3306,
            self::PdoPgsql => 5432,
            self::PdoSqlite => null,
        };
    }

    public function isSqlite(): bool
    {
        return $this === self::PdoSqlite;
    }

    public function isMysql(): bool
    {
        return $this === self::PdoMysql;
    }
}
