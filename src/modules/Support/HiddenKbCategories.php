<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support;

use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Utils\Normalizer;

/**
 * Knowledge base categories hidden from clients.
 *
 * Clients and guests do not see a hidden category or its articles: the knowledge
 * base lists and search leave them out, and its page and its articles' pages are
 * not found. Staff see and edit everything as before.
 *
 * Saved on the category's page ("Hidden from clients"); the
 * support_kb_category_hidden table holds the hidden ones.
 */
final class HiddenKbCategories
{
    public const TABLE = 'support_kb_category_hidden';

    /**
     * The hidden categories' ids.
     *
     * @return list<int>
     */
    public static function ids(\Pimple\Container $di): array
    {
        try {
            return array_map(intval(...), self::db($di)->fetchFirstColumn('SELECT category_id FROM ' . self::TABLE . ' ORDER BY category_id'));
        } catch (\Throwable) {
            // Table not made yet (update patch not run): nothing is hidden.
            return [];
        }
    }

    public static function isHidden(\Pimple\Container $di, ?int $categoryId): bool
    {
        return $categoryId !== null && in_array($categoryId, self::ids($di), true);
    }

    /** Saves a category's "Hidden from clients" from its page. */
    public static function save(\Pimple\Container $di, int $categoryId, mixed $hidden): void
    {
        $db = self::db($di);
        $hidden = Normalizer::normalizeBoolean($hidden, false);
        if (!$hidden) {
            $db->delete(self::TABLE, ['category_id' => $categoryId]);
        } elseif ($db->fetchOne('SELECT 1 FROM ' . self::TABLE . ' WHERE category_id = ?', [$categoryId]) === false) {
            $db->insert(self::TABLE, ['category_id' => $categoryId, 'hidden_at' => date('Y-m-d H:i:s')]);
        }
    }

    /** A deleted category's setting. */
    public static function forget(\Pimple\Container $di, int $categoryId): void
    {
        try {
            self::db($di)->delete(self::TABLE, ['category_id' => $categoryId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Category list rows (staff) with `hidden` added.
     *
     * @param array<mixed> $rows
     *
     * @return array<mixed>
     */
    public static function withFlags(\Pimple\Container $di, array $rows): array
    {
        $hidden = self::ids($di);
        foreach ($rows as $key => $row) {
            if (is_array($row) && isset($row['id'])) {
                $rows[$key]['hidden'] = in_array((int) $row['id'], $hidden, true);
            }
        }

        return $rows;
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
