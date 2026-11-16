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
use FOSSBilling\Core\Utils\Normalizer;

/**
 * Ticket escalation rules (Support settings > Escalation Rules).
 *
 * A rule matches tickets of the chosen help desks, statuses and priorities
 * (none chosen: any) that got no reply for the set time. It then, in this
 * order: adds a reply (posted by the system and emailed to the client like a
 * staff reply, with [NAME], [FIRSTNAME] and [EMAIL] merge fields), moves the ticket to
 * a help desk, sets its priority and status, assigns it to a staff member
 * (TicketAssign, who is emailed) and emails the chosen staff members
 * (mod_staff_ticket_escalated).
 *
 * The time counts from the ticket's last reply (the system's own replies,
 * such as the autoresponder or a rule's reply, do not count); a rule
 * acts once on a ticket for each such reply (support_escalation_applied keeps
 * the reply it acted on); and it acts only when the time ran out after the
 * rule was made, so tickets already waiting longer when it was made are left
 * alone. A status set by a rule goes through the ticket update, not the close
 * path. The cron runs the rules.
 */
final class TicketEscalation
{
    public const RULES = 'support_escalation_rule';
    public const APPLIED = 'support_escalation_applied';

    public const STATUSES = ['open', 'on_hold', 'closed'];
    /** A year. */
    public const MAX_MINUTES = 525600;
    public const MAX_REPLY = 10000;
    /** Most tickets one rule acts on in one cron run (the rest in the next runs). */
    public const BATCH = 100;

    /**
     * Every rule, by name.
     *
     * @return list<array<string, mixed>>
     */
    public static function rules(\Pimple\Container $di): array
    {
        try {
            $rows = self::db($di)->fetchAllAssociative('SELECT * FROM ' . self::RULES . ' ORDER BY name, id');
        } catch (\Doctrine\DBAL\Exception) {
            return [];
        }

        return array_map(self::toArray(...), $rows);
    }

    /** @return array<string, mixed> */
    public static function get(\Pimple\Container $di, int $id): array
    {
        $row = self::db($di)->fetchAssociative('SELECT * FROM ' . self::RULES . ' WHERE id = ?', [$id]);
        if ($row === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Escalation rule not found');
        }

        return self::toArray($row);
    }

