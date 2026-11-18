<?php

declare(strict_types=1);

use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Event\AfterTicketClosedEvent;
use Box\Mod\Support\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function Tests\Helpers\admin;
use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

/** @return array{Service, Doctrine\DBAL\Connection, ArrayObject} */
function ticketCloseHarness(bool $secretTable = true): array
{
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, status TEXT NOT NULL, updated_at TEXT, support_helpdesk_id INTEGER, subject TEXT, client_id INTEGER, author_name TEXT, author_email TEXT, access_hash TEXT)');
    $db->executeStatement('CREATE TABLE support_ticket_close_event (id TEXT PRIMARY KEY, ticket_id INTEGER, actor TEXT, actor_id INTEGER, created_at TEXT, attempted_at TEXT, notification_queued_at TEXT, feedback_queued_at TEXT, delivered_at TEXT)');
    $db->executeStatement('CREATE TABLE support_helpdesk (id INTEGER PRIMARY KEY, close_after INTEGER)');
    $db->executeStatement('CREATE TABLE support_helpdesk_feedback (helpdesk_id INTEGER PRIMARY KEY, feedback_request INTEGER)');
    $db->insert('support_helpdesk_feedback', ['helpdesk_id' => 1, 'feedback_request' => 0]);
    $db->insert('support_helpdesk', ['id' => 1, 'close_after' => 24]);
    $db->insert('support_ticket', ['id' => 10, 'status' => 'open', 'updated_at' => '2020-01-01 00:00:00', 'support_helpdesk_id' => 1]);
    if ($secretTable) {
        $db->executeStatement('CREATE TABLE support_ticket_secret (id INTEGER PRIMARY KEY, ticket_id INTEGER NOT NULL)');
        $db->insert('support_ticket_secret', ['id' => 1, 'ticket_id' => 10]);
    }
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('isOpen')->andReturnTrue();
    $em->shouldReceive('clear')->zeroOrMoreTimes();
    $em->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing());
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
    $current = null;
    $em->shouldReceive('refresh')->andReturnUsing(function (SupportTicket $ticket) use ($db, &$current): void {
        $current = $ticket;
        $ticket->setStatus((string) $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?', [$ticket->getId()]));
    });
    $em->shouldReceive('flush')->andReturnUsing(function () use ($db, &$current): void {
        $db->update('support_ticket', ['status' => $current->getStatus()], ['id' => $current->getId()]);
    });
    $events = new ArrayObject();
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(AfterTicketClosedEvent::class, function ($event) use ($db, $events): void {
        $events[] = ['active' => $db->isTransactionActive(), 'ticket' => $event->ticketId];
    });
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $service = new Service();
    $service->setDi($di);

    return [$service, $db, $events];
}

test('stale concurrent closes produce one event after the purge commits', function (): void {
    [$service, $db, $events] = ticketCloseHarness();
    $first = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    $second = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect($service->closeTicket($first, admin()))->toBeTrue();
    expect($service->closeTicket($second, admin()))->toBeTrue();
    expect($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe('closed')
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(0)
        ->and($events->getArrayCopy())->toBe([['active' => false, 'ticket' => 10]]);
});

test('a secret purge error rolls back close and sends no close event', function (string $path): void {
    [$service, $db, $events] = ticketCloseHarness();
    $db->executeStatement("CREATE TRIGGER reject_secret_delete BEFORE DELETE ON support_ticket_secret BEGIN SELECT RAISE(ABORT, 'purge failed'); END");
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    if ($path === 'automatic') {
        $db->update('support_ticket', ['status' => SupportTicket::STATUS_ONHOLD], ['id' => 10]);
    }
    $call = match ($path) {
        'manual' => fn () => $service->closeTicket($ticket, admin()),
        'automatic' => fn () => $service->autoClose($ticket),
        'update' => fn () => $service->ticketUpdate($ticket, ['status' => 'closed']),
    };
    expect($call)->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'purge failed');
    expect($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe($path === 'automatic' ? SupportTicket::STATUS_ONHOLD : 'open')
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1)
        ->and($events->getArrayCopy())->toBe([]);
})->with(['manual', 'automatic', 'update']);

