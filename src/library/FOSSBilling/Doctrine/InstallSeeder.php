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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Seeds initial data for a fresh install, on any supported platform, by replaying
 * `install/sql/content.sql` - this class *reuses that same mysqldump-produced file's text* rather
 * than duplicating its data, so the seed data itself never has to be kept in sync between two
 * places. It only rewrites the handful of things that are actually MySQL-dialect: backtick-quoted
 * identifiers, the `NOW()` function, mysqldump's own bookkeeping statements/comments, backslash
 * escapes mysqldump applies that standard-conforming SQL (PostgreSQL, SQLite) does not interpret
 * the same way MySQL does, and bare 0/1 literals for genuinely boolean-typed columns (MySQL has no
 * real boolean type - mysqldump always emits a tinyint's literal integer value, which PostgreSQL's
 * real `boolean` columns reject outright).
 */
final class InstallSeeder
{
    /**
     * Replays install/sql/content.sql's INSERT statements, rewritten to be portable.
     *
     * $entityManager supplies the same metadata {@see SchemaInstaller} used to build the schema in
     * the first place, so the boolean-literal rewrite below can never drift from what a column's
     * real type actually is.
     */
    public static function seedContent(Connection $connection, EntityManagerInterface $entityManager, string $contentSql, \DateTimeInterface $now): void
    {
        $portableSql = self::portableize($contentSql, $now, self::booleanColumnsByTable($entityManager));
        $seededTables = [];
        foreach (self::splitStatements($portableSql) as $statement) {
            $connection->executeStatement($statement);
            if (preg_match('/^INSERT INTO (\w+)/i', $statement, $match) === 1) {
                $seededTables[$match[1]] = true;
            }
        }

        self::resyncPostgresSequences($connection, array_keys($seededTables));
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

    /**
     * @param array<string, list<string>> $booleanColumnsByTable table name => its boolean-mapped column names
     */
    private static function portableize(string $sql, \DateTimeInterface $now, array $booleanColumnsByTable): string
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
        // intact without having to parse and rebuild them. Must happen before the boolean rewrite
        // below, which walks parenthesized VALUES tuples and would otherwise mistake NOW()'s own
        // parentheses for a tuple boundary.
        $sql = str_replace('NOW()', "'" . $now->format('Y-m-d H:i:s') . "'", $sql);

        // mysqldump backslash-escapes string content (\n, \") that standard-conforming string
        // literals (PostgreSQL, SQLite) don't interpret the same way - undo the escaping so the
        // stored text matches what a MySQL install ends up with, rather than storing literal
        // backslash sequences.
        $sql = strtr($sql, ['\\n' => "\n", '\\"' => '"']);

        return self::portableizeBooleans($sql, $booleanColumnsByTable);
    }

    /**
     * @param array<string, list<string>> $booleanColumnsByTable
     */
    private static function portableizeBooleans(string $sql, array $booleanColumnsByTable): string
    {
        // Only rewrites the column positions that are genuinely boolean-mapped for that INSERT's
        // table - never touches a real integer/counter column that happens to also hold a 0 or 1
        // (e.g. `product`.`quantity_in_stock`), and leaves NULL and every other literal alone.
        return preg_replace_callback(
            '/INSERT INTO (\w+) \(([^)]+)\)\s*VALUES\s*(.*?);/is',
            static function (array $matches) use ($booleanColumnsByTable): string {
                [$statement, $table, $columnList, $valuesList] = $matches;
                $booleanColumns = $booleanColumnsByTable[$table] ?? [];
                if ($booleanColumns === []) {
                    return $statement;
                }

                $columns = array_map(trim(...), explode(',', $columnList));
                $booleanIndexes = array_keys(array_intersect($columns, $booleanColumns));
                if ($booleanIndexes === []) {
                    return $statement;
                }

                $rows = array_map(
                    static function (array $fields) use ($booleanIndexes): string {
                        foreach ($booleanIndexes as $index) {
                            $value = trim($fields[$index]);
                            if ($value === '0') {
                                $fields[$index] = 'false';
                            } elseif ($value === '1') {
                                $fields[$index] = 'true';
                            }
                        }

                        return '(' . implode(',', $fields) . ')';
                    },
                    self::splitValueTuples($valuesList),
                );

                return "INSERT INTO {$table} ({$columnList}) VALUES\n" . implode(",\n", $rows) . ';';
            },
            $sql,
        );
    }

