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
 * Tickets assigned to a staff member, shown as "Assigned To". Staff
 * assign a ticket on its page (Manage tab); the assignee gets an email unless
 * they assigned it to themselves. The ticket list shows the assignee and
 * filters by it (assigned to me, to nobody, to someone). Clients never see it.
 *
 * support_ticket_assignment holds one row per assigned ticket; a ticket without
 * a row, or whose staff member was deleted or deactivated, is unassigned.
 */
final class TicketAssign
{
    public const TABLE = 'support_ticket_assignment';
    /** Most ticket ids an assignee filter passes to the list query. */
    public const MAX_FILTER = 5000;

    /**
     * Staff members a ticket can be assigned to: active, not the system's cron
     * account, by name.
     *
     * @return array<int, string> id => name
     */
    public static function staff(\Pimple\Container $di): array
    {
        $rows = self::db($di)->fetchAllKeyValue("SELECT id, name FROM admin WHERE status = 'active' AND system_name IS NULL ORDER BY name ASC, id ASC");
        $staff = [];
        foreach ($rows as $id => $name) {
            $staff[(int) $id] = trim((string) $name) !== '' ? (string) $name : '#' . $id;
        }

        return $staff;
    }

    /**
     * Assigns the ticket to a staff member (0 or null: nobody). Emails the new
     * assignee unless they assigned it to themselves ($byAdminId). Returns
     * whether the assignee changed.
     */
    public static function assign(\Pimple\Container $di, int $ticketId, ?int $adminId, ?int $byAdminId, bool $notify = true): bool
    {
        $adminId = (int) $adminId > 0 ? (int) $adminId : null;
        $db = self::db($di);
        if ($adminId !== null && !isset(self::staff($di)[$adminId])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose an active staff member.');
        }
        $changed = $db->transactional(function (Connection $db) use ($di, $ticketId, $adminId, $byAdminId): bool {
            if ($db->fetchOne('SELECT 1 FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$ticketId]) === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found');
            }
            if (self::assignee($di, $ticketId) === $adminId) {
                return false;
            }
            if ($adminId === null) {
                $db->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE ticket_id = ?', [$ticketId]);
            } else {
                $row = ['admin_id' => $adminId, 'assigned_by' => $byAdminId, 'assigned_at' => date('Y-m-d H:i:s')];
                if ($db->update(self::TABLE, $row, ['ticket_id' => $ticketId]) === 0) {
                    $db->insert(self::TABLE, ['ticket_id' => $ticketId] + $row);
                }
            }

            return true;
        });
        if (!$changed) {
            return false;
        }
        if ($adminId === null) {
            $di['logger']->info('Unassigned ticket #{id}', ['id' => $ticketId]);
        } else {
            $di['logger']->info('Assigned ticket #{id} to staff member #{staff}', ['id' => $ticketId, 'staff' => $adminId]);
            if ($notify && $adminId !== $byAdminId) {
                self::notify($di, $ticketId, $adminId, $byAdminId);
            }
        }

        return true;
    }

    /** The ticket's assignee (an active staff member), or null. */
    public static function assignee(\Pimple\Container $di, int $ticketId): ?int
    {
        return self::assignees($di, [$ticketId])[$ticketId]['id'] ?? null;
    }

    /**
     * The assignees of the tickets that have one.
     *
     * @param list<int> $ticketIds
     *
     * @return array<int, array{id: int, name: string}> ticket id => assignee
     */
    public static function assignees(\Pimple\Container $di, array $ticketIds): array
    {
        $ticketIds = array_values(array_filter(array_map(intval(...), $ticketIds), static fn (int $id): bool => $id > 0));
        if ($ticketIds === []) {
            return [];
        }

        try {
            $rows = self::db($di)->fetchAllAssociative(
                'SELECT a.ticket_id, a.admin_id, ad.name FROM ' . self::TABLE . ' a'
                . " JOIN admin ad ON ad.id = a.admin_id AND ad.status = 'active' AND ad.system_name IS NULL"
                . ' WHERE a.ticket_id IN (?)',
                [$ticketIds],
                [ArrayParameterType::INTEGER]
            );
        } catch (\Throwable) {
            // Table not made yet (or no database): nothing is assigned, and the pages still show.
            return [];
        }
        $assignees = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            $assignees[(int) $row['ticket_id']] = ['id' => (int) $row['admin_id'], 'name' => $name !== '' ? $name : '#' . $row['admin_id']];
        }

