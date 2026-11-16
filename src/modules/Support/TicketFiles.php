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

use Box\Mod\Client\ClientRowLock;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use FOSSBilling\Core\Doctrine\RowLock;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Files attached to support ticket messages.
 *
 * Uploads land as "waiting" rows bound to the browser session; saving the
 * message attaches them to it. Serving needs the message to exist and not be
 * removed; staff can remove a file (the message keeps a "removed" marker).
 * Old attached files expire through the support settings keep period; files
 * uploaded but never sent are deleted a day later. Clients never see pins.
 */
final class TicketFiles
{
    public const string TABLE = 'support_ticket_file';
    /** Attached files are kept this long unless the settings say otherwise. */
    public const int KEEP_DAYS = 180;
    public const int MAX_FILES = 5;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const int UPLOADS_PER_HOUR = 30;
    public const int UPLOADS_PER_HOUR_STAFF = 120;
    /** Waiting uploads kept per session before sending is required first. */
    public const int MAX_WAITING = 10;
    /** Waiting uploads older than this are deleted. */
    public const int WAITING_HOURS = 24;
    /** Give older unmarked uploads time to finish before checking for orphaned files. */
    public const int LEGACY_ORPHAN_HOURS = 24;

    /** extension => mime; never svg or html. 'text' means any text/* or known text type. */
    public const array TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'txt' => 'text', 'log' => 'text', 'csv' => 'text',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar', 'r01' => 'application/vnd.rar', 'r02' => 'application/vnd.rar', 'r03' => 'application/vnd.rar',
        'tar' => 'application/x-tar',
    ];

    public const array IMAGES = ['image/jpeg', 'image/png', 'image/gif'];
    public const array TEXT_TYPES = ['application/json', 'application/xml', 'text/csv', 'application/x-ndjson'];

    /**
     * The upload form's limits: extensions, accept attribute, per-message and per-file caps.
     */
    public static function rules(): array
    {
        $extensions = array_keys(self::TYPES);

        return [
            'extensions' => $extensions,
            'accept' => '.' . implode(',.', $extensions),
            'max_files' => self::MAX_FILES,
            'max_bytes' => self::MAX_BYTES,
            'max_mb' => self::MAX_BYTES / 1024 / 1024,
        ];
    }

    /** Outside the web root: PATH_UPLOADS/ticket-files, or FOSSBILLING_TICKET_FILES_DIR. */
    public static function dir(): string
    {
        return rtrim((string) (getenv('FOSSBILLING_TICKET_FILES_DIR') ?: PATH_UPLOADS . '/ticket-files'), '/');
    }

    private static function path(int $clientId, string $token): string
    {
        return self::dir() . '/' . $clientId . '/' . $token;
    }

    private static function markerPath(int $clientId, string $token): string
    {
        return self::path($clientId, $token) . '.pending';
    }

    /** Record the lock owner before creating a file that might outlive its database transaction. */
    private static function stageFile(int $clientId, string $token, ?int $ticketId): void
    {
        (new Filesystem())->dumpFile(self::markerPath($clientId, $token), (string) ($ticketId ?? 0));
    }

    /** Waiting files are scoped to the browser session. */
    private static function sessionHash(\Pimple\Container $di): string
    {
        return hash('sha256', 'fossbilling-ticket-files|' . $di['session']->getId());
    }

    /**
     * Uploads a file for the client's ticket (null ticket id: a new ticket
     * being written). Returns {id: token, name, size, size_label}.
     */
    public static function uploadForClient(\Pimple\Container $di, int $clientId, ?int $ticketId, mixed $file): array
    {
        if ($ticketId !== null) {
            $exists = self::db($di)->fetchOne('SELECT 1 FROM support_ticket WHERE id = ? AND client_id = ?', [$ticketId, $clientId]);
            if ($exists === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found');
            }
        }

        return self::store($di, $file, $clientId, $ticketId, 'client', $clientId, self::UPLOADS_PER_HOUR);
    }

    /** Uploads a file for the ticket's staff reply. Returns {id: token, name, size, size_label}. */
    public static function uploadForStaff(\Pimple\Container $di, int $adminId, int $ticketId, mixed $file): array
    {
        $clientId = self::db($di)->fetchOne('SELECT client_id FROM support_ticket WHERE id = ?', [$ticketId]);
        if ($clientId === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found');
        }

        return self::store($di, $file, (int) $clientId, $ticketId, 'admin', $adminId, self::UPLOADS_PER_HOUR_STAFF);
    }

    private static function store(\Pimple\Container $di, mixed $file, int $clientId, ?int $ticketId, string $kind, int $uploaderId, int $perHour): array
    {
        $db = self::db($di);
        $stored = null;

        try {
            return $db->transactional(function () use ($di, $db, $file, $clientId, $ticketId, $kind, $uploaderId, $perHour, &$stored): array {
                if (($clientId < 1 && $kind !== 'admin') || ($clientId > 0 && !ClientRowLock::acquire($db, $clientId))) {
                    throw new \FOSSBilling\Core\Exception\InformationException('Client not found');
                }
                if ($ticketId !== null && !self::lockTicketOwner($db, $ticketId, $clientId)) {
                    throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found');
                }
                $stored = self::storeLocked($di, $file, $clientId, $ticketId, $kind, $uploaderId, $perHour);

                return $stored;
            });
        } catch (\Throwable $e) {
            if ($stored !== null) {
                (new Filesystem())->remove(self::path($clientId, $stored['id']));
            }

            throw $e;
        }
    }

    private static function lockTicketOwner(Connection $db, int $ticketId, int $clientId): bool
    {
        if ($db->getDatabasePlatform() instanceof SQLitePlatform) {
            $db->executeStatement('UPDATE support_ticket SET id = id WHERE id = ?', [$ticketId]);
        }
        if ($clientId === 0) {
            if ($db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $db->executeStatement('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['ticket-file-guest:' . $ticketId]);
            }

            return $db->fetchOne('SELECT id FROM support_ticket WHERE id = ? AND (client_id IS NULL OR client_id = 0)' . RowLock::suffix($db), [$ticketId]) !== false;
        }

        return $db->fetchOne('SELECT id FROM support_ticket WHERE id = ? AND client_id = ?' . RowLock::suffix($db), [$ticketId, $clientId]) !== false;
    }

    private static function storeLocked(\Pimple\Container $di, mixed $file, int $clientId, ?int $ticketId, string $kind, int $uploaderId, int $perHour): array
    {
        $db = self::db($di);
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose a file to attach.');
        }
        $recent = (int) $db->fetchOne(
            'SELECT COUNT(*) FROM support_ticket_file WHERE uploader_kind = ? AND uploader_id = ? AND created_at > ?',
            [$kind, $uploaderId, date('Y-m-d H:i:s', time() - 3600)]
        );
        if ($recent >= $perHour) {
            throw new \FOSSBilling\Core\Exception\InformationException('You attached many files lately. Try again later.');
        }
        $waiting = (int) $db->fetchOne(
            'SELECT COUNT(*) FROM support_ticket_file WHERE session_hash = ? AND message_id IS NULL',
            [self::sessionHash($di)]
        );
        if ($waiting >= self::MAX_WAITING) {
            throw new \FOSSBilling\Core\Exception\InformationException('Send your message first, then attach more files.');
        }

        $size = $file->getSize() ?: 0;
        if ($size < 1 || $size > self::MAX_BYTES) {
            throw new \FOSSBilling\Core\Exception\InformationException('Each file must be smaller than :size MB.', [':size' => self::MAX_BYTES / 1024 / 1024]);
        }
        $name = self::cleanName($file->getClientOriginalName());
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!self::typeFits($ext, $mime)) {
            throw new \FOSSBilling\Core\Exception\InformationException('This file type cannot be attached. Allowed: :types.', [':types' => implode(', ', array_keys(self::TYPES))]);
        }

        $token = bin2hex(random_bytes(16));
        $folder = self::dir() . '/' . $clientId;
        if (!is_dir($folder) && !@mkdir($folder, 0o700, true) && !is_dir($folder)) {
            throw new \FOSSBilling\Core\Exception\InformationException('The file could not be stored.');
        }
        self::stageFile($clientId, $token, $ticketId);
        $file->move($folder, $token);
        @chmod(self::path($clientId, $token), 0o600);

        try {
            $db->insert('support_ticket_file', [
                'token' => $token,
                'ticket_id' => $ticketId,
                'message_id' => null,
                'client_id' => $clientId,
                'uploader_kind' => $kind,
                'uploader_id' => $uploaderId,
                'session_hash' => self::sessionHash($di),
                'name' => $name,
                'mime' => $mime,
                'size' => $size,
                'created_at' => date('Y-m-d H:i:s'),
                'attached_at' => null,
            ]);
        } catch (\Throwable $e) {
            @unlink(self::path($clientId, $token));

            throw $e;
        }

        return ['id' => $token, 'name' => $name, 'size' => $size, 'size_label' => self::sizeLabel($size)];
    }

    /** Discards a waiting upload (the form's remove button). */
    public static function discard(\Pimple\Container $di, string $token, string $kind, int $uploaderId): void
    {
        $db = self::db($di);
        $claimed = $db->executeStatement(
            "UPDATE support_ticket_file SET removed_at = ?, removed_reason = 'orphan_cleanup' WHERE token = ? AND session_hash = ? AND message_id IS NULL AND removed_at IS NULL AND uploader_kind = ? AND uploader_id = ?",
            [date('Y-m-d H:i:s'), $token, self::sessionHash($di), $kind, $uploaderId]
        );
        if ($claimed === 0) {
            throw new \FOSSBilling\Core\Exception\InformationException('File not found.');
        }
        self::cleanupPrunedFiles($di, $db);
    }

    /**
     * Binds the request's waiting uploads to the saved message. Never throws:
     * an attachment failure must not fail the message save.
     */
    public static function attachFromRequest(\Pimple\Container $di, int $ticketId, bool $staff, ?int $messageId = null): int
    {
        if ($ticketId <= 0) {
            return 0;
        }

        try {
            $body = $di['request']->request->all();
            if (!isset($body['ticket_files']) && str_contains((string) $di['request']->headers->get('Content-Type'), 'json')) {
                $json = json_decode((string) $di['request']->getContent(), true);
                $body = is_array($json) ? $json : [];
            }
            $tokens = $body['ticket_files'] ?? null;
            if (!is_array($tokens) || $tokens === []) {
                return 0;
            }

            return self::attach($di, $ticketId, $staff, $tokens, $messageId);
        } catch (\Throwable $e) {
            try {
                $di['logger']->warning('Support: ticket #{id} attachments failed: {error}', ['id' => $ticketId, 'error' => $e->getMessage()]);
            } catch (\Throwable) {
            }

            return 0;
        }
    }

    /** Binds waiting uploads to a specific message, or the newest matching message. */
    public static function attach(\Pimple\Container $di, int $ticketId, bool $staff, array $tokens, ?int $messageId = null): int
    {
        $db = self::db($di);
        $tokens = array_values(array_unique(array_filter(array_map(strval(...), $tokens), static fn (string $token): bool => preg_match('/^[a-f0-9]{32}$/', $token) === 1)));
        $tokens = array_slice($tokens, 0, self::MAX_FILES);
        if ($tokens === []) {
            return 0;
        }
        $clientId = $db->fetchOne('SELECT client_id FROM support_ticket WHERE id = ?', [$ticketId]);
        if ($clientId === false) {
            return 0;
        }

        return $db->transactional(function () use ($di, $db, $ticketId, $clientId, $staff, $tokens, $messageId): int {
            if (!self::lockTicketOwner($db, $ticketId, (int) $clientId)) {
                return 0;
            }
            $sql = 'SELECT id FROM support_ticket_message WHERE support_ticket_id = ?';
            $params = [$ticketId];
            if ($messageId !== null) {
                $sql .= ' AND id = ?';
                $params[] = $messageId;
            }
            if ($staff) {
                $sql .= ' AND admin_id IS NOT NULL';
            } else {
                $sql .= ' AND admin_id IS NULL AND client_id = ?';
                $params[] = (int) $clientId;
            }
            $messageId = $db->fetchOne($sql . ' ORDER BY id DESC' . RowLock::suffix($db), $params);
            if ($messageId === false) {
                return 0;
            }
            $already = count($db->fetchFirstColumn('SELECT id FROM support_ticket_file WHERE message_id = ? AND removed_at IS NULL' . RowLock::suffix($db), [(int) $messageId]));
            $room = self::MAX_FILES - $already;
            if ($room <= 0) {
                return 0;
            }
            $attached = 0;
            foreach (array_slice($tokens, 0, $room) as $token) {
                $sql = 'UPDATE support_ticket_file SET message_id = ?, ticket_id = ?, attached_at = ? WHERE token = ? AND session_hash = ? AND client_id = ? AND message_id IS NULL AND removed_at IS NULL AND (ticket_id IS NULL OR ticket_id = ?)';
                $params = [(int) $messageId, $ticketId, date('Y-m-d H:i:s'), $token, self::sessionHash($di), (int) $clientId, $ticketId];
                if ($staff) {
                    $sql .= ' AND uploader_kind = ? AND uploader_id = (SELECT admin_id FROM support_ticket_message WHERE id = ?)';
                    array_push($params, 'admin', (int) $messageId);
                } else {
                    $sql .= " AND uploader_kind = 'client'";
                }
                $attached += (int) $db->executeStatement($sql, $params);
            }

            return $attached;
        });
    }

    /**
     * Stores mailed attachments on the imported message. Returns the skipped
     * file names (type or size not allowed).
     *
     * @param list<array{name?: string, mime?: string, content?: string}> $attachments
     *
     * @return list<string>
     */
    public static function attachFromMail(\Pimple\Container $di, int $clientId, int $ticketId, int $messageId, array $attachments): array
    {
        $db = self::db($di);
        $paths = [];

        try {
            return $db->transactional(function () use ($db, $clientId, $ticketId, $messageId, $attachments, &$paths): array {
                if (($clientId > 0 && !ClientRowLock::acquire($db, $clientId))
                    || !self::lockTicketOwner($db, $ticketId, $clientId)
                    || $db->fetchOne('SELECT id FROM support_ticket_message WHERE id = ? AND support_ticket_id = ?', [$messageId, $ticketId]) === false) {
                    return array_map(static fn (array $attachment): string => self::cleanName((string) ($attachment['name'] ?? '')), $attachments);
                }

                return self::attachMailLocked($db, $clientId, $ticketId, $messageId, $attachments, $paths);
            });
        } catch (\Throwable $e) {
            (new Filesystem())->remove($paths);

            throw $e;
        }
    }

    private static function attachMailLocked(Connection $db, int $clientId, int $ticketId, int $messageId, array $attachments, array &$paths): array
    {
        $skipped = [];
        $kept = 0;
        foreach ($attachments as $attachment) {
            $name = self::cleanName((string) ($attachment['name'] ?? ''));
            $size = strlen((string) ($attachment['content'] ?? ''));
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) ($attachment['content'] ?? ''));
            if ($kept >= self::MAX_FILES || $size === 0 || $size > self::MAX_BYTES || !self::typeFits($ext, $mime)) {
                $skipped[] = $name !== '' ? $name : 'file';

                continue;
            }
            $token = bin2hex(random_bytes(16));
            $folder = self::dir() . '/' . $clientId;
            if (!is_dir($folder) && !@mkdir($folder, 0o700, true) && !is_dir($folder)) {
                $skipped[] = $name;

                continue;
            }
            self::stageFile($clientId, $token, $ticketId);
            (new Filesystem())->dumpFile(self::path($clientId, $token), (string) $attachment['content']);
            $paths[] = self::path($clientId, $token);
            @chmod(self::path($clientId, $token), 0o600);

            try {
                $db->insert('support_ticket_file', [
                    'token' => $token,
                    'ticket_id' => $ticketId,
                    'message_id' => $messageId,
                    'client_id' => $clientId,
                    'uploader_kind' => 'mail',
                    'uploader_id' => 0,
                    'session_hash' => '',
                    'name' => $name,
                    'mime' => $mime,
                    'size' => $size,
                    'created_at' => date('Y-m-d H:i:s'),
                    'attached_at' => date('Y-m-d H:i:s'),
                ]);
                ++$kept;
            } catch (\Throwable) {
                @unlink(self::path($clientId, $token));
                $skipped[] = $name;
            }
        }

        return $skipped;
    }

    /**
     * Adds ticket_files to each message of the ticket array: {id: token, name,
     * size_label, image, removed, removed_reason}.
     */
    public static function withFiles(\Pimple\Container $di, array $ticket): array
    {
        $messageIds = [];
        foreach ($ticket['messages'] ?? [] as $message) {
            if (isset($message['id'])) {
                $messageIds[] = (int) $message['id'];
            }
        }
        if ($messageIds === [] || !isset($ticket['id'])) {
            return $ticket;
        }

        try {
            $rows = self::db($di)->fetchAllAssociative(
                'SELECT message_id, token, name, size, mime, removed_at, removed_reason FROM support_ticket_file WHERE ticket_id = ? AND message_id IN (?) ORDER BY id',
                [(int) $ticket['id'], $messageIds],
                [\Doctrine\DBAL\ParameterType::INTEGER, ArrayParameterType::INTEGER]
            );
        } catch (\Throwable) {
            return $ticket;
        }
        $byMessage = [];
        foreach ($rows as $row) {
            $removed = $row['removed_at'] !== null;
            $byMessage[(int) $row['message_id']][] = [
                'id' => (string) $row['token'],
                'name' => (string) $row['name'],
                'size_label' => self::sizeLabel((int) $row['size']),
                'image' => in_array((string) $row['mime'], self::IMAGES, true),
                'removed' => $removed,
                'removed_reason' => $removed ? (string) $row['removed_reason'] : null,
            ];
        }
        foreach ($ticket['messages'] as $index => $message) {
            $ticket['messages'][$index]['ticket_files'] = $byMessage[(int) ($message['id'] ?? 0)] ?? [];
        }

        return $ticket;
    }

    /** A served file's row, or null (unknown token, not attached, removed, or file gone). */
    public static function find(\Pimple\Container $di, string $token): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return null;
        }

        try {
            $row = self::db($di)->fetchAssociative('SELECT * FROM support_ticket_file WHERE token = ? AND message_id IS NOT NULL AND removed_at IS NULL', [$token]);
        } catch (\Throwable) {
            return null;
        }
        if ($row === false || !is_file(self::path((int) $row['client_id'], $token))) {
            return null;
        }

        return $row;
    }

    /**
     * How to serve the file: {name, content_type, disposition, path}.
     * Images and PDFs show inline in a sandboxed page; the rest download.
     */
    public static function serve(array $row): array
    {
        $mime = (string) $row['mime'];
        if (str_starts_with($mime, 'text/') || in_array($mime, self::TEXT_TYPES, true)) {
            $contentType = 'text/plain; charset=utf-8';
            $disposition = 'inline';
        } elseif ($mime === 'application/pdf' || in_array($mime, self::IMAGES, true)) {
            $contentType = $mime;
            $disposition = 'inline';
        } else {
            $contentType = 'application/octet-stream';
            $disposition = 'attachment';
        }

        return [
            'name' => (string) $row['name'],
            'content_type' => $contentType,
            'disposition' => $disposition,
            'path' => self::path((int) $row['client_id'], (string) $row['token']),
        ];
    }

    /** Removes the file (staff): the row stays so the message shows it as removed. */
    public static function remove(\Pimple\Container $di, string $token, ?int $adminId): void
    {
        $db = self::db($di);
        $claimed = $db->executeStatement(
            "UPDATE support_ticket_file SET removed_at = ?, removed_by = ?, removed_reason = 'staff_pending' WHERE token = ? AND message_id IS NOT NULL AND removed_at IS NULL",
            [date('Y-m-d H:i:s'), $adminId, $token]
        );
        if ($claimed === 0) {
            throw new \FOSSBilling\Core\Exception\InformationException('File not found.');
        }
        self::cleanupPrunedFiles($di, $db);
        $di['logger']->info('Support: ticket file {token} removed by staff member #{staff}', ['token' => $token, 'staff' => $adminId]);
    }

    /**
     * Deletes expired attached files (soft: the message keeps a "removed"
     * marker) and orphaned rows (hard), with the message missing or never sent.
     */
    public static function prune(\Pimple\Container $di): int
    {
        $db = self::db($di);
        self::cleanupStagedFiles($di, $db);
        self::cleanupDeletedClients($di, $db);
        $config = $di['mod_config']('support');
        $rawDays = $config['ticket_files_days'] ?? null;
        $days = $rawDays === '' || $rawDays === null ? self::KEEP_DAYS : max(0, (int) $rawDays);
        $pruned = 0;
        if ($days > 0) {
            $cutoff = date('Y-m-d H:i:s', time() - $days * 86400);
            $tokens = $db->fetchFirstColumn(
                'SELECT token FROM support_ticket_file WHERE message_id IS NOT NULL AND removed_at IS NULL AND created_at < ? LIMIT 500',
                [$cutoff]
            );
            foreach ($tokens as $token) {
                $pruned += self::executeRetentionClaim($db,
                    "UPDATE support_ticket_file SET removed_at = ?, removed_reason = 'expire_pending' WHERE token = ? AND message_id IS NOT NULL AND removed_at IS NULL AND created_at < ?",
                    [date('Y-m-d H:i:s'), $token, $cutoff]
                );
            }
        }
        $cutoff = date('Y-m-d H:i:s', time() - self::WAITING_HOURS * 3600);
        $tokens = $db->fetchFirstColumn(
            'SELECT f.token FROM support_ticket_file f LEFT JOIN support_ticket_message m ON m.id = f.message_id'
            . " WHERE (f.removed_reason IS NULL OR f.removed_reason NOT IN ('client_deleted', 'expire_pending', 'orphan_cleanup', 'staff_pending'))"
            . ' AND ((f.message_id IS NOT NULL AND m.id IS NULL) OR (f.message_id IS NULL AND f.created_at < ?)) LIMIT 500',
            [$cutoff]
        );
        foreach ($tokens as $token) {
            $pruned += self::executeRetentionClaim($db,
                "UPDATE support_ticket_file SET removed_at = ?, removed_reason = 'orphan_cleanup' WHERE token = ?"
                . " AND (removed_reason IS NULL OR removed_reason NOT IN ('client_deleted', 'expire_pending', 'orphan_cleanup', 'staff_pending'))"
                . ' AND ((message_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM support_ticket_message m WHERE m.id = support_ticket_file.message_id)) OR (message_id IS NULL AND created_at < ?))',
                [date('Y-m-d H:i:s'), $token, $cutoff]
            );
        }
        self::cleanupPrunedFiles($di, $db);

        return $pruned;
    }

    private static function executeRetentionClaim(Connection $db, string $sql, array $params): int
    {
        try {
            return (int) $db->executeStatement($sql, $params);
        } catch (\Doctrine\DBAL\Exception\RetryableException $e) {
            // A failed statement inside an outer transaction must be rolled back by its owner.
            if ($db->isTransactionActive()) {
                throw $e;
            }

            return (int) $db->executeStatement($sql, $params);
        }
    }

    /** Retry committed retention removals without losing their metadata on filesystem failure. */
    private static function cleanupPrunedFiles(\Pimple\Container $di, Connection $db): void
    {
        if ($db->isTransactionActive()) {
            return;
        }
        $filesystem = new Filesystem();
        $rows = $db->fetchAllAssociative("SELECT token, client_id, removed_reason FROM support_ticket_file WHERE removed_reason IN ('expire_pending', 'orphan_cleanup', 'staff_pending') LIMIT 500");
        foreach ($rows as $row) {
            $token = (string) $row['token'];
            $clientId = (int) $row['client_id'];
            $reason = (string) $row['removed_reason'];
            if ($clientId < 0 || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
                $di['logger']->warning('Support: invalid queued ticket file path for client #{client}', ['client' => $clientId]);

                continue;
            }

            try {
                $filesystem->remove(self::path($clientId, $token));
                if ($reason === 'expire_pending' || $reason === 'staff_pending') {
                    $db->update(self::TABLE, ['removed_reason' => $reason === 'staff_pending' ? 'staff' : 'expired'], ['token' => $token, 'removed_reason' => $reason]);
                } else {
                    $db->delete(self::TABLE, ['token' => $token, 'removed_reason' => $reason]);
                }
            } catch (\Throwable $e) {
                $di['logger']->warning('Support: ticket file retention cleanup failed: {message}', ['message' => $e->getMessage()]);
            }
        }
    }

    /** Reconcile files written by transactions that committed, rolled back, or crashed. */
    private static function cleanupStagedFiles(\Pimple\Container $di, Connection $db): void
    {
        if ($db->isTransactionActive() || !(new Filesystem())->exists(self::dir())) {
            return;
        }
        $finder = (new Finder())->files()->in(self::dir())->depth('== 1')->name('/^[a-f0-9]{32}\.pending$/');
        $filesystem = new Filesystem();
        $processed = 0;
        foreach ($finder as $marker) {
            if (++$processed > 500) {
                break;
            }
            $clientName = $marker->getRelativePath();
            if (!ctype_digit($clientName) || (string) (int) $clientName !== $clientName) {
                continue;
            }
            $clientId = (int) $clientName;
            $token = substr($marker->getFilename(), 0, 32);

            try {
                $ticketText = trim($marker->getContents());
                if (!ctype_digit($ticketText) || ($clientId === 0 && (int) $ticketText < 1)) {
                    throw new \RuntimeException('Invalid attachment recovery marker');
                }
                $db->transactional(function () use ($db, $filesystem, $clientId, $token, $ticketText): void {
                    // The owner may commit while we wait for its advisory lock.
                    if ($db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                        $db->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                    }
                    if ($clientId > 0) {
                        ClientRowLock::acquire($db, $clientId);
                    } else {
                        self::lockTicketOwner($db, (int) $ticketText, 0);
                    }
                    $row = $db->fetchOne('SELECT id FROM support_ticket_file WHERE token = ? AND client_id = ?' . RowLock::suffix($db), [$token, $clientId]);
                    if ($row === false) {
                        $filesystem->remove(self::path($clientId, $token));
                    }
                    $filesystem->remove(self::markerPath($clientId, $token));
                });
            } catch (\Throwable $e) {
                $di['logger']->warning('Support: attachment recovery failed: {message}', ['message' => $e->getMessage()]);
            }
        }
    }

    /** Read-only, bounded inventory for an operator to review before deleting legacy files. */
    public static function legacyOrphanInventory(\Pimple\Container $di): array
    {
        $db = self::db($di);
        if ($db->isTransactionActive()) {
            throw new \LogicException('Legacy attachment inventory cannot run inside a transaction.');
        }
        $files = [];
        $filesystem = new Filesystem();
        if ($filesystem->exists(self::dir())) {
            $finder = (new Finder())->files()->in(self::dir())->depth('== 1')->name('/^[a-f0-9]{32}$/')->sortByName();
            foreach ($finder as $file) {
                $client = $file->getRelativePath();
                if ($file->isLink() || !ctype_digit($client) || (string) (int) $client !== $client
                    || $file->getMTime() >= time() - self::LEGACY_ORPHAN_HOURS * 3600
                    || $filesystem->exists(self::markerPath((int) $client, $file->getFilename()))
                    || $db->fetchOne('SELECT id FROM support_ticket_file WHERE token = ?', [$file->getFilename()]) !== false) {
                    continue;
                }
                $files[] = [
                    'client_id' => (int) $client, 'token' => $file->getFilename(),
                    'size' => $file->getSize(), 'mtime' => $file->getMTime(),
                    'sha256' => hash_file('sha256', $file->getPathname()),
                ];
                if (count($files) === 500) {
                    break;
                }
            }
        }

        return ['version' => 1, 'generated_at' => gmdate(DATE_ATOM), 'files' => $files];
    }

    /** Delete only reviewed, unchanged candidates; the legacy writer drain remains mandatory. */
    public static function cleanupLegacyInventory(\Pimple\Container $di, array $inventory, bool $legacyUploadsDrained): int
    {
        if (!$legacyUploadsDrained) {
            throw new \InvalidArgumentException('Legacy attachment writers must be stopped and their requests drained.');
        }
        if (($inventory['version'] ?? null) !== 1 || !is_array($inventory['files'] ?? null) || count($inventory['files']) > 500) {
            throw new \InvalidArgumentException('Invalid legacy attachment inventory.');
        }
        $batch = [];
        foreach ($inventory['files'] as $file) {
            if (!is_array($file) || !is_int($file['client_id'] ?? null) || $file['client_id'] < 0
                || !is_string($file['token'] ?? null) || preg_match('/^[a-f0-9]{32}$/', $file['token']) !== 1
                || !is_int($file['size'] ?? null) || $file['size'] < 0 || !is_int($file['mtime'] ?? null)
                || !is_string($file['sha256'] ?? null) || preg_match('/^[a-f0-9]{64}$/', $file['sha256']) !== 1) {
                throw new \InvalidArgumentException('Invalid legacy attachment inventory entry.');
            }
            $batch[] = [$file['client_id'], $file['token'], $file];
        }
        $db = self::db($di);
        if ($db->isTransactionActive()) {
            throw new \LogicException('Legacy attachment cleanup cannot run inside a transaction.');
        }

        return self::removeLegacyOrphanBatch($db, new Filesystem(), $batch, 500, $di);
    }

    /** Remove unmarked files only after every legacy writer and request has stopped. */
    public static function cleanupLegacyOrphans(\Pimple\Container $di, bool $legacyUploadsDrained): int
    {
        if (!$legacyUploadsDrained) {
            throw new \InvalidArgumentException('Legacy attachment writers must be stopped and their requests drained.');
        }
        $db = self::db($di);
        if ($db->isTransactionActive()) {
            throw new \LogicException('Legacy attachment cleanup cannot run inside a transaction.');
        }
        $filesystem = new Filesystem();
        if (!$filesystem->exists(self::dir())) {
            return 0;
        }
        $cutoff = time() - self::LEGACY_ORPHAN_HOURS * 3600;
        $finder = (new Finder())->files()->in(self::dir())->depth('== 1')->name('/^[a-f0-9]{32}$/');
        $batch = [];
        $removed = 0;
        foreach ($finder as $file) {
            $clientName = $file->getRelativePath();
            if ($file->isLink() || !ctype_digit($clientName) || (string) (int) $clientName !== $clientName || $file->getMTime() >= $cutoff) {
                continue;
            }
            $clientId = (int) $clientName;
            $token = $file->getFilename();
            if ($filesystem->exists(self::markerPath($clientId, $token))) {
                continue;
            }
            $batch[] = [$clientId, $token];
            if (count($batch) === 250) {
                $removed += self::removeLegacyOrphanBatch($db, $filesystem, $batch, 500 - $removed);
                $batch = [];
                if ($removed >= 500) {
                    return $removed;
                }
            }
        }
        if ($batch !== []) {
            $removed += self::removeLegacyOrphanBatch($db, $filesystem, $batch, 500 - $removed);
        }

        return $removed;
    }

    /** @param list<array{0: int, 1: string, 2?: array}> $batch */
    private static function removeLegacyOrphanBatch(Connection $db, Filesystem $filesystem, array $batch, int $limit, ?\Pimple\Container $di = null): int
    {
        $tokens = array_column($batch, 1);
        $existing = array_fill_keys($db->executeQuery(
            'SELECT token FROM support_ticket_file WHERE token IN (?)', [$tokens], [ArrayParameterType::STRING]
        )->fetchFirstColumn(), true);
        $removed = 0;
        foreach ($batch as $entry) {
            [$clientId, $token] = $entry;
            $reviewed = $entry[2] ?? null;
            if (isset($existing[$token])) {
                continue;
            }
            $didRemove = $db->transactional(function () use ($db, $filesystem, $clientId, $token, $reviewed): bool {
                if ($db->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                    $db->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }
                if ($clientId > 0) {
                    ClientRowLock::acquire($db, $clientId);
                } elseif ($db->getDatabasePlatform() instanceof SQLitePlatform) {
                    $db->executeStatement('UPDATE support_ticket_file SET id = id WHERE token = ?', [$token]);
                }
                if ($filesystem->exists(self::markerPath($clientId, $token))
                    || $db->fetchOne('SELECT id FROM support_ticket_file WHERE token = ?' . RowLock::suffix($db), [$token]) !== false) {
                    return false;
                }
                $path = self::path($clientId, $token);
                clearstatcache(true, $path);
                if (is_link($path) || is_link(dirname($path)) || !$filesystem->exists($path)) {
                    return false;
                }
                if ($reviewed !== null && (filesize($path) !== $reviewed['size'] || filemtime($path) !== $reviewed['mtime']
                    || filemtime($path) >= time() - self::LEGACY_ORPHAN_HOURS * 3600
                    || hash_file('sha256', $path) !== $reviewed['sha256'])) {
                    return false;
                }
                $filesystem->remove($path);

                return true;
            });
            if ($didRemove && $di !== null) {
                $di['logger']->info('Removed reviewed legacy attachment {client}/{token}', ['client' => $clientId, 'token' => $token]);
            }
            $removed += (int) $didRemove;
            if ($removed >= $limit) {
                break;
            }
        }

        return $removed;
    }

    /** Queue file removal in the same transaction as client deletion. */
    public static function forgetClient(Connection $db, int $clientId): void
    {
        if (!$db->createSchemaManager()->tablesExist([self::TABLE])) {
            return;
        }
        $db->update(self::TABLE, [
            'ticket_id' => null, 'message_id' => null, 'uploader_kind' => '', 'uploader_id' => 0,
            'session_hash' => '', 'name' => '', 'mime' => '', 'size' => 0,
            'removed_at' => date('Y-m-d H:i:s'), 'removed_by' => null, 'removed_reason' => 'client_deleted',
        ], ['client_id' => $clientId]);
    }

    private static function cleanupDeletedClients(\Pimple\Container $di, Connection $db): void
    {
        // Do not unlink anything while the deletion that queued it can still roll back.
        if ($db->isTransactionActive()) {
            return;
        }
        $filesystem = new Filesystem();
        $rows = $db->fetchAllAssociative("SELECT token, client_id FROM support_ticket_file WHERE removed_reason = 'client_deleted' LIMIT 500");
        foreach ($rows as $row) {
            $token = (string) $row['token'];
            $clientId = (int) $row['client_id'];
            if ($clientId < 1 || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
                $di['logger']->warning('Support: invalid queued ticket file path for client #{client}', ['client' => $clientId]);

                continue;
            }

            try {
                $filesystem->remove(self::path($clientId, $token));
                $db->delete(self::TABLE, ['token' => $token, 'removed_reason' => 'client_deleted']);
            } catch (\Throwable $e) {
                $di['logger']->warning('Support: ticket file cleanup failed: {message}', ['message' => $e->getMessage()]);
            }
        }
    }

    public static function typeFits(string $ext, string $mime): bool
    {
        $expected = self::TYPES[strtolower($ext)] ?? false;
        if ($expected === false) {
            return false;
        }
        if ($expected === 'text') {
            return str_starts_with($mime, 'text/') || in_array($mime, self::TEXT_TYPES, true);
        }

        return in_array($mime, [$expected], true);
    }

    public static function cleanName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        $name = basename($name);
        $name = (string) preg_replace('/[^\p{L}\p{N} ._()\-]+/u', '_', $name);
        $name = (string) preg_replace('/\s+/', ' ', $name);
        $name = trim($name, " .\t");
        if ($name === '') {
            return 'file';
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $base = mb_substr($base, 0, 120 - mb_strlen($ext) - 1);
        $name = $ext !== '' ? $base . '.' . strtolower($ext) : $base;
        if (trim($name, " .\t") === '') {
            return 'file';
        }

        return $name;
    }

    public static function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1) . ' MB';
        }

        return max(1, (int) ceil($bytes / 1024)) . ' KB';
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
