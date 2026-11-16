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

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Doctrine\RowLock;

/**
 * The ticket list's bulk actions Pin, Unpin and Close ("With Selected").
 *
 * Pinned tickets stay at the top of the staff ticket list, newest first,
 * whatever page or filter; clients never see pins.
 */
final class TicketBulk
{
    public const PINS = 'support_ticket_pin';
    /** Most tickets one bulk action takes (a list page holds 25 to 100). */
    public const MAX = 100;

    /**
     * The selected ticket ids: positive, unique, at most MAX.
     *
     * @return list<int>
     */
    public static function ids(mixed $ids): array
    {
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        $ids = array_values(array_unique(array_filter(array_map(static fn ($id): int => (int) $id, $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new \FOSSBilling\Core\Exception\InformationException('Select at least one ticket.');
        }
        if (count($ids) > self::MAX) {
            throw new \FOSSBilling\Core\Exception\InformationException('At most :max tickets at once.', [':max' => self::MAX]);
        }

        return $ids;
    }

    /** Pins the tickets that exist; returns how many are pinned now. */
    public static function pin(\Pimple\Container $di, array $ids, ?int $adminId): int
    {
        $db = self::db($di);
        $pinned = 0;
        foreach (array_unique(array_map(intval(...), $ids)) as $id) {
            $pinned += (int) $db->transactional(function (Connection $db) use ($id, $adminId): bool {
                if ($db->fetchOne('SELECT 1 FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [(int) $id]) === false) {
                    return false;
                }
                if ($db->fetchOne('SELECT 1 FROM ' . self::PINS . ' WHERE ticket_id = ?', [(int) $id]) === false) {
                    $db->insert(self::PINS, ['ticket_id' => (int) $id, 'pinned_by' => $adminId, 'pinned_at' => date('Y-m-d H:i:s')]);
                }

                return true;
            });
        }

        return $pinned;
    }

    /** Unpins the tickets; returns how many were pinned. */
    public static function unpin(\Pimple\Container $di, array $ids): int
    {
        return (int) self::db($di)->executeStatement('DELETE FROM ' . self::PINS . ' WHERE ticket_id IN (?)', [$ids], [ArrayParameterType::INTEGER]);
    }

    /**
     * Pinned tickets that still exist, newest first.
     *
     * @return list<int>
     */
    public static function pinnedIds(\Pimple\Container $di): array
    {
        try {
            return array_map(intval(...), self::db($di)->fetchFirstColumn(
                'SELECT p.ticket_id FROM ' . self::PINS . ' p JOIN support_ticket t ON t.id = p.ticket_id ORDER BY p.ticket_id DESC LIMIT 500'
            ));
        } catch (\Throwable) {
            // Table not made yet (or no database): nothing is pinned, and the list still shows.
            return [];
        }
    }

    /** Pins of deleted tickets. */
    public static function sweep(\Pimple\Container $di): int
    {
        try {
            return (int) self::db($di)->executeStatement('DELETE FROM ' . self::PINS . ' WHERE NOT EXISTS (SELECT 1 FROM support_ticket t WHERE t.id = ' . self::PINS . '.ticket_id)');
        } catch (\Doctrine\DBAL\Exception) {
            return 0;
        }
    }

    /**
     * The senders of the tickets (lower case, unique): a guest's address; for a client ticket the
     * authorized user who wrote its first message (support_ticket_author), else the client's.
     *
     * @return list<string>
     */
    public static function senders(\Pimple\Container $di, array $ids): array
    {
        $db = self::db($di);

        try {
            $db->fetchOne('SELECT 1 FROM support_ticket_author WHERE 1 = 0');
            $db->fetchOne('SELECT 1 FROM client_user WHERE 1 = 0');
            $hasAuthors = true;
        } catch (\Doctrine\DBAL\Exception) {
            $hasAuthors = false;
        }
        $user = $hasAuthors
            ? '(SELECT u.email FROM support_ticket_author a JOIN client_user u ON u.id = a.user_id WHERE a.ticket_id = t.id ORDER BY a.message_id LIMIT 1)'
            : 'NULL';
        // Only when the first message is the user's: a later reply by a user does not make them the sender.
        $first = $hasAuthors
            ? ' AND (SELECT MIN(a2.message_id) FROM support_ticket_author a2 WHERE a2.ticket_id = t.id) = (SELECT MIN(m.id) FROM support_ticket_message m WHERE m.support_ticket_id = t.id)'
            : '';
        $rows = $db->fetchFirstColumn(
            "SELECT COALESCE(CASE WHEN t.client_id IS NOT NULL{$first} THEN {$user} END, c.email, t.author_email)"
            . ' FROM support_ticket t LEFT JOIN client c ON c.id = t.client_id WHERE t.id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER]
        );

        return array_values(array_unique(array_filter(array_map(static fn ($e): string => strtolower(trim((string) $e)), $rows))));
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
