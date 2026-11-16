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
use FOSSBilling\Core\Doctrine\RowLock;
use FOSSBilling\Core\Utils\Normalizer;

/**
 * Secrets clients attach to tickets (server logins, other credentials).
 *
 * Values are encrypted at rest and never leave through ticket payloads,
 * messages or emails: staff open them one by one (audited, and only with the
 * open_secrets permission). Values vanish when the ticket closes; the audit
 * events survive closing but not ticket deletion.
 */
final class TicketSecrets
{
    public const TABLE = 'support_ticket_secret';
    public const EVENTS = 'support_ticket_secret_event';
    /** Most secrets on one ticket. */
    public const MAX_SECRETS = 10;

    /**
     * Which kinds clients may add ({server, other}) and after how many days
     * untouched secrets expire.
     *
     * @return array{server: bool, other: bool, days: int}
     */
    public static function kinds(\Pimple\Container $di): array
    {
        $config = $di['mod_config']('support');

        return [
            'server' => Normalizer::normalizeBoolean($config['secrets_server'] ?? true, true),
            'other' => Normalizer::normalizeBoolean($config['secrets_other'] ?? true, true),
            'days' => ($config['secrets_days'] ?? 7) === '' ? 7 : max(1, min(90, (int) ($config['secrets_days'] ?? 7))),
        ];
    }