    /**
     * @return list<list<string>>
     */
    private static function splitValueTuples(string $valuesList): array
    {
        // Splits a VALUES clause's row tuples into their individual field literals, respecting
        // mysqldump's single-quoted string content - including its `''` escaped-literal-quote
        // convention - so a comma or parenthesis inside seeded text is never mistaken for a tuple
        // or field boundary.
        $rows = [];
        $fields = [];
        $field = '';
        $depth = 0;
        $inString = false;
        $length = strlen($valuesList);

        for ($i = 0; $i < $length; ++$i) {
            $char = $valuesList[$i];

            if ($inString) {
                $field .= $char;
                if ($char === "'") {
                    if (($valuesList[$i + 1] ?? '') === "'") {
                        $field .= "'";
                        ++$i;
                    } else {
                        $inString = false;
                    }
                }

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $field .= $char;
            } elseif ($char === '(') {
                ++$depth;
                if ($depth > 1) {
                    $field .= $char;
                }
            } elseif ($char === ')') {
                --$depth;
                if ($depth === 0) {
                    $fields[] = $field;
                    $rows[] = $fields;
                    $fields = [];
                    $field = '';
                } else {
                    $field .= $char;
                }
            } elseif ($char === ',' && $depth === 1) {
                $fields[] = $field;
                $field = '';
            } elseif ($depth >= 1) {
                $field .= $char;
            }
            // Characters between tuples (commas, whitespace, newlines) at depth 0 are skipped.
        }

        return $rows;
    }

    /**
     * @return array<string, list<string>> table name => its boolean-mapped column names
     */
    private static function booleanColumnsByTable(EntityManagerInterface $entityManager): array
    {
        // Straight from the same entity metadata {@see SchemaInstaller} used to build the schema,
        // so this can never drift from a column's real type.
        $columns = [];
        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $classMetadata) {
            foreach ($classMetadata->getFieldNames() as $fieldName) {
                $mapping = $classMetadata->getFieldMapping($fieldName);
                if ($mapping->type === Types::BOOLEAN) {
                    $columns[$classMetadata->getTableName()][] = $mapping->columnName;
                }
            }
        }

        return $columns;
    }

    /**
     * @param list<string> $tables
     */
    private static function resyncPostgresSequences(Connection $connection, array $tables): void
    {
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return;
        }

        // mysqldump's INSERTs give every seeded row an explicit primary key value. MySQL's
        // AUTO_INCREMENT and SQLite's rowid both auto-advance past an explicit value on insert, so
        // they need nothing further - but a PostgreSQL sequence doesn't track explicit inserts at
        // all; left alone, it stays at 1 while the table already holds ids up to content.sql's
        // highest seeded id, so the next auto-generated insert (seedAdmin()/seedInstallNudge()
        // below, or simply the first row a client creates afterwards) collides with a row
        // content.sql already seeded. Resyncing to MAX(id) is the same step pg_dump's own
        // --data-only restores append as a trailing setval() call.
        foreach ($tables as $table) {
            // NULL for a table whose id isn't backed by a real owned sequence (none of the seeded
            // tables today, but this stays a no-op rather than an error if that ever changes).
            $sequence = $connection->fetchOne('SELECT pg_get_serial_sequence(?, ?)', [$table, 'id']);
            if ($sequence === null) {
                continue;
            }

            $connection->executeStatement(
                "SELECT setval(?, COALESCE((SELECT MAX(id) FROM {$table}), 1), (SELECT MAX(id) FROM {$table}) IS NOT NULL)",
                [$sequence],
            );
        }
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
