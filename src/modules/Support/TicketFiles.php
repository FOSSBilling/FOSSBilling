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
    /** Attached files are kept this long unless the settings say otherwise. */
    public const KEEP_DAYS = 180;
    public const MAX_FILES = 5;
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const UPLOADS_PER_HOUR = 30;
    public const UPLOADS_PER_HOUR_STAFF = 120;
    /** Waiting uploads kept per session before sending is required first. */
    public const MAX_WAITING = 10;
    /** Waiting uploads older than this are deleted. */
    public const WAITING_HOURS = 24;

    /** extension => mime; never svg or html. 'text' means any text/* or known text type. */
    public const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'pdf' => 'application/pdf',
        'txt' => 'text', 'log' => 'text', 'csv' => 'text',
        'zip' => 'application/zip',
        'rar' => 'application/vnd.rar', 'r01' => 'application/vnd.rar', 'r02' => 'application/vnd.rar', 'r03' => 'application/vnd.rar',
        'tar' => 'application/x-tar',
    ];

    public const IMAGES = ['image/jpeg', 'image/png', 'image/gif'];
    public const TEXT_TYPES = ['application/json', 'application/xml', 'text/csv', 'application/x-ndjson'];

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
            'max_mb' => (int) (self::MAX_BYTES / 1024 / 1024),
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
            throw new \FOSSBilling\Core\Exception\InformationException('Each file must be smaller than :size MB.', [':size' => (int) (self::MAX_BYTES / 1024 / 1024)]);
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
        $row = $db->fetchAssociative(
            'SELECT client_id FROM support_ticket_file WHERE token = ? AND session_hash = ? AND message_id IS NULL AND uploader_kind = ? AND uploader_id = ?',
            [$token, self::sessionHash($di), $kind, $uploaderId]
        );
        if ($row === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('File not found.');
        }
        @unlink(self::path((int) $row['client_id'], $token));
        $db->delete('support_ticket_file', ['token' => $token]);
    }

    /**
     * Binds the request's waiting uploads to the saved message. Never throws:
     * an attachment failure must not fail the message save.
     */
    public static function attachFromRequest(\Pimple\Container $di, int $ticketId, bool $staff): int
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

            return self::attach($di, $ticketId, $staff, $tokens);
        } catch (\Throwable $e) {
            try {
                $di['logger']->warning('Support: ticket #{id} attachments failed: {error}', ['id' => $ticketId, 'error' => $e->getMessage()]);
            } catch (\Throwable) {
            }

            return 0;
        }
    }

    /** Binds waiting uploads to the ticket's newest message; returns how many were bound. */
    public static function attach(\Pimple\Container $di, int $ticketId, bool $staff, array $tokens): int
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
        if ($staff) {
            $messageId = $db->fetchOne('SELECT id FROM support_ticket_message WHERE support_ticket_id = ? AND admin_id IS NOT NULL ORDER BY id DESC', [$ticketId]);
        } else {
            $messageId = $db->fetchOne('SELECT id FROM support_ticket_message WHERE support_ticket_id = ? AND admin_id IS NULL AND client_id = ? ORDER BY id DESC', [$ticketId, (int) $clientId]);
        }
        if ($messageId === false) {
            return 0;
        }
        $already = (int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_file WHERE message_id = ? AND removed_at IS NULL', [(int) $messageId]);
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
                $sql .= " AND uploader_kind IN ('client', 'user')";
            }
            $attached += (int) $db->executeStatement($sql, $params);
        }

        return $attached;
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
            file_put_contents(self::path($clientId, $token), (string) $attachment['content']);
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
        $row = $db->fetchAssociative('SELECT client_id FROM support_ticket_file WHERE token = ? AND message_id IS NOT NULL AND removed_at IS NULL', [$token]);
        if ($row === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('File not found.');
        }
        @unlink(self::path((int) $row['client_id'], $token));
        $db->update('support_ticket_file', ['removed_at' => date('Y-m-d H:i:s'), 'removed_by' => $adminId, 'removed_reason' => 'staff'], ['token' => $token]);
        $di['logger']->info('Support: ticket file {token} removed by staff member #{staff}', ['token' => $token, 'staff' => $adminId]);
    }

    /**
     * Deletes expired attached files (soft: the message keeps a "removed"
     * marker) and orphaned rows (hard), with the message missing or never sent.
     */
    public static function prune(\Pimple\Container $di): int
    {
        $db = self::db($di);
        $config = $di['mod_config']('support');
        $rawDays = $config['ticket_files_days'] ?? null;
        $days = $rawDays === '' || $rawDays === null ? self::KEEP_DAYS : max(0, (int) $rawDays);
        $pruned = 0;
        if ($days > 0) {
            $rows = $db->fetchAllAssociative(
                'SELECT token, client_id FROM support_ticket_file WHERE message_id IS NOT NULL AND removed_at IS NULL AND created_at < ? LIMIT 500',
                [date('Y-m-d H:i:s', time() - $days * 86400)]
            );
            foreach ($rows as $row) {
                @unlink(self::path((int) $row['client_id'], (string) $row['token']));
                $pruned += (int) $db->update('support_ticket_file', ['removed_at' => date('Y-m-d H:i:s'), 'removed_reason' => 'expired'], ['token' => (string) $row['token']]);
            }
        }
        $rows = $db->fetchAllAssociative(
            'SELECT f.token, f.client_id FROM support_ticket_file f LEFT JOIN support_ticket_message m ON m.id = f.message_id'
            . ' WHERE (f.message_id IS NOT NULL AND m.id IS NULL) OR (f.message_id IS NULL AND f.created_at < ?) LIMIT 500',
            [date('Y-m-d H:i:s', time() - self::WAITING_HOURS * 3600)]
        );
        foreach ($rows as $row) {
            @unlink(self::path((int) $row['client_id'], (string) $row['token']));
            $pruned += (int) $db->delete('support_ticket_file', ['token' => (string) $row['token']]);
        }

        return $pruned;
    }

    /** Deletes every file of a removed client. */
    public static function forgetClient(Connection $db, int $clientId): void
    {
        try {
            $db->delete('support_ticket_file', ['client_id' => $clientId]);
        } catch (\Throwable) {
        }
        $folder = self::dir() . '/' . $clientId;
        if (is_dir($folder)) {
            foreach (glob($folder . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($folder);
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