    /**
     * Adds the client's secret to the ticket; returns its id.
     */
    public static function addForClient(\Pimple\Container $di, int $clientId, int $ticketId, string $kind, array $data): array
    {
        $ticket = self::ticket($di, $ticketId, $clientId);
        if ($ticket['status'] === Entity\SupportTicket::STATUS_CLOSED) {
            throw new \FOSSBilling\Core\Exception\InformationException('Secrets cannot be added to a closed ticket.');
        }
        if ($kind !== 'server' && $kind !== 'other') {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose a valid kind of secret.');
        }
        $kinds = self::kinds($di);
        if (!$kinds[$kind]) {
            throw new \FOSSBilling\Core\Exception\InformationException('This kind of secret is turned off.');
        }
        $db = self::db($di);
        if ((int) $db->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ticket_id = ?', [$ticketId]) >= self::MAX_SECRETS) {
            throw new \FOSSBilling\Core\Exception\InformationException('At most :count secrets per ticket.', [':count' => self::MAX_SECRETS]);
        }

        if ($kind === 'server') {
            $fields = [
                'host' => self::text($data['host'] ?? null, 'Host', 255, false),
                'type' => self::text($data['type'] ?? null, 'Type', 10, false),
                'username' => self::text($data['username'] ?? null, 'Username', 255, false),
                'password' => self::text($data['password'] ?? null, 'Password', 1000, false),
                'ssh_key' => self::text($data['ssh_key'] ?? null, 'SSH key', 20000, true),
                'note' => self::text($data['note'] ?? null, 'Note', 2000, true),
            ];
            if ($fields['host'] === '' || $fields['host'] === null) {
                throw new \FOSSBilling\Core\Exception\InformationException('Enter the host.');
            }
            if (!in_array($fields['type'], ['ssh', 'panel', 'other'], true)) {
                throw new \FOSSBilling\Core\Exception\InformationException('Choose SSH, panel or other.');
            }
            if (($fields['password'] === '' || $fields['password'] === null) && ($fields['ssh_key'] === '' || $fields['ssh_key'] === null)) {
                throw new \FOSSBilling\Core\Exception\InformationException('Enter a password or an SSH key.');
            }
            $port = $data['port'] ?? '';
            if (!is_string($port) && !is_int($port)) {
                throw new \FOSSBilling\Core\Exception\InformationException('Enter a valid port.');
            }
            $port = trim((string) $port);
            if ($port !== '' && (filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false)) {
                throw new \FOSSBilling\Core\Exception\InformationException('Enter a valid port.');
            }
            $fields['port'] = $port === '' ? null : (int) $port;
            $typeTitle = ['ssh' => 'SSH', 'panel' => 'Panel', 'other' => 'Other'][$fields['type']];
            $label = mb_substr($typeTitle . ' ' . (string) $fields['host'], 0, 100);
        } else {
            $label = self::text($data['label'] ?? null, 'Label', 100, false);
            $value = self::text($data['value'] ?? null, 'Value', 5000, true);
            if ($label === '' || $label === null || $value === '' || $value === null) {
                throw new \FOSSBilling\Core\Exception\InformationException('Enter a label and a value.');
            }
            $fields = ['label' => $label, 'value' => $value];
            $label = mb_substr((string) $label, 0, 100);
        }

        $encrypted = $di['crypt']->encrypt(json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if (strlen($encrypted) > 65535) {
            throw new \FOSSBilling\Core\Exception\InformationException('This secret is too large.');
        }

        return $db->transactional(function (Connection $db) use ($ticketId, $clientId, $kind, $label, $encrypted): array {
            // Serialize with ticket status updates and recheck after input processing.
            $row = $db->fetchAssociative(
                'SELECT status FROM support_ticket WHERE id = ? AND client_id = ?' . RowLock::suffix($db),
                [$ticketId, $clientId]
            );
            if ($row === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found.');
            }
            if ($row['status'] === Entity\SupportTicket::STATUS_CLOSED) {
                throw new \FOSSBilling\Core\Exception\InformationException('Secrets cannot be added to a closed ticket.');
            }
            if ((int) $db->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE ticket_id = ?', [$ticketId]) >= self::MAX_SECRETS) {
                throw new \FOSSBilling\Core\Exception\InformationException('At most :count secrets per ticket.', [':count' => self::MAX_SECRETS]);
            }
            $db->insert(self::TABLE, [
                'ticket_id' => $ticketId,
                'kind' => $kind,
                'label' => $label,
                'data' => $encrypted,
                'added_by_client_id' => $clientId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return ['id' => (int) $db->lastInsertId()];
        });
    }

    /**
     * The ticket's secrets (metadata only, never values) and their audit
     * history. Staff pass no client id; clients pass their own.
     */
    public static function listForTicket(\Pimple\Container $di, int $ticketId, ?int $clientId = null): array
    {
        self::ticket($di, $ticketId, $clientId);
        $db = self::db($di);

        try {
            $secrets = $db->fetchAllAssociative('SELECT id, kind, label, created_at FROM ' . self::TABLE . ' WHERE ticket_id = ? ORDER BY id', [$ticketId]);
            $events = $db->fetchAllAssociative(
                'SELECT e.action, e.secret_label, e.created_at, a.name AS staff_name FROM ' . self::EVENTS . ' e LEFT JOIN admin a ON a.id = e.admin_id WHERE e.ticket_id = ? ORDER BY e.id',
                [$ticketId]
            );
        } catch (\Throwable) {
            return ['secrets' => [], 'events' => []];
        }

        return [
            'secrets' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'kind' => (string) $row['kind'],
                'label' => (string) $row['label'],
                'created_at' => (string) $row['created_at'],
            ], $secrets),
            'events' => array_map(static fn (array $row): array => [
                'action' => (string) $row['action'],
                'label' => (string) $row['secret_label'],
                'staff_name' => $row['staff_name'] !== null ? (string) $row['staff_name'] : null,
                'created_at' => (string) $row['created_at'],
            ], $events),
        ];
    }

    /** Opens the secret for staff (audited); returns its fields. */
    public static function openForStaff(\Pimple\Container $di, int $id, int $adminId): array
    {
        $row = self::secret($di, $id, null);
        $db = self::db($di);

        return $db->transactional(function (Connection $db) use ($di, $id, $adminId, $row): array {
            self::lockTicket($db, (int) $row['ticket_id']);
            $current = self::secret($di, $id, null);
            $decrypted = $di['crypt']->decrypt((string) $current['data']);
            if ($decrypted === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('This secret cannot be opened.');
            }
            $fields = json_decode($decrypted, true);
            if (!is_array($fields)) {
                throw new \FOSSBilling\Core\Exception\InformationException('This secret cannot be opened.');
            }
            self::event($db, (int) $current['ticket_id'], (string) $current['label'], 'opened', $adminId, null);

            return $fields;
        });
    }

    /** Deletes the secret (audited). Either side may delete: staff or its owner. */
    public static function delete(\Pimple\Container $di, int $id, ?int $adminId, ?int $clientId): void
    {
        $row = self::secret($di, $id, $clientId);
        $db = self::db($di);
        $db->transactional(function (Connection $db) use ($di, $id, $adminId, $clientId, $row): void {
            self::lockTicket($db, (int) $row['ticket_id']);
            $current = self::secret($di, $id, $clientId);
            self::event($db, (int) $current['ticket_id'], (string) $current['label'], 'deleted', $adminId, $clientId);
            $db->delete(self::TABLE, ['id' => $id]);
        });
    }

    private static function lockTicket(Connection $db, int $ticketId): void
    {
        if ($db->fetchOne('SELECT id FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$ticketId]) === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found.');
        }
    }

    /** Purge values, and optionally events, while tolerating tables not yet installed. */
    public static function forgetTicket(Connection $db, int $ticketId, bool $withEvents = false): void
    {
        $schema = $db->createSchemaManager();
        if ($schema->tablesExist([self::TABLE])) {
            $db->delete(self::TABLE, ['ticket_id' => $ticketId]);
        }
        if ($withEvents && $schema->tablesExist([self::EVENTS])) {
            $db->delete(self::EVENTS, ['ticket_id' => $ticketId]);
        }
    }

    /** Deletes untouched secrets older than the keep period (audited). */
    public static function prune(\Pimple\Container $di): int
    {
        $days = self::kinds($di)['days'];
        $db = self::db($di);

        try {
            $rows = $db->fetchAllAssociative('SELECT id, ticket_id, label FROM ' . self::TABLE . ' WHERE created_at < ? LIMIT 500', [date('Y-m-d H:i:s', time() - $days * 86400)]);
        } catch (\Throwable) {
            return 0;
        }
        $pruned = 0;
        foreach ($rows as $row) {
            $db->beginTransaction();

            try {
                self::event($db, (int) $row['ticket_id'], (string) $row['label'], 'expired', null, null);
                $db->delete(self::TABLE, ['id' => (int) $row['id']]);
                $db->commit();
                ++$pruned;
            } catch (\Throwable) {
                if ($db->isTransactionActive()) {
                    $db->rollBack();
                }
            }
        }

        return $pruned;
    }

    private static function event(Connection $db, int $ticketId, string $label, string $action, ?int $adminId, ?int $clientId): void
    {
        $db->insert(self::EVENTS, [
            'ticket_id' => $ticketId,
            'secret_label' => $label,
            'action' => $action,
            'admin_id' => $adminId,
            'client_id' => $clientId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{ticket_id: int, status: string}
     */
    private static function ticket(\Pimple\Container $di, int $ticketId, ?int $clientId): array
    {
        $sql = 'SELECT id AS ticket_id, status FROM support_ticket WHERE id = ? AND client_id > 0';
        $params = [$ticketId];
        if ($clientId !== null) {
            $sql .= ' AND client_id = ?';
            $params[] = $clientId;
        }
        $row = self::db($di)->fetchAssociative($sql, $params);
        if ($row === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found.');
        }

        return ['ticket_id' => (int) $row['ticket_id'], 'status' => (string) $row['status']];
    }

    /**
     * @return array{id: int, ticket_id: int, label: string, data: string}
     */
    private static function secret(\Pimple\Container $di, int $id, ?int $clientId): array
    {
        $sql = 'SELECT s.id, s.ticket_id, s.label, s.data FROM ' . self::TABLE . ' s JOIN support_ticket t ON t.id = s.ticket_id WHERE s.id = ? AND t.client_id > 0';
        $params = [$id];
        if ($clientId !== null) {
            $sql .= ' AND t.client_id = ?';
            $params[] = $clientId;
        }
        $row = self::db($di)->fetchAssociative($sql, $params);
        if ($row === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Secret not found.');
        }

        return ['id' => (int) $row['id'], 'ticket_id' => (int) $row['ticket_id'], 'label' => (string) $row['label'], 'data' => (string) $row['data']];
    }

    /**
     * Trims and validates a text field: UTF-8, no control characters (tabs and
     * newlines only where multiline), at most max characters.
     */
    private static function text(mixed $value, string $field, int $max, bool $multiline): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \FOSSBilling\Core\Exception\InformationException('The :field field must be text.', [':field' => $field]);
        }
        $value = trim($value);
        if (!mb_check_encoding($value, 'UTF-8')) {
            throw new \FOSSBilling\Core\Exception\InformationException('The :field field must be text.', [':field' => $field]);
        }
        // Control characters are rejected; tabs and newlines only where multiline.
        $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/\p{Cc}/u';
        if (preg_match($pattern, $value) === 1) {
            throw new \FOSSBilling\Core\Exception\InformationException('The :field field contains control characters.', [':field' => $field]);
        }
        if (mb_strlen($value) > $max) {
            throw new \FOSSBilling\Core\Exception\InformationException('The :field field must be at most :max characters.', [':field' => $field, ':max' => $max]);
        }

        return $value;
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
