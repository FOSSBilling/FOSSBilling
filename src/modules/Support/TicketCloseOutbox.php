<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace Box\Mod\Support;

use Box\Mod\Support\Event\AfterTicketClosedEvent;
use Box\Mod\Support\Event\TicketActorRole;
use Doctrine\DBAL\Connection;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Doctrine\RowLock;

/** Durable, at-least-once delivery of close events and feedback. */
final class TicketCloseOutbox
{
    public const string TABLE = 'support_ticket_close_event';

    /** Must be called in the same transaction as the close. */
    public static function enqueue(Connection $db, int $ticketId, TicketActorRole $actor, ?int $actorId): string
    {
        return self::enqueueRow($db, $ticketId, $actor->value, $actorId);
    }

    /** Queue feedback without emitting a manual close event. */
    public static function enqueueFeedback(Connection $db, int $ticketId): string
    {
        return self::enqueueRow($db, $ticketId, 'system', null);
    }

    private static function enqueueRow(Connection $db, int $ticketId, string $actor, ?int $actorId): string
    {
        $id = bin2hex(random_bytes(16));
        $db->insert(self::TABLE, ['id' => $id, 'ticket_id' => $ticketId, 'actor' => $actor, 'actor_id' => $actorId, 'created_at' => date('Y-m-d H:i:s'), 'attempted_at' => null, 'notification_queued_at' => null, 'feedback_queued_at' => null, 'delivered_at' => null]);

        return $id;
    }

    /** Returns false on failure; the pending event is retried by cron. */
    public static function deliver(\Pimple\Container $di, string $id): bool
    {
        $db = $di['em']->getConnection();
        if ($db->isTransactionActive()) {
            return false;
        }
        $lock = 'fb:ticket-close:' . $id;
        $acquired = false;

        try {
            if (!NamedLock::acquire($db, $lock, 0)) {
                return false;
            }
            $acquired = true;
            $row = $db->fetchAssociative('SELECT ticket_id, actor, actor_id FROM ' . self::TABLE . ' WHERE id = ? AND delivered_at IS NULL', [$id]);
            if ($row === false) {
                return true;
            }
            $db->executeStatement('UPDATE ' . self::TABLE . ' SET attempted_at = ? WHERE id = ? AND delivered_at IS NULL', [date('Y-m-d H:i:s'), $id]);
            if ($row['actor'] !== 'system') {
                $di['event_dispatcher']->dispatch(new AfterTicketClosedEvent((int) $row['ticket_id'], TicketActorRole::from((string) $row['actor']), $row['actor_id'] === null ? null : (int) $row['actor_id'], $id));
            }
            self::once($di, $id, 'feedback_queued_at', fn () => TicketFeedback::afterClose($di, (int) $row['ticket_id'], true));
            $db->executeStatement('UPDATE ' . self::TABLE . ' SET delivered_at = ? WHERE id = ? AND delivered_at IS NULL', [date('Y-m-d H:i:s'), $id]);

            return true;
        } catch (\Throwable $e) {
            $di['logger']->error('Support: ticket close event {id} failed and will be retried: {error}', ['id' => $id, 'error' => $e->getMessage()]);

            return false;
        } finally {
            if ($acquired) {
                NamedLock::release($db, $lock);
            }
        }
    }

    /** Commit one built-in queue operation with its marker, so retries do not enqueue it twice. */
    public static function once(\Pimple\Container $di, string $id, string $step, callable $callback): void
    {
        if (!in_array($step, ['notification_queued_at', 'feedback_queued_at'], true)) {
            throw new \InvalidArgumentException('Unknown ticket close delivery step.');
        }
        $em = $di['em'];
        $db = $em->getConnection();
        if ($db->isTransactionActive()) {
            throw new \LogicException('Ticket close delivery must run outside an existing transaction.');
        }

        try {
            $db->transactional(function () use ($db, $em, $id, $step, $callback): void {
                $row = $db->fetchAssociative('SELECT ' . $step . ' FROM ' . self::TABLE . ' WHERE id = ?' . RowLock::suffix($db), [$id]);
                if ($row === false) {
                    throw new \RuntimeException('Ticket close event no longer exists.');
                }
                if ($row[$step] !== null) {
                    return;
                }
                $callback();
                $em->flush();
                $db->update(self::TABLE, [$step => date('Y-m-d H:i:s')], ['id' => $id]);
            });
        } catch (\Throwable $e) {
            // Rollback does not undo the ORM's in-memory state. Discard it so
            // another event in this cron run cannot flush the failed queue work.
            if ($em->isOpen()) {
                $em->clear();
            }

            throw $e;
        }
    }

    public static function retryPending(\Pimple\Container $di): bool
    {
        $db = $di['em']->getConnection();
        $ids = $db->fetchFirstColumn('SELECT id FROM ' . self::TABLE . ' WHERE delivered_at IS NULL ORDER BY CASE WHEN attempted_at IS NULL THEN 0 ELSE 1 END, attempted_at, created_at, id LIMIT 100');
        foreach ($ids as $id) {
            if (!self::deliver($di, (string) $id) && !$di['em']->isOpen()) {
                $di['logger']->error('Support: close-event retry stopped because the entity manager is closed; remaining events will be retried on the next run.');

                return false;
            }
        }

        return true;
    }
}