test('closing still works before the optional secrets table exists', function (): void {
    [$service, $db, $events] = ticketCloseHarness(false);
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect($service->closeTicket($ticket, admin()))->toBeTrue()
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe('closed')
        ->and($events->getArrayCopy())->toBe([['active' => false, 'ticket' => 10]]);
});

test('automatic closure skips a ticket whose latest reply reset its expiry', function (): void {
    [$service, $db, $events] = ticketCloseHarness();
    $db->update('support_ticket', ['status' => SupportTicket::STATUS_ONHOLD, 'updated_at' => date('Y-m-d H:i:s')], ['id' => 10]);
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => SupportTicket::STATUS_ONHOLD]);

    expect($service->autoClose($ticket))->toBeFalse()
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe(SupportTicket::STATUS_ONHOLD)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1)
        ->and($events->getArrayCopy())->toBe([]);
});

function concurrentTicketCloseService(Doctrine\DBAL\Connection $db, EventDispatcher $dispatcher, ?callable $onFlush = null): Service
{
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing());
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
    $current = null;
    $em->shouldReceive('refresh')->andReturnUsing(function (SupportTicket $ticket) use ($db, &$current): void {
        $current = $ticket;
        $ticket->setStatus((string) $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?', [$ticket->getId()]));
    });
    $em->shouldReceive('flush')->andReturnUsing(function () use ($db, &$current, $onFlush): void {
        $db->update('support_ticket', ['status' => $current->getStatus()], ['id' => $current->getId()]);
        if ($onFlush !== null) {
            $onFlush();
        }
    });
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $service = new Service();
    $service->setDi($di);

    return $service;
}

test('overlapping SQLite closes serialize or fail safely before retry', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'ticket-close-');
    $firstDb = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $secondDb = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);

    try {
        $firstDb->executeStatement('PRAGMA busy_timeout = 0');
        $secondDb->executeStatement('PRAGMA busy_timeout = 0');
        $firstDb->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, status TEXT NOT NULL, subject TEXT, client_id INTEGER, author_name TEXT, author_email TEXT, access_hash TEXT, support_helpdesk_id INTEGER)');
        $firstDb->executeStatement('CREATE TABLE support_helpdesk_feedback (helpdesk_id INTEGER PRIMARY KEY, feedback_request INTEGER)');
        $firstDb->insert('support_helpdesk_feedback', ['helpdesk_id' => 1, 'feedback_request' => 0]);
        $firstDb->executeStatement('CREATE TABLE support_ticket_close_event (id TEXT PRIMARY KEY, ticket_id INTEGER, actor TEXT, actor_id INTEGER, created_at TEXT, attempted_at TEXT, notification_queued_at TEXT, feedback_queued_at TEXT, delivered_at TEXT)');
        $firstDb->executeStatement('CREATE TABLE support_ticket_secret (id INTEGER PRIMARY KEY, ticket_id INTEGER NOT NULL)');
        $firstDb->insert('support_ticket', ['id' => 10, 'status' => 'open', 'support_helpdesk_id' => 1]);
        $firstDb->insert('support_ticket_secret', ['id' => 1, 'ticket_id' => 10]);
        $events = new ArrayObject();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(AfterTicketClosedEvent::class, function () use ($events): void { $events->append(true); });
        $second = concurrentTicketCloseService($secondDb, $dispatcher);
        $secondTicket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
        $conflict = null;
        $first = concurrentTicketCloseService($firstDb, $dispatcher, function () use ($second, $secondTicket, &$conflict): void {
            try {
                $second->closeTicket($secondTicket, admin());
            } catch (Doctrine\DBAL\Exception\DriverException $e) {
                $conflict = $e;
            }
        });
        $firstTicket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
        expect($first->closeTicket($firstTicket, admin()))->toBeTrue()
            ->and($conflict)->toBeInstanceOf(Doctrine\DBAL\Exception\DriverException::class)
            ->and($second->closeTicket($secondTicket, admin()))->toBeTrue()
            ->and($events->count())->toBe(1)
            ->and((int) $firstDb->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(0);
    } finally {
        $firstDb->close();
        $secondDb->close();
        unlink($path);
    }
});

