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
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

/**
 * Portable raw-SQL fragment builders for date arithmetic that has to happen in SQL rather than
 * PHP - typically because the amount to add/compare varies per row (e.g. a per-order grace
 * period pulled from a joined table), so it can't be reduced to a single bound parameter computed
 * up front. For date math against a single, call-time-known value (a page filter, a fixed cutoff),
 * compute the boundary in PHP and bind it as a parameter instead - that's simpler and doesn't need
 * this class.
 *
 * Both helpers here take raw SQL fragments (column names, prior GREATEST/MAX expressions),
 * *not* bind parameter values - they're building the query string, not filling it in.
 */
final class SqlExpr
{
    /**
     * Portable "largest of two" expression. MySQL/MariaDB and PostgreSQL both have GREATEST();
     * SQLite doesn't, but its multi-argument MAX() (distinct from the single-argument aggregate
     * MAX()) serves the same purpose.
     *
     * NULL handling differs across platforms if either argument is NULL - MySQL's GREATEST()
     * propagates NULL, PostgreSQL's ignores it, and SQLite's multi-arg MAX() propagates it - so
     * only use this where both arguments are already guaranteed non-null (e.g. wrapped in
     * COALESCE, or literals).
     */
    public static function greatestOfTwo(Connection $connection, string $a, string $b): string
    {
        return $connection->getDatabasePlatform() instanceof SQLitePlatform
            ? "MAX({$a}, {$b})"
            : "GREATEST({$a}, {$b})";
    }

    /**
     * Portable "add N days to a datetime expression" where $daysExpr is itself a SQL fragment
     * (e.g. a column, or a {@see self::greatestOfTwo()} result) rather than a fixed number, so
     * it can vary per row.
     */
    public static function addDays(Connection $connection, string $dateExpr, string $daysExpr): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => "DATE_ADD({$dateExpr}, INTERVAL ({$daysExpr}) DAY)",
            $platform instanceof PostgreSQLPlatform => "({$dateExpr} + make_interval(days => ({$daysExpr})::int))",
            $platform instanceof SQLitePlatform => "datetime({$dateExpr}, '+' || ({$daysExpr}) || ' days')",
            default => throw new \RuntimeException('Unsupported database platform: ' . $platform::class),
        };
    }

    /**
     * Portable "add N hours to a datetime expression", the hour-granularity counterpart to
     * {@see self::addDays()} - see there for when $hoursExpr needs to be a SQL fragment rather
     * than a bound parameter.
     */
    public static function addHours(Connection $connection, string $dateExpr, string $hoursExpr): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => "DATE_ADD({$dateExpr}, INTERVAL ({$hoursExpr}) HOUR)",
            $platform instanceof PostgreSQLPlatform => "({$dateExpr} + make_interval(hours => ({$hoursExpr})::int))",
            $platform instanceof SQLitePlatform => "datetime({$dateExpr}, '+' || ({$hoursExpr}) || ' hours')",
            default => throw new \RuntimeException('Unsupported database platform: ' . $platform::class),
        };
    }

    /**
     * Portable "truncate a datetime expression down to its date portion" (as a `'Y-m-d'` string),
     * for grouping a report by calendar day - a stand-in for MySQL's SUBSTR(dateExpr, 1, 10),
     * which works there only because MySQL's driver returns a DATETIME column as a plain string in
     * the first place. PostgreSQL has no implicit cast from `timestamp` to `text`, so SUBSTR()
     * against a real timestamp column fails outright there.
     */
    public static function dateOnly(Connection $connection, string $dateExpr): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => "DATE({$dateExpr})",
            $platform instanceof PostgreSQLPlatform => "({$dateExpr})::date",
            $platform instanceof SQLitePlatform => "date({$dateExpr})",
            default => throw new \RuntimeException('Unsupported database platform: ' . $platform::class),
        };
    }

    /**
     * Portable "whole calendar days between two datetime expressions" (a minus b, ignoring
     * time-of-day) - a stand-in for MySQL's DATEDIFF(a, b) where the result itself is needed
     * (e.g. returned to the caller), not just compared against a threshold. When you only need
     * to compare against a fixed number of days, prefer computing that boundary once in PHP and
     * binding it as a parameter instead - it's simpler and this isn't needed.
     */
    public static function dateDiffDays(Connection $connection, string $a, string $b): string
    {
        $platform = $connection->getDatabasePlatform();

        return match (true) {
            $platform instanceof AbstractMySQLPlatform => "DATEDIFF({$a}, {$b})",
            $platform instanceof PostgreSQLPlatform => "(({$a})::date - ({$b})::date)",
            $platform instanceof SQLitePlatform => "CAST(julianday(date({$a})) - julianday(date({$b})) AS INTEGER)",
            default => throw new \RuntimeException('Unsupported database platform: ' . $platform::class),
        };
    }
}