        return $assignees;
    }

    /**
     * The ticket list's assignee filter: 'me' (the signed-in staff member),
     * 'none', 'any' or a staff member's id. Returns the ids the list keeps
     * (`in`) or leaves out (`not_in`), or null for no filter.
     *
     * @return array{0: 'in'|'not_in', 1: list<int>}|null
     */
    public static function listFilter(\Pimple\Container $di, mixed $filter, int $meId): ?array
    {
        $filter = strtolower(trim((string) $filter));
        if ($filter === '') {
            return null;
        }
        $active = ' FROM ' . self::TABLE . " a JOIN admin ad ON ad.id = a.admin_id AND ad.status = 'active' AND ad.system_name IS NULL";
        $db = self::db($di);

        try {
            $ids = match (true) {
                $filter === 'none', $filter === 'any' => $db->fetchFirstColumn('SELECT a.ticket_id' . $active . ' ORDER BY a.ticket_id DESC LIMIT ' . self::MAX_FILTER),
                $filter === 'me' => $db->fetchFirstColumn('SELECT a.ticket_id' . $active . ' WHERE a.admin_id = ? ORDER BY a.ticket_id DESC LIMIT ' . self::MAX_FILTER, [$meId]),
                ctype_digit($filter) => $db->fetchFirstColumn('SELECT a.ticket_id' . $active . ' WHERE a.admin_id = ? ORDER BY a.ticket_id DESC LIMIT ' . self::MAX_FILTER, [(int) $filter]),
                default => throw new \FOSSBilling\Core\Exception\InformationException('Unknown assignee filter.'),
            };
        } catch (\Doctrine\DBAL\Exception) {
            $ids = [];
        }
        $ids = array_map(intval(...), $ids);

        return $filter === 'none' ? ['not_in', $ids] : ['in', $ids];
    }

    /** Assignments of deleted tickets. */
    public static function sweep(\Pimple\Container $di): int
    {
        try {
            return (int) self::db($di)->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE NOT EXISTS (SELECT 1 FROM support_ticket t WHERE t.id = ' . self::TABLE . '.ticket_id)');
        } catch (\Doctrine\DBAL\Exception) {
            return 0;
        }
    }

    /** Emails the assignee (template mod_staff_ticket_assigned); a failure is logged, not thrown. */
    private static function notify(\Pimple\Container $di, int $ticketId, int $adminId, ?int $byAdminId): void
    {
        try {
            $db = self::db($di);
            $ticket = $db->fetchAssociative('SELECT id, subject, status, priority, support_helpdesk_id FROM support_ticket WHERE id = ?', [$ticketId]);
            if ($ticket === false) {
                return;
            }
            $helpdesk = $db->fetchOne('SELECT name FROM support_helpdesk WHERE id = ?', [(int) $ticket['support_helpdesk_id']]);
            $by = $byAdminId !== null ? $db->fetchOne('SELECT name FROM admin WHERE id = ?', [$byAdminId]) : false;
            $di['mod_service']('email')->sendTemplate([
                'to_admin' => $adminId,
                'code' => 'mod_staff_ticket_assigned',
                'ticket' => [
                    'id' => (int) $ticket['id'],
                    'subject' => (string) $ticket['subject'],
                    'status' => (string) $ticket['status'],
                    'status_title' => ['open' => 'Open', 'on_hold' => 'On hold', 'closed' => 'Closed'][(string) $ticket['status']] ?? (string) $ticket['status'],
                    'priority' => TicketPriority::level($ticket['priority']),
                    'priority_title' => ucfirst(TicketPriority::level($ticket['priority'])),
                    'helpdesk' => $helpdesk === false ? '' : (string) $helpdesk,
                    'url' => $di['url']->adminLink('support/ticket/' . $ticketId),
                ],
                'assigned_by' => $by === false ? '' : (string) $by,
            ]);
        } catch (\Throwable $e) {
            try {
                $di['logger']->warning('Support: could not email the assignee of ticket #{id}: {error}', ['id' => $ticketId, 'error' => $e->getMessage()]);
            } catch (\Throwable) {
            }
        }
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