test('reply insertion rolls back if the ticket status update fails', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
    $db->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, content TEXT)');
    $db->insert('support_ticket', ['id' => 10, 'status' => SupportTicket::STATUS_ONHOLD]);
    $db->executeStatement("CREATE TRIGGER reject_reopen BEFORE UPDATE ON support_ticket WHEN NEW.status = 'open' BEGIN SELECT RAISE(ABORT, 'status update failed'); END");
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing());
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
    $current = null;
    $em->shouldReceive('refresh')->andReturnUsing(function (SupportTicket $ticket) use ($db, &$current): void {
        $current = $ticket;
        $ticket->setStatus((string) $db->fetchOne('SELECT status FROM support_ticket WHERE id = ?', [$ticket->getId()]));
    });
    $em->shouldReceive('persist')->andReturnUsing(function (Box\Mod\Support\Entity\SupportTicketMessage $message) use ($db): void {
        $db->insert('support_ticket_message', ['id' => 1, 'support_ticket_id' => 10, 'content' => $message->getContent()]);
        Tests\Helpers\setEntityId($message, 1);
    });
    $em->shouldReceive('flush')->andReturnUsing(function () use ($db, &$current): void {
        $db->update('support_ticket', ['status' => $current->getStatus()], ['id' => $current->getId()]);
    });
    $events = new ArrayObject();
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(Box\Mod\Support\Event\AfterTicketRepliedEvent::class, function () use ($events): void { $events->append(true); });
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $service = new Service();
    $service->setDi($di);
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => SupportTicket::STATUS_ONHOLD]);

    expect(fn () => $service->ticketReply($ticket, new FOSSBilling\Core\Identity\Guest(), 'reply'))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'status update failed');
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_message'))->toBe(0)
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe(SupportTicket::STATUS_ONHOLD)
        ->and($events->count())->toBe(0);
});

