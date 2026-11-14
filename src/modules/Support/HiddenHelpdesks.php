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
 * Help desks hidden from clients, as WHMCS's "Hide from clients" department setting.
 *
 * A hidden help desk is left out of the help desks clients and guests choose from
 * when they open a ticket (client and guest helpdesk_get_pairs, so the ticket and
 * order forms), and the client and guest ticket_create refuse it. Everything else
 * stays: staff open and move tickets into it, its clients read and reply to their
 * tickets, and the system opens tickets in it (receipts, privacy requests).
 *
 * Saved on the help desk's page ("Hidden from clients"); the
 * support_helpdesk_hidden table holds the hidden ones.
 */
final class HiddenHelpdesks
{
    public const TABLE = 'support_helpdesk_hidden';

    /**
     * The hidden help desks' ids.
     *
     * @return list<int>
     */
    public static function ids(\Pimple\Container $di): array
    {
        try {
            return array_map(intval(...), self::db($di)->fetchFirstColumn('SELECT helpdesk_id FROM ' . self::TABLE . ' ORDER BY helpdesk_id'));
        } catch (\Throwable) {
            // Table not made yet (update patch not run): nothing is hidden.
            return [];
        }
    }

    public static function isHidden(\Pimple\Container $di, int $helpdeskId): bool
    {
        return in_array($helpdeskId, self::ids($di), true);
    }

    /**
     * The help desks clients and guests may open tickets in: id => name.
     *
     * @param array<int, string> $pairs every help desk
     *
     * @return array<int, string>
     */
    public static function visiblePairs(\Pimple\Container $di, array $pairs): array
    {
        return array_diff_key($pairs, array_flip(self::ids($di)));
    }

    /** Saves a help desk's "Hidden from clients" from its form. */
    public static function save(\Pimple\Container $di, int $helpdeskId, mixed $hidden): void
    {
        $db = self::db($di);
        $hidden = Normalizer::normalizeBoolean($hidden, false);
        if (!$hidden) {
            $db->delete(self::TABLE, ['helpdesk_id' => $helpdeskId]);
        } elseif ($db->fetchOne('SELECT 1 FROM ' . self::TABLE . ' WHERE helpdesk_id = ?', [$helpdeskId]) === false) {
            $db->insert(self::TABLE, ['helpdesk_id' => $helpdeskId, 'hidden_at' => date('Y-m-d H:i:s')]);
        }
    }

    /** A deleted help desk's setting. */
    public static function forget(\Pimple\Container $di, int $helpdeskId): void
    {
        try {
            self::db($di)->delete(self::TABLE, ['helpdesk_id' => $helpdeskId]);
        } catch (\Throwable) {
        }
    }

    /**
     * Help desk list rows (staff) with `hidden` added.
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
