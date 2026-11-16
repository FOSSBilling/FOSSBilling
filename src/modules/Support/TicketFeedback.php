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
use FOSSBilling\Core\Utils\Normalizer;

/**
 * Reply ratings and closing feedback.
 *
 * Clients rate staff replies 1-5 stars; when a ticket closes, the client gets
 * an email link to rate each staff member who replied 1-10 and to comment on
 * how to improve. A rule acts once per reply; reopening and closing resends
 * the same link until feedback is given.
 */
final class TicketFeedback
{
    public const RATINGS = 'support_ticket_message_rating';
    public const FEEDBACK = 'support_ticket_feedback';
    public const REQUESTS = 'support_ticket_feedback_request';
    public const HELPDESKS = 'support_helpdesk_feedback';

    public const MAX_STARS = 5;
    public const MAX_SCORE = 10;
    public const LOW_STARS = 2;
    public const LOW_SCORE = 4;
    public const MAX_COMMENT = 5000;

    /** Report periods in days (all: everything). */
    public const PERIODS = ['30' => 30, '90' => 90, '365' => 365, 'all' => 0];

    /** Whether clients may rate replies (support setting, on by default). */
    public static function ratingsOn(\Pimple\Container $di): bool
    {
        try {
            $config = $di['mod_config']('support');

            return Normalizer::normalizeBoolean($config['ticket_rating'] ?? true, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Whether the help desk asks for closing feedback (on when unset). */
    public static function helpdeskOn(\Pimple\Container $di, int $helpdeskId, bool $throwOnFailure = false): bool
    {
        try {
            $row = self::db($di)->fetchOne('SELECT feedback_request FROM ' . self::HELPDESKS . ' WHERE helpdesk_id = ?', [$helpdeskId]);
        } catch (\Throwable $e) {
            if ($throwOnFailure) {
                throw $e;
            }

            return true;
        }

        return $row === false || (int) $row === 1;
    }

    /** Saves a help desk's closing-feedback switch. */
    public static function saveHelpdesk(\Pimple\Container $di, int $helpdeskId, mixed $on): void
    {
        self::withTables($di, function () use ($di, $helpdeskId, $on): void {
            $db = self::db($di);
            $row = ['feedback_request' => Normalizer::normalizeBoolean($on, true) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
            if ($db->update(self::HELPDESKS, $row, ['helpdesk_id' => $helpdeskId]) === 0
                && $db->fetchOne('SELECT 1 FROM ' . self::HELPDESKS . ' WHERE helpdesk_id = ?', [$helpdeskId]) === false) {
                $db->insert(self::HELPDESKS, ['helpdesk_id' => $helpdeskId] + $row);
            }
        });
    }

    /** A deleted help desk's switch. */
    public static function deleteHelpdesk(\Pimple\Container $di, int $helpdeskId): void
    {
        try {
            self::db($di)->delete(self::HELPDESKS, ['helpdesk_id' => $helpdeskId]);
        } catch (\Throwable) {
        }
    }

    /** Rates a staff reply 1-5 stars; one rating per reply. */
    public static function rate(\Pimple\Container $di, int $ticketId, int $messageId, mixed $stars, string $kind, int $byId, ?string $ip): void
    {
        if (!self::ratingsOn($di)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Reply ratings are switched off.');
        }
        $stars = filter_var($stars, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_STARS]]);
        if ($stars === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('Choose from 1 to :max stars.', [':max' => self::MAX_STARS]);
        }
        $db = self::db($di);
        $adminId = $db->transactional(function (Connection $db) use ($ticketId, $messageId, $stars, $kind, $byId, $ip): int {
            // Merges take the same ticket lock before moving replies and ratings.
            if ($db->fetchOne('SELECT id FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$ticketId]) === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found.');
            }
            $adminId = $db->fetchOne('SELECT admin_id FROM support_ticket_message WHERE id = ? AND support_ticket_id = ?', [$messageId, $ticketId]);
            if ($adminId === false || $adminId === null) {
                throw new \FOSSBilling\Core\Exception\InformationException('Only staff replies of this ticket can be rated.');
            }

            try {
                $db->insert(self::RATINGS, [
                    'message_id' => $messageId,
                    'ticket_id' => $ticketId,
                    'admin_id' => (int) $adminId,
                    'rating' => $stars,
                    'rated_by_kind' => $kind === 'guest' ? 'guest' : 'client',
                    'rated_by_id' => $byId,
                    'ip' => mb_substr((string) $ip, 0, 45),
                    'rated_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                throw new \FOSSBilling\Core\Exception\InformationException('This reply was already rated.');
            }

            return (int) $adminId;
        });
        $di['logger']->info('Support: reply #{message} of ticket #{ticket} rated {stars} stars', ['message' => $messageId, 'ticket' => $ticketId, 'stars' => $stars]);
        if ($stars <= self::LOW_STARS) {
            try {
                $di['logger']->warning('Support: {stars}-star rating of staff member #{staff} on ticket #{ticket}', ['stars' => $stars, 'staff' => (int) $adminId, 'ticket' => $ticketId]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * The ticket's message ratings: message id => stars.
     *
     * @return array<int, int>
     */
    public static function ratings(\Pimple\Container $di, int $ticketId): array
    {
        try {
            $rows = self::db($di)->fetchAllKeyValue('SELECT message_id, rating FROM ' . self::RATINGS . ' WHERE ticket_id = ?', [$ticketId]);
        } catch (\Throwable) {
            return [];
        }

        return array_map(intval(...), $rows);
    }

    /**
     * Asks for closing feedback once the ticket closed (resending the same
     * link when it closes again). Delivery can request errors for retry.
     */
    public static function afterClose(\Pimple\Container $di, int $ticketId, bool $throwOnFailure = false): void
    {
        try {
            $ticket = self::db($di)->fetchAssociative('SELECT id, subject, status, client_id, author_name, author_email, access_hash, support_helpdesk_id FROM support_ticket WHERE id = ?', [$ticketId]);
            if ($ticket === false || (string) $ticket['status'] !== Entity\SupportTicket::STATUS_CLOSED || !self::helpdeskOn($di, (int) $ticket['support_helpdesk_id'], $throwOnFailure)) {
                return;
            }
            if (self::db($di)->fetchOne('SELECT 1 FROM ' . self::FEEDBACK . ' WHERE ticket_id = ?', [$ticketId]) !== false) {
                return;
            }
            $token = self::db($di)->fetchOne('SELECT token FROM ' . self::REQUESTS . ' WHERE ticket_id = ?', [$ticketId]);
            if ($token === false) {
                $token = bin2hex(random_bytes(32));
                self::db($di)->insert(self::REQUESTS, ['ticket_id' => $ticketId, 'token' => $token, 'sent_at' => date('Y-m-d H:i:s'), 'answered_at' => null]);
            } else {
                self::db($di)->update(self::REQUESTS, ['sent_at' => date('Y-m-d H:i:s')], ['ticket_id' => $ticketId]);
            }

            $email = [
                'code' => 'mod_support_ticket_feedback_request',
                'ticket' => ['id' => (int) $ticket['id'], 'subject' => (string) $ticket['subject']],
                'feedback_url' => $di['url']->link('support/feedback/' . $token),
            ];
            if ((int) ($ticket['client_id'] ?? 0) > 0) {
                $email['to_client'] = (int) $ticket['client_id'];
            } elseif ((string) ($ticket['access_hash'] ?? '') !== '' && (string) ($ticket['author_email'] ?? '') !== '') {
                $email['to'] = (string) $ticket['author_email'];
                $email['to_name'] = (string) $ticket['author_name'];
                $email['name'] = (string) $ticket['author_name'];
            } else {
                return;
            }
            $di['mod_service']('email')->sendTemplate($email);
        } catch (\Throwable $e) {
            try {
                $di['logger']->warning('Support: closing feedback for ticket #{id} failed: {error}', ['id' => $ticketId, 'error' => $e->getMessage()]);
            } catch (\Throwable) {
            }
            if ($throwOnFailure) {
                throw $e;
            }
        }
    }

    /** The ticket's open feedback link, or null (answered, never asked, or gone). */
    public static function openLink(\Pimple\Container $di, int $ticketId): ?string
    {
        try {
            $token = self::db($di)->fetchOne('SELECT token FROM ' . self::REQUESTS . ' WHERE ticket_id = ? AND answered_at IS NULL', [$ticketId]);
        } catch (\Throwable) {
            return null;
        }
        if ($token === false || $token === null) {
            return null;
        }

        return $di['url']->link('support/feedback/' . $token);
    }

    /**
     * The feedback page: ticket summary, involved staff and whether the form,
     * an already-closed notice or the done state shows.
     *
     * @return array{ticket: array, staff: array<int, string>, state: string}
     */
    public static function page(\Pimple\Container $di, string $token): array
    {
        $ticket = self::ticketForToken($di, $token);
        $ticketId = (int) $ticket['id'];
        $db = self::db($di);
        $times = $db->fetchNumeric('SELECT MIN(created_at), MAX(created_at) FROM support_ticket_message WHERE support_ticket_id = ?', [$ticketId]) ?: [null, null];
        $opened = (string) ($ticket['created_at'] !== '' ? $ticket['created_at'] : ($times[0] ?? ''));
        $last = (string) ($times[1] ?? $opened);
        $seconds = max(0, strtotime($last) - strtotime($opened));
        $state = 'form';
        if ((string) $ticket['status'] !== Entity\SupportTicket::STATUS_CLOSED) {
            $state = 'open';
        } elseif ($db->fetchOne('SELECT 1 FROM ' . self::FEEDBACK . ' WHERE ticket_id = ?', [$ticketId]) !== false) {
            $state = 'done';
        }

        return [
            'ticket' => [
                'id' => $ticketId,
                'subject' => (string) $ticket['subject'],
                'opened_at' => $opened,
                'last_reply_at' => $last,
                'duration' => ['days' => (int) floor($seconds / 86400), 'hours' => (int) floor($seconds % 86400 / 3600), 'minutes' => (int) floor($seconds % 3600 / 60)],
            ],
            'staff' => self::staffInvolved($di, $ticketId),
            'state' => $state,
        ];
    }

    /**
     * Records the page's scores and comments. Scores every involved staff
     * member 1-10 (or only the generic comment when nobody replied).
     */
    public static function submit(\Pimple\Container $di, string $token, mixed $scores, mixed $comments, ?string $ip): void
    {
        $ticket = self::ticketForToken($di, $token);
        $ticketId = (int) $ticket['id'];
        if ((string) $ticket['status'] !== Entity\SupportTicket::STATUS_CLOSED) {
            throw new \FOSSBilling\Core\Exception\InformationException('Feedback can be given once the ticket is closed.');
        }
        $scores = is_array($scores) ? $scores : [];
        $comments = is_array($comments) ? $comments : [];
        $staff = self::staffInvolved($di, $ticketId);
        $rows = [];
        foreach ($staff as $adminId => $name) {
            $score = filter_var($scores[$adminId] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_SCORE]]);
            if ($score === false) {
                throw new \FOSSBilling\Core\Exception\InformationException('Rate :name from 1 to :max.', [':name' => $name, ':max' => self::MAX_SCORE]);
            }
            $rows[] = ['admin_id' => $adminId, 'rating' => $score, 'comments' => self::comment($comments[$adminId] ?? '')];
        }
        $generic = self::comment($comments['generic'] ?? '');
        if ($generic !== '' || $rows === []) {
            if ($generic === '') {
                throw new \FOSSBilling\Core\Exception\InformationException('Tell us how we can improve.');
            }
            $rows[] = ['admin_id' => null, 'rating' => null, 'comments' => $generic];
        }

        $db = self::db($di);
        $db->beginTransaction();

        try {
            $status = $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?' . RowLock::suffix($db), [$ticketId]);
            if ($status !== Entity\SupportTicket::STATUS_CLOSED) {
                throw new \FOSSBilling\Core\Exception\InformationException('Feedback can be given once the ticket is closed.');
            }
            if ($db->executeStatement('UPDATE ' . self::REQUESTS . ' SET answered_at = ? WHERE ticket_id = ? AND answered_at IS NULL', [date('Y-m-d H:i:s'), $ticketId]) !== 1
                || $db->fetchOne('SELECT 1 FROM ' . self::FEEDBACK . ' WHERE ticket_id = ?', [$ticketId]) !== false) {
                $db->rollBack();

                throw new \FOSSBilling\Core\Exception\InformationException('Feedback for this ticket was already given. Thank you.');
            }
            foreach ($rows as $row) {
                $db->insert(self::FEEDBACK, $row + ['ticket_id' => $ticketId, 'ip' => mb_substr((string) $ip, 0, 45), 'created_at' => date('Y-m-d H:i:s')]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }

            throw $e;
        }
        $di['logger']->info('Support: feedback given on ticket #{id}', ['id' => $ticketId]);
        $lowest = null;
        foreach ($rows as $row) {
            if ($row['rating'] !== null && ($lowest === null || $row['rating'] < $lowest)) {
                $lowest = $row['rating'];
            }
        }
        if ($lowest !== null && $lowest <= self::LOW_SCORE) {
            try {
                $di['logger']->warning('Support: low feedback score on ticket #{id}', ['id' => $ticketId]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * The ticket's closing feedback for the staff page, or null.
     */
    public static function forTicket(\Pimple\Container $di, int $ticketId): ?array
    {
        try {
            $rows = self::db($di)->fetchAllAssociative(
                'SELECT f.admin_id, f.rating, f.comments, f.created_at, a.name FROM ' . self::FEEDBACK . ' f LEFT JOIN admin a ON a.id = f.admin_id WHERE f.ticket_id = ? ORDER BY f.id',
                [$ticketId]
            );
        } catch (\Throwable) {
            return null;
        }
        if ($rows === []) {
            return null;
        }
        $staff = [];
        $generic = '';
        foreach ($rows as $row) {
            if ($row['admin_id'] === null) {
                $generic = (string) $row['comments'];
            } else {
                $staff[] = [
                    'admin_id' => (int) $row['admin_id'],
                    'name' => trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : '#' . $row['admin_id'],
                    'rating' => (int) $row['rating'],
                    'comments' => (string) $row['comments'],
                ];
            }
        }

        return ['created_at' => (string) $rows[0]['created_at'], 'staff' => $staff, 'comments' => $generic];
    }

    /**
     * Staff report: reply averages, feedback scores and recent comments.
     */
    public static function report(\Pimple\Container $di, mixed $period): array
    {
        $period = isset(self::PERIODS[(string) $period]) ? (string) $period : '90';
        $days = self::PERIODS[$period];
        $since = $days === 0 ? '1970-01-01 00:00:00' : date('Y-m-d H:i:s', time() - $days * 86400);
        $empty = ['period' => $period, 'ratings' => [], 'feedback' => [], 'comments' => []];

        try {
            $db = self::db($di);
            $starSums = [];
            for ($star = 1; $star <= self::MAX_STARS; ++$star) {
                $starSums[] = 'SUM(CASE WHEN r.rating = ' . $star . ' THEN 1 ELSE 0 END) AS s' . $star;
            }
            $ratings = $db->fetchAllAssociative(
                'SELECT r.admin_id, a.name, COUNT(*) AS total, AVG(r.rating) AS average, ' . implode(', ', $starSums)
                . ' FROM ' . self::RATINGS . ' r LEFT JOIN admin a ON a.id = r.admin_id WHERE r.rated_at >= ? GROUP BY r.admin_id, a.name ORDER BY average DESC, total DESC',
                [$since]
            );
            $feedback = $db->fetchAllAssociative(
                'SELECT f.admin_id, a.name, COUNT(*) AS total, AVG(f.rating) AS average FROM ' . self::FEEDBACK . ' f LEFT JOIN admin a ON a.id = f.admin_id'
                . ' WHERE f.admin_id IS NOT NULL AND f.created_at >= ? GROUP BY f.admin_id, a.name ORDER BY average DESC, total DESC',
                [$since]
            );
            $comments = $db->fetchAllAssociative(
                'SELECT f.ticket_id, t.subject, a.name, f.rating, f.comments, f.created_at FROM ' . self::FEEDBACK . ' f'
                . ' LEFT JOIN admin a ON a.id = f.admin_id LEFT JOIN support_ticket t ON t.id = f.ticket_id'
                . " WHERE f.comments <> '' AND f.created_at >= ? ORDER BY f.created_at DESC, f.id DESC LIMIT 100",
                [$since]
            );
        } catch (\Doctrine\DBAL\Exception) {
            return $empty;
        }

        $ratingsOut = [];
        foreach ($ratings as $row) {
            $stars = [];
            for ($star = 1; $star <= self::MAX_STARS; ++$star) {
                $stars[$star] = (int) $row['s' . $star];
            }
            $ratingsOut[] = [
                'admin_id' => (int) $row['admin_id'],
                'name' => trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : '#' . $row['admin_id'],
                'total' => (int) $row['total'],
                'average' => round((float) $row['average'], 2),
                'stars' => $stars,
            ];
        }
        $feedbackOut = [];
        foreach ($feedback as $row) {
            $feedbackOut[] = [
                'admin_id' => (int) $row['admin_id'],
                'name' => trim((string) ($row['name'] ?? '')) !== '' ? (string) $row['name'] : '#' . $row['admin_id'],
                'total' => (int) $row['total'],
                'average' => round((float) $row['average'], 1),
            ];
        }
        $commentsOut = [];
        foreach ($comments as $row) {
            $commentsOut[] = [
                'ticket_id' => (int) $row['ticket_id'],
                'subject' => (string) ($row['subject'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '')),
                'rating' => $row['rating'] === null ? null : (int) $row['rating'],
                'comments' => (string) $row['comments'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return ['period' => $period, 'ratings' => $ratingsOut, 'feedback' => $feedbackOut, 'comments' => $commentsOut];
    }

    /**
     * Moves a merged ticket's ratings and feedback to the surviving ticket.
     * The newest open request link transfers only when the survivor has no
     * link and no feedback.
     */
    public static function moveForMerge(Connection $db, int $masterId, array $merged): void
    {
        if ($merged === []) {
            return;
        }

        try {
            $db->executeStatement('UPDATE ' . self::RATINGS . ' SET ticket_id = ? WHERE ticket_id IN (?)', [$masterId, $merged], [\Doctrine\DBAL\ParameterType::INTEGER, ArrayParameterType::INTEGER]);
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException) {
        }

        try {
            $db->executeStatement('UPDATE ' . self::FEEDBACK . ' SET ticket_id = ? WHERE ticket_id IN (?)', [$masterId, $merged], [\Doctrine\DBAL\ParameterType::INTEGER, ArrayParameterType::INTEGER]);
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException) {
        }

        try {
            $keep = null;
            if ($db->fetchOne('SELECT 1 FROM ' . self::REQUESTS . ' WHERE ticket_id = ?', [$masterId]) === false
                && $db->fetchOne('SELECT 1 FROM ' . self::FEEDBACK . ' WHERE ticket_id = ?', [$masterId]) === false) {
                $keep = $db->fetchOne('SELECT ticket_id FROM ' . self::REQUESTS . ' WHERE ticket_id IN (?) AND answered_at IS NULL ORDER BY sent_at DESC LIMIT 1', [$merged], [ArrayParameterType::INTEGER]);
            }
            if ($keep !== false && $keep !== null) {
                $db->update(self::REQUESTS, ['ticket_id' => $masterId], ['ticket_id' => (int) $keep]);
            }
            $db->executeStatement('DELETE FROM ' . self::REQUESTS . ' WHERE ticket_id IN (?)', [$merged], [ArrayParameterType::INTEGER]);
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException) {
        }
    }

    /** Deletes ratings, feedback and request links of deleted tickets. */
    public static function sweep(\Pimple\Container $di): int
    {
        $swept = 0;
        foreach ([self::RATINGS, self::FEEDBACK, self::REQUESTS] as $table) {
            try {
                $swept += (int) self::db($di)->executeStatement('DELETE FROM ' . $table . ' WHERE NOT EXISTS (SELECT 1 FROM support_ticket t WHERE t.id = ' . $table . '.ticket_id)');
            } catch (\Doctrine\DBAL\Exception) {
            }
        }

        return $swept;
    }

    /**
     * Staff members who replied to the ticket (system accounts excluded).
     *
     * @return array<int, string> admin id => name
     */
    public static function staffInvolved(\Pimple\Container $di, int $ticketId): array
    {
        try {
            $rows = self::db($di)->fetchAllAssociative(
                'SELECT DISTINCT a.id, a.name FROM support_ticket_message m JOIN admin a ON a.id = m.admin_id WHERE m.support_ticket_id = ? AND a.system_name IS NULL ORDER BY a.name, a.id',
                [$ticketId]
            );
        } catch (\Throwable) {
            return [];
        }
        $staff = [];
        foreach ($rows as $row) {
            $name = trim((string) $row['name']);
            $staff[(int) $row['id']] = $name !== '' ? $name : '#' . $row['id'];
        }

        return $staff;
    }

    /**
     * @return array{id: int, subject: string, status: string, created_at: string}
     */
    private static function ticketForToken(\Pimple\Container $di, string $token): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new \FOSSBilling\Core\Exception\InformationException('This feedback link is not valid.');
        }

        try {
            $ticket = self::db($di)->fetchAssociative(
                'SELECT t.id, t.subject, t.status, t.created_at FROM ' . self::REQUESTS . ' r JOIN support_ticket t ON t.id = r.ticket_id WHERE r.token = ?',
                [$token]
            );
        } catch (\Throwable) {
            $ticket = false;
        }
        if ($ticket === false) {
            throw new \FOSSBilling\Core\Exception\InformationException('This feedback link is not valid.');
        }

        return ['id' => (int) $ticket['id'], 'subject' => (string) $ticket['subject'], 'status' => (string) $ticket['status'], 'created_at' => (string) $ticket['created_at']];
    }

    private static function comment(mixed $comment): string
    {
        $comment = is_string($comment) ? trim(str_replace(["\r\n", "\r"], "\n", $comment)) : '';
        if (mb_strlen($comment) > self::MAX_COMMENT) {
            throw new \FOSSBilling\Core\Exception\InformationException('A comment can be at most :max characters.', [':max' => self::MAX_COMMENT]);
        }

        return $comment;
    }

    private static function withTables(\Pimple\Container $di, callable $query): void
    {
        try {
            $query();
        } catch (\Doctrine\DBAL\Exception\TableNotFoundException) {
            // Tables arrive with the update patch; until then the switch reads as on.
        }
    }

    private static function db(\Pimple\Container $di): Connection
    {
        return $di['em']->getConnection();
    }
}