test('ticket deletion blocks pending close events and serializes with replies', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'ticket-delete-');
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $otherDb = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);

    try {
        $db->executeStatement('PRAGMA busy_timeout = 0');
        $otherDb->executeStatement('PRAGMA busy_timeout = 0');
        $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, status TEXT)');
        $db->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, content TEXT)');
        $db->executeStatement('CREATE TABLE support_ticket_message_rating (message_id INTEGER PRIMARY KEY, ticket_id INTEGER)');
        $db->executeStatement('CREATE TABLE support_ticket_close_event (id TEXT PRIMARY KEY, ticket_id INTEGER, delivered_at TEXT)');
        $db->insert('support_ticket', ['id' => 10, 'status' => 'open']);
        $db->insert('support_ticket_message', ['id' => 1, 'support_ticket_id' => 10, 'content' => 'old']);
        $db->insert('support_ticket_message_rating', ['message_id' => 1, 'ticket_id' => 10]);
        $db->insert('support_ticket_close_event', ['id' => 'pending', 'ticket_id' => 10, 'delivered_at' => null]);

        $dispatcher = new EventDispatcher();
        $replyEm = Mockery::mock(EntityManagerInterface::class);
        $replyEm->shouldReceive('getConnection')->andReturn($otherDb);
        $replyEm->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing());
        $replyEm->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $otherDb->transactional(fn (): mixed => $fn()));
        $replyEm->shouldReceive('refresh')->andReturnUsing(function (SupportTicket $ticket) use ($otherDb): void {
            $ticket->setStatus((string) $otherDb->fetchOne('SELECT status FROM support_ticket WHERE id = ?', [$ticket->getId()]));
        });
        $replyEm->shouldReceive('persist')->andReturnUsing(fn () => $otherDb->insert('support_ticket_message', ['id' => 2, 'support_ticket_id' => 10, 'content' => 'late']));
        $replyDi = container();
        $replyDi['em'] = $replyEm;
        $replyDi['event_dispatcher'] = $dispatcher;
        $replyService = new Service();
        $replyService->setDi($replyDi);
        $replyTicket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
        $conflict = null;

        $noteRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketNoteRepository::class);
        $noteRepo->shouldReceive('findByTicketId')->andReturn([]);
        $historyRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketMessageHistoryRepository::class);
        $historyRepo->shouldReceive('findByMessageId')->andReturn([]);
        $message = createEntity(Box\Mod\Support\Entity\SupportTicketMessage::class, ['id' => 1]);
        $messageRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketMessageRepository::class);
        $messageRepo->shouldReceive('findByTicketId')->andReturn([$message]);
        $em = Mockery::mock(EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($db);
        $em->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => match ($entity) {
            Box\Mod\Support\Entity\SupportTicketNote::class => $noteRepo,
            Box\Mod\Support\Entity\SupportTicketMessageHistory::class => $historyRepo,
            Box\Mod\Support\Entity\SupportTicketMessage::class => $messageRepo,
            default => Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing(),
        });
        $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
        $em->shouldReceive('refresh');
        $em->shouldReceive('flush');
        $em->shouldReceive('remove')->andReturnUsing(function (object $entity) use ($db, $replyService, $replyTicket, &$conflict): void {
            if ($entity instanceof Box\Mod\Support\Entity\SupportTicketMessage) {
                $db->delete('support_ticket_message', ['id' => 1]);

                try {
                    $replyService->ticketReply($replyTicket, new FOSSBilling\Core\Identity\Guest(), 'late');
                } catch (Doctrine\DBAL\Exception\DriverException $e) {
                    $conflict = $e;
                }
            } elseif ($entity instanceof SupportTicket) {
                $db->delete('support_ticket', ['id' => 10]);
            }
        });
        $di = container();
        $di['em'] = $em;
        $service = new Service();
        $service->setDi($di);
        $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
        expect(fn () => $service->rm($ticket))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'pending close event');
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_message'))->toBe(1);
        $db->delete('support_ticket_close_event', ['id' => 'pending']);
        expect($service->rm($ticket))->toBeTrue()->and($conflict)->toBeInstanceOf(Doctrine\DBAL\Exception\DriverException::class);
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket'))->toBe(0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_message'))->toBe(0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_message_rating'))->toBe(0);
        expect(fn () => $replyService->ticketReply($replyTicket, new FOSSBilling\Core\Identity\Guest(), 'late'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
    } finally {
        $db->close();
        $otherDb->close();
        unlink($path);
    }
});

test('a close remains queued when a listener fails and is delivered on retry', function (): void {
    [$service, $db] = ticketCloseHarness();
    $dispatcher = $service->getDi()['event_dispatcher'];
    $fail = true;
    $calls = 0;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function () use (&$fail, &$calls): void {
        ++$calls;
        if ($fail) {
            throw new RuntimeException('listener unavailable');
        }
    });
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect($service->closeTicket($ticket, admin()))->toBeTrue()
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe('closed')
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event WHERE delivered_at IS NULL'))->toBe(1);
    $fail = false;
    Box\Mod\Support\TicketCloseOutbox::retryPending($service->getDi());
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event WHERE delivered_at IS NULL'))->toBe(0)
        ->and($calls)->toBe(2);
});

