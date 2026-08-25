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

/**
 * Seeds initial data for PostgreSQL and SQLite installs. MySQL/MariaDB installs still replay
 * `install/sql/content.sql` almost verbatim via raw PDO (see install.php) - this class instead
 * *reuses that same file's text* rather than duplicating its data, so the seed data itself never
 * has to be kept in sync between two places. It only rewrites the handful of things that are
 * actually MySQL-dialect: backtick-quoted identifiers, the `NOW()` function, mysqldump's own
 * bookkeeping statements/comments, and backslash escapes mysqldump applies that standard-conforming
 * SQL (PostgreSQL, SQLite) does not interpret the same way MySQL does.
 */
final class InstallSeeder
{
    /**
     * Replays install/sql/content.sql's INSERT statements, rewritten to be portable.
     */
    public static function seedContent(Connection $connection, string $contentSql, \DateTimeInterface $now): void
    {
        foreach (self::splitStatements(self::portableize($contentSql, $now)) as $statement) {
            $connection->executeStatement($statement);
        }
    }

    /**
     * Creates the initial admin account and links it to the Super Administrator group (id 1,
     * seeded by {@see self::seedContent()}). Returns the new admin's id.
     */
    public static function seedAdmin(Connection $connection, string $name, string $email, string $passwordHash, ?string $apiToken, \DateTimeInterface $now): int
    {
        $connection->insert('admin', [
            'name' => $name,
            'email' => $email,
            'pass' => $passwordHash,
            'api_token' => $apiToken,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $adminId = (int) $connection->lastInsertId();

        $connection->insert('admin_group_member', [
            'admin_id' => $adminId,
            'admin_group_id' => 1,
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $adminId;
    }

    /**
     * Points the default currency row (id 1, seeded as USD by {@see self::seedContent()}) at the
     * currency the installer's user actually chose.
     */
    public static function setDefaultCurrency(Connection $connection, string $currencyCode): void
    {
        $connection->update('currency', ['code' => $currencyCode], ['id' => 1]);
    }

    /**
     * Records the version the installer ran at, matching what a MySQL install's raw dump replay
     * does after loading content.sql (see install.php's `install()`).
     */
    public static function seedInstallNudge(Connection $connection, string $version, \DateTimeInterface $now): void
    {
        $connection->insert('setting', [
            'param' => 'last_error_reporting_nudge',
            'value' => $version,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);
    }

    private static function portableize(string $sql, \DateTimeInterface $now): string
    {
        $lines = array_filter(
            explode("\n", $sql),
            static function (string $line): bool {
                $trimmed = ltrim($line);

                return $trimmed !== ''
                    && !str_starts_with($trimmed, '#')
                    && !str_starts_with($trimmed, '/*!')
                    && !str_starts_with($trimmed, 'LOCK TABLES')
                    && !str_starts_with($trimmed, 'UNLOCK TABLES');
            },
        );
        $sql = implode("\n", $lines);

        // MySQL identifier quoting; none of the seed data itself contains a backtick character.
        $sql = str_replace('`', '', $sql);

        // A literal, bound-at-seed-time timestamp in place of the MySQL NOW() function. Embedding
        // it as a literal (rather than a bind parameter) keeps the multi-row VALUES(...) lists
        // intact without having to parse and rebuild them.
        $sql = str_replace('NOW()', "'" . $now->format('Y-m-d H:i:s') . "'", $sql);

        // mysqldump backslash-escapes string content (\n, \") that standard-conforming string
        // literals (PostgreSQL, SQLite) don't interpret the same way - undo the escaping so the
        // stored text matches what a MySQL install ends up with, rather than storing literal
        // backslash sequences.
        return strtr($sql, ['\\n' => "\n", '\\"' => '"']);
    }

    /**
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        // Matches install.php's own splitter for this same file - proven correct against it,
        // since content.sql's row data never embeds a bare ";" immediately followed by a newline.
        $statements = preg_split('/\;[\r]*\n/ism', $sql) ?: [];
        $statements = array_map(trim(...), $statements);

        return array_values(array_filter($statements, static fn (string $statement): bool => $statement !== ''));
    }
}