    /**
     * Creates (no `id`) or updates a rule; returns its id.
     */
    public static function save(\Pimple\Container $di, array $data): int
    {
        $db = self::db($di);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new \FOSSBilling\Core\Exception\InformationException('Name the rule (up to 100 characters).');
        }
        $minutes = filter_var($data['minutes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_MINUTES]]);
        if ($minutes === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Set the time without a reply, in minutes (1 to :max).', [':max' => self::MAX_MINUTES]);
        }

        $helpdeskIds = array_map(intval(...), $db->fetchFirstColumn('SELECT id FROM support_helpdesk'));
        $helpdesks = self::ids($data['helpdesks'] ?? []);
        if (array_diff($helpdesks, $helpdeskIds) !== []) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose existing help desks.');
        }
        $statuses = self::words($data['statuses'] ?? [], self::STATUSES, 'Choose ticket statuses.');
        $priorities = self::words($data['priorities'] ?? [], array_keys(TicketPriority::LEVELS), 'Choose priorities.');

        $newHelpdesk = (int) ($data['new_helpdesk_id'] ?? 0);
        if ($newHelpdesk !== 0 && !in_array($newHelpdesk, $helpdeskIds, true)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose an existing help desk.');
        }
        $newStatus = (string) ($data['new_status'] ?? '');
        if ($newStatus !== '' && !in_array($newStatus, self::STATUSES, true)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose a ticket status.');
        }
        $newPriority = strtolower((string) ($data['new_priority'] ?? ''));
        if ($newPriority !== '' && !isset(TicketPriority::LEVELS[$newPriority])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose a priority: Low, Medium or High.');
        }
        $staff = TicketAssign::staff($di);
        $assignTo = (int) ($data['assign_to'] ?? 0);
        if ($assignTo !== 0 && !isset($staff[$assignTo])) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose an active staff member.');
        }
        $notify = self::ids($data['notify'] ?? []);
        if (array_diff($notify, array_keys($staff)) !== []) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose active staff members to email.');
        }
        $reply = trim(str_replace(["\r\n", "\r"], "\n", (string) ($data['reply'] ?? '')));
        if (mb_strlen($reply) > self::MAX_REPLY) {
            throw new \FOSSBilling\Core\Exception\InformationException('The reply can be at most :max characters.', [':max' => self::MAX_REPLY]);
        }
        if ($reply === '' && $newHelpdesk === 0 && $newStatus === '' && $newPriority === '' && $assignTo === 0 && $notify === []) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose at least one action.');
        }

        $row = [
            'name' => $name,
            'active' => Normalizer::normalizeBoolean($data['active'] ?? true, true) ? 1 : 0,
            'helpdesks' => json_encode($helpdesks),
            'statuses' => json_encode($statuses),
            'priorities' => json_encode($priorities),
            'minutes' => $minutes,
            'new_helpdesk_id' => $newHelpdesk ?: null,
            'new_status' => $newStatus,
            'new_priority' => $newPriority,
            'assign_to' => $assignTo ?: null,
            'notify' => json_encode($notify),
            'reply' => $reply,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $id = (int) ($data['id'] ?? 0);
        if ($id > 0) {
            self::get($di, $id);
            $db->update(self::RULES, $row, ['id' => $id]);
            $di['logger']->info('Updated escalation rule #{id}', ['id' => $id]);

            return $id;
        }
        $db->insert(self::RULES, $row + ['created_at' => date('Y-m-d H:i:s')]);
        $id = (int) $db->lastInsertId();
        $di['logger']->info('Created escalation rule #{id}', ['id' => $id]);

        return $id;
    }

    public static function delete(\Pimple\Container $di, int $id): void
    {
        self::get($di, $id);
        $db = self::db($di);
        $db->delete(self::APPLIED, ['rule_id' => $id]);
        $db->delete(self::RULES, ['id' => $id]);
        $di['logger']->info('Deleted escalation rule #{id}', ['id' => $id]);
    }

    /**
     * Runs the active rules (cron). Returns how many times a rule acted on a ticket.
     */
    public static function run(\Pimple\Container $di, ?int $now = null): int
    {
        $now ??= time();
        $acted = 0;
        foreach (self::rules($di) as $rule) {
            if (!$rule['active']) {
                continue;
            }
            foreach (self::due($di, $rule, $now) as $ticket) {
                if (!self::claim($di, (int) $rule['id'], (int) $ticket['id'], (string) $ticket['last_reply'])) {
                    continue;
                }

                try {
                    self::act($di, $rule, (int) $ticket['id']);
                    ++$acted;
                } catch (\Throwable $e) {
                    self::log($di, 'escalation rule "' . $rule['name'] . '" failed on ticket #' . $ticket['id'] . ': ' . $e->getMessage());
                }
            }
        }

        return $acted;
    }

    /** Applied marks of deleted tickets or rules. */
    public static function sweep(\Pimple\Container $di): int
    {
        try {
            return (int) self::db($di)->executeStatement(
                'DELETE FROM ' . self::APPLIED . ' WHERE NOT EXISTS (SELECT 1 FROM support_ticket t WHERE t.id = ' . self::APPLIED . '.ticket_id)'
                . ' OR NOT EXISTS (SELECT 1 FROM ' . self::RULES . ' r WHERE r.id = ' . self::APPLIED . '.rule_id)'
            );
        } catch (\Doctrine\DBAL\Exception) {
            return 0;
        }
    }

    /**
     * Tickets the rule matches whose time without a reply ran out (after the rule was made) and
     * that it did not act on for that reply.
     *
     * @return list<array{id: int, last_reply: string}>
     */
    private static function due(\Pimple\Container $di, array $rule, int $now): array
    {
        $minutes = (int) $rule['minutes'];
        $where = [];
        $params = [];
        $types = [];
        if ($rule['helpdesks'] !== []) {
            $where[] = 't.support_helpdesk_id IN (?)';
            $params[] = $rule['helpdesks'];
            $types[] = ArrayParameterType::INTEGER;
        }
        if ($rule['statuses'] !== []) {
            $where[] = 't.status IN (?)';
            $params[] = $rule['statuses'];
            $types[] = ArrayParameterType::STRING;
        }
        if ($rule['priorities'] !== []) {
            $levels = [];
            foreach ($rule['priorities'] as $level) {
                [$operator, $number] = TicketPriority::filter($level);
                $levels[] = 'COALESCE(t.priority, ' . TicketPriority::MEDIUM . ') ' . $operator . ' ' . $number;
            }
            $where[] = '(' . implode(' OR ', $levels) . ')';
        }
        // The last reply that is not the system's own (autoresponder, a rule's reply).
        $lastReply = '(SELECT MAX(m.created_at) FROM support_ticket_message m LEFT JOIN admin a ON a.id = m.admin_id'
            . ' WHERE m.support_ticket_id = t.id AND (m.admin_id IS NULL OR a.system_name IS NULL))';
        $dueBy = date('Y-m-d H:i:s', $now - $minutes * 60);
        $since = date('Y-m-d H:i:s', strtotime((string) $rule['created_at']) - $minutes * 60);

        $sql = 'SELECT id, last_reply FROM (SELECT t.id, ' . $lastReply . ' AS last_reply FROM support_ticket t'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where)) . ') due'
            . ' WHERE last_reply IS NOT NULL AND last_reply <= ? AND last_reply >= ?'
            . ' AND NOT EXISTS (SELECT 1 FROM ' . self::APPLIED . ' x WHERE x.rule_id = ? AND x.ticket_id = due.id AND x.reply_at = due.last_reply)'
            . ' ORDER BY last_reply, id LIMIT ' . self::BATCH;
        array_push($params, $dueBy, $since, (int) $rule['id']);
        array_push($types, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ParameterType::INTEGER);

        try {
            $rows = self::db($di)->fetchAllAssociative($sql, $params, $types);
        } catch (\Doctrine\DBAL\Exception $e) {
            self::log($di, 'escalation rule "' . $rule['name'] . '" could not list tickets: ' . $e->getMessage());

            return [];
        }

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'last_reply' => (string) $r['last_reply']], $rows);
    }

    /** Marks the rule as acting on the ticket for this reply; false when another run already did. */
    private static function claim(\Pimple\Container $di, int $ruleId, int $ticketId, string $replyAt): bool
    {
        $db = self::db($di);
        $now = date('Y-m-d H:i:s');
        if ($db->executeStatement('UPDATE ' . self::APPLIED . ' SET reply_at = ?, applied_at = ? WHERE rule_id = ? AND ticket_id = ? AND reply_at <> ?', [$replyAt, $now, $ruleId, $ticketId, $replyAt]) === 1) {
            return true;
        }

        try {
            $db->insert(self::APPLIED, ['rule_id' => $ruleId, 'ticket_id' => $ticketId, 'reply_at' => $replyAt, 'applied_at' => $now]);

            return true;
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            return false;
        }
    }

    private static function act(\Pimple\Container $di, array $rule, int $ticketId): void
    {
        $support = $di['mod_service']('support');
        $ticket = $support->getTicketById($ticketId);

        if ($rule['reply'] !== '') {
            $support->ticketReply($ticket, $di['mod_service']('staff')->getCronAdmin(), self::mergeFields($di, $ticketId, $rule['reply']));
        }
        $changes = array_filter([
            'support_helpdesk_id' => $rule['new_helpdesk_id'],
            'priority' => $rule['new_priority'] !== '' ? $rule['new_priority'] : null,
            'status' => $rule['new_status'] !== '' ? $rule['new_status'] : null,
        ], static fn ($value): bool => $value !== null);
        if ($changes !== []) {
            $support->ticketUpdate($ticket, $changes, true);
        }
        if ($rule['assign_to'] !== null) {
            TicketAssign::assign($di, $ticketId, (int) $rule['assign_to'], null);
        }
        foreach ($rule['notify'] as $adminId) {
            self::notify($di, $rule, $ticketId, (int) $adminId);
        }
        $di['logger']->info('Escalation rule "{name}" acted on ticket #{id}', ['name' => $rule['name'], 'id' => $ticketId]);
    }

    /** Reply merge fields: [NAME], [FIRSTNAME] and [EMAIL] of the ticket's sender. */
    private static function mergeFields(\Pimple\Container $di, int $ticketId, string $reply): string
    {
        $row = self::db($di)->fetchAssociative(
            'SELECT c.first_name, c.last_name, c.email, t.author_name, t.author_email FROM support_ticket t LEFT JOIN client c ON c.id = t.client_id WHERE t.id = ?',
            [$ticketId]
        ) ?: [];
        $first = trim((string) ($row['first_name'] ?? ''));
        $name = trim($first . ' ' . (string) ($row['last_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($row['author_name'] ?? ''));
            $first = (string) strtok($name, ' ');
        }
        $email = (string) ($row['email'] ?? '') !== '' ? (string) $row['email'] : (string) ($row['author_email'] ?? '');

        return strtr($reply, ['[NAME]' => $name, '[FIRSTNAME]' => $first, '[EMAIL]' => $email]);
    }

    private static function notify(\Pimple\Container $di, array $rule, int $ticketId, int $adminId): void
    {
        try {
            $db = self::db($di);
            $ticket = $db->fetchAssociative('SELECT t.id, t.subject, t.status, t.priority, h.name AS helpdesk FROM support_ticket t LEFT JOIN support_helpdesk h ON h.id = t.support_helpdesk_id WHERE t.id = ?', [$ticketId]);
            if ($ticket === false || !isset(TicketAssign::staff($di)[$adminId])) {
                return;
            }
            $di['mod_service']('email')->sendTemplate([
                'to_admin' => $adminId,
                'code' => 'mod_staff_ticket_escalated',
                'rule' => ['name' => (string) $rule['name'], 'minutes' => (int) $rule['minutes']],
                'ticket' => [
                    'id' => (int) $ticket['id'],
                    'subject' => (string) $ticket['subject'],
                    'helpdesk' => (string) $ticket['helpdesk'],
                    'status_title' => ['open' => 'Open', 'on_hold' => 'On hold', 'closed' => 'Closed'][(string) $ticket['status']] ?? (string) $ticket['status'],
                    'priority_title' => ucfirst(TicketPriority::level($ticket['priority'])),
                    'url' => $di['url']->adminLink('support/ticket/' . $ticketId),
                ],
            ]);
        } catch (\Throwable $e) {
            self::log($di, 'could not email staff member #' . $adminId . ' about escalated ticket #' . $ticketId . ': ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private static function toArray(array $row): array
    {
        $list = static fn ($json): array => is_array($decoded = json_decode((string) $json, true)) ? array_values($decoded) : [];

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'active' => (int) $row['active'] === 1,
            'helpdesks' => array_map(intval(...), $list($row['helpdesks'])),
            'statuses' => array_map('strval', $list($row['statuses'])),
            'priorities' => array_map('strval', $list($row['priorities'])),
            'minutes' => (int) $row['minutes'],
            'new_helpdesk_id' => $row['new_helpdesk_id'] === null ? null : (int) $row['new_helpdesk_id'],
            'new_status' => (string) $row['new_status'],
            'new_priority' => (string) $row['new_priority'],
            'assign_to' => $row['assign_to'] === null ? null : (int) $row['assign_to'],
            'notify' => array_map(intval(...), $list($row['notify'])),
            'reply' => (string) $row['reply'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return list<int> */
    private static function ids(mixed $ids): array
    {
        $ids = is_array($ids) ? $ids : ($ids === '' || $ids === null ? [] : explode(',', (string) $ids));

        return array_values(array_unique(array_filter(array_map(intval(...), $ids), static fn (int $id): bool => $id > 0)));
    }

    /** @return list<string> */
    private static function words(mixed $words, array $allowed, string $error): array
    {
        $words = is_array($words) ? $words : ($words === '' || $words === null ? [] : explode(',', (string) $words));
        $words = array_values(array_unique(array_map(static fn ($w): string => strtolower(trim((string) $w)), $words)));
        if (array_diff($words, $allowed) !== []) {
            throw new \FOSSBilling\Core\Exception\InformationException($error);
        }

        return $words;
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }

    private static function log(\Pimple\Container $di, string $message): void
    {
        try {
            $di['logger']->warning('Support: {message}', ['message' => $message]);
        } catch (\Throwable) {
        }
    }
}