test('a close rolls back if its durable event cannot be recorded', function (): void {
    [$service, $db, $events] = ticketCloseHarness();
    $db->executeStatement('DROP TABLE support_ticket_close_event');
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect(fn () => $service->closeTicket($ticket, admin()))->toThrow(Doctrine\DBAL\Exception\TableNotFoundException::class);
    expect($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe('open')
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1)
        ->and($events->count())->toBe(0);
});

test('a close inside an outer transaction defers delivery and rolls back cleanly', function (): void {
    [$service, $db, $events] = ticketCloseHarness();
    $db->beginTransaction();
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect($service->closeTicket($ticket, admin()))->toBeTrue()
        ->and($events->count())->toBe(0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event WHERE delivered_at IS NULL'))->toBe(1);
    $db->rollBack();
    expect($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe('open')
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event'))->toBe(0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1);
});

test('a close inside a committed outer transaction is delivered by the next retry', function (): void {
    [$service, $db, $events] = ticketCloseHarness();
    $db->beginTransaction();
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    expect($service->closeTicket($ticket, admin()))->toBeTrue()->and($events->count())->toBe(0);
    $db->commit();
    Box\Mod\Support\TicketCloseOutbox::retryPending($service->getDi());
    expect($events->count())->toBe(1)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event WHERE delivered_at IS NULL'))->toBe(0);
});

test('automatic and status-update closures retry feedback without dispatching manual close events', function (string $path): void {
    [$service, $db, $events] = ticketCloseHarness();
    if ($path === 'automatic') {
        $db->update('support_ticket', ['status' => SupportTicket::STATUS_ONHOLD], ['id' => 10]);
    }
    $db->executeStatement('ALTER TABLE support_helpdesk_feedback RENAME TO unavailable_feedback_setting');
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => 'open']);
    $closed = $path === 'automatic' ? $service->autoClose($ticket) : $service->ticketUpdate($ticket, ['status' => 'closed']);
    expect($closed)->toBeTrue()
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe(SupportTicket::STATUS_CLOSED)
        ->and($db->fetchOne('SELECT actor FROM support_ticket_close_event WHERE ticket_id = 10'))->toBe('system')
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE ticket_id = 10'))->toBeNull()
        ->and($events->getArrayCopy())->toBe([]);
    if ($path === 'automatic') {
        expect($db->fetchOne('SELECT attempted_at FROM support_ticket_close_event WHERE ticket_id = 10'))->toBeNull();
    } else {
        expect($db->fetchOne('SELECT attempted_at FROM support_ticket_close_event WHERE ticket_id = 10'))->not->toBeNull();
    }
    $db->executeStatement('ALTER TABLE unavailable_feedback_setting RENAME TO support_helpdesk_feedback');
    Box\Mod\Support\TicketCloseOutbox::retryPending($service->getDi());
    expect($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE ticket_id = 10'))->not->toBeNull()
        ->and($events->getArrayCopy())->toBe([]);
})->with(['automatic', 'update']);

test('outbox insertion failure rolls back automatic and status-update closures', function (string $path): void {
    [$service, $db, $events] = ticketCloseHarness();
    $oldStatus = $path === 'automatic' ? SupportTicket::STATUS_ONHOLD : SupportTicket::STATUS_OPEN;
    $db->update('support_ticket', ['status' => $oldStatus], ['id' => 10]);
    $db->executeStatement("CREATE TRIGGER reject_outbox BEFORE INSERT ON support_ticket_close_event BEGIN SELECT RAISE(ABORT, 'outbox failed'); END");
    $ticket = createEntity(SupportTicket::class, ['id' => 10, 'status' => $oldStatus]);
    $close = $path === 'automatic' ? fn () => $service->autoClose($ticket) : fn () => $service->ticketUpdate($ticket, ['status' => 'closed']);
    expect($close)->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'outbox failed')
        ->and($db->fetchOne('SELECT status FROM support_ticket WHERE id = 10'))->toBe($oldStatus)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1)
        ->and($events->getArrayCopy())->toBe([]);
})->with(['automatic', 'update']);
