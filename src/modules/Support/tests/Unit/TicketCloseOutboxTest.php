<?php

declare(strict_types=1);

use Box\Mod\Support\Event\AfterTicketClosedEvent;
use Box\Mod\Support\Event\TicketActorRole;
use Box\Mod\Support\TicketCloseOutbox;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use FOSSBilling\Core\Doctrine\NamedLock;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function Tests\Helpers\container;

/** @return array{Pimple\Container, Doctrine\DBAL\Connection, EventDispatcher} */
function ticketCloseOutboxHarness(): array
{
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE support_ticket_close_event (id TEXT PRIMARY KEY, ticket_id INTEGER, actor TEXT, actor_id INTEGER, created_at TEXT, attempted_at TEXT, notification_queued_at TEXT, feedback_queued_at TEXT, delivered_at TEXT)');
    $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject TEXT, status TEXT, client_id INTEGER, author_name TEXT, author_email TEXT, access_hash TEXT, support_helpdesk_id INTEGER)');
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $em->shouldReceive('flush')->zeroOrMoreTimes();
    $em->shouldReceive('isOpen')->andReturnTrue();
    $em->shouldReceive('clear')->zeroOrMoreTimes();
    $em->shouldReceive('wrapInTransaction')->andReturnUsing(fn (callable $fn): mixed => $db->transactional(fn (): mixed => $fn()));
    $dispatcher = new EventDispatcher();
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;

    return [$di, $db, $dispatcher];
}

test('undelivered close events survive failure and retry with the original actor', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    $db->beginTransaction();
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::ADMIN, 7);
    $db->commit();
    $received = [];
    $fail = true;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use (&$received, &$fail): void {
        $received[] = [$event->ticketId, $event->actor, $event->actorId, $event->deliveryId];
        if ($fail) {
            throw new RuntimeException('listener failed');
        }
    });

    expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$id]))->toBeNull()
        ->and($db->fetchOne('SELECT attempted_at FROM support_ticket_close_event WHERE id = ?', [$id]))->not->toBeNull();
    $fail = false;
    TicketCloseOutbox::retryPending($di);
    expect($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$id]))->not->toBeNull()
        ->and($received)->toBe([[10, TicketActorRole::ADMIN, 7, $id], [10, TicketActorRole::ADMIN, 7, $id]]);
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()->and($received)->toHaveCount(2);
});

test('a rolled back close cannot leave a queued event', function (): void {
    [, $db] = ticketCloseOutboxHarness();
    $db->beginTransaction();
    TicketCloseOutbox::enqueue($db, 10, TicketActorRole::CLIENT, 3);
    $db->rollBack();
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_close_event'))->toBe(0);
});

test('a second worker does not dispatch an event held by another worker', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::GUEST, null);
    $calls = 0;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function () use (&$calls): void { ++$calls; });
    $name = 'fb:ticket-close:' . $id;
    expect(NamedLock::acquire($db, $name, 0))->toBeTrue();

    try {
        expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()->and($calls)->toBe(0);
    } finally {
        NamedLock::release($db, $name);
    }
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()->and($calls)->toBe(1);
});

test('fresh close events are not starved by previously failed events', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    for ($i = 1; $i <= 101; ++$i) {
        $db->insert('support_ticket_close_event', [
            'id' => str_pad((string) $i, 32, '0', STR_PAD_LEFT), 'ticket_id' => $i,
            'actor' => 'guest', 'actor_id' => null, 'created_at' => '2020-01-01 00:00:00',
            'attempted_at' => $i <= 100 ? '2020-01-02 00:00:00' : null, 'delivered_at' => null,
        ]);
    }
    $received = [];
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use (&$received): void { $received[] = $event->ticketId; });
    TicketCloseOutbox::retryPending($di);
    expect($received)->toHaveCount(100)->toContain(101)
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE ticket_id = 101'))->not->toBeNull();
});

test('the built-in notification is queued only once when a later listener fails', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    $db->executeStatement('CREATE TABLE test_email_queue (id INTEGER PRIMARY KEY)');
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::ADMIN, 7);
    $fail = true;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use ($di, $db): void {
        TicketCloseOutbox::once($di, $event->deliveryId, 'notification_queued_at', function () use ($db): void {
            $db->insert('test_email_queue', ['id' => 1]);
        });
    }, 10);
    $dispatcher->addListener(AfterTicketClosedEvent::class, function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('later listener failed');
        }
    });
    expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(1);
    $fail = false;
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(1);
});

test('a failed notification rolls back both the email and its delivery marker', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    $db->executeStatement('CREATE TABLE test_email_queue (id INTEGER PRIMARY KEY)');
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::ADMIN, 7);
    $fail = true;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use ($di, $db, &$fail): void {
        TicketCloseOutbox::once($di, $event->deliveryId, 'notification_queued_at', function () use ($db, &$fail): void {
            $db->insert('test_email_queue', ['id' => 1]);
            if ($fail) {
                throw new RuntimeException('queue failed');
            }
        });
    });
    expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(0)
        ->and($db->fetchOne('SELECT notification_queued_at FROM support_ticket_close_event WHERE id = ?', [$id]))->toBeNull();
    $fail = false;
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(1);
});

test('feedback queue failure rolls back the request and leaves the event pending', function (): void {
    [$di, $db] = ticketCloseOutboxHarness();
    $db->executeStatement('CREATE TABLE support_helpdesk_feedback (helpdesk_id INTEGER PRIMARY KEY, feedback_request INTEGER)');
    $db->executeStatement('CREATE TABLE support_ticket_feedback (ticket_id INTEGER)');
    $db->executeStatement('CREATE TABLE support_ticket_feedback_request (ticket_id INTEGER PRIMARY KEY, token TEXT, sent_at TEXT, answered_at TEXT)');
    $db->insert('support_helpdesk_feedback', ['helpdesk_id' => 1, 'feedback_request' => 1]);
    $db->insert('support_ticket', ['id' => 10, 'subject' => 'Help', 'status' => 'closed', 'client_id' => 5, 'support_helpdesk_id' => 1]);
    $fail = true;
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->twice()->andReturnUsing(function () use (&$fail): bool {
        if ($fail) {
            throw new RuntimeException('queue unavailable');
        }

        return true;
    });
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $url = Mockery::mock(FOSSBilling\Core\Url::class);
    $url->shouldReceive('link')->andReturn('https://billing.example/feedback');
    $di['url'] = $url;
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::CLIENT, 5);
    expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback_request'))->toBe(0)
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$id]))->toBeNull();
    $fail = false;
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_feedback_request'))->toBe(1)
        ->and($db->fetchOne('SELECT feedback_queued_at FROM support_ticket_close_event WHERE id = ?', [$id]))->not->toBeNull();
});

test('the close email listener does not queue twice when a later listener fails', function (): void {
    [$di, $db, $dispatcher] = ticketCloseOutboxHarness();
    $db->executeStatement('CREATE TABLE test_email_queue (id INTEGER PRIMARY KEY)');
    $admin = Tests\Helpers\admin(['id' => 7]);
    $ticket = Tests\Helpers\createEntity(Box\Mod\Support\Entity\SupportTicket::class, ['id' => 10]);
    $service = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $service->shouldReceive('getTicketById')->once()->with(10)->andReturn($ticket);
    $service->shouldReceive('toApiArray')->once()->with($ticket, true, $admin)->andReturn([]);
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldReceive('sendTemplate')->once()->andReturnUsing(function () use ($db): bool {
        $db->insert('test_email_queue', ['id' => 1]);

        return true;
    });
    $adminRepo = Mockery::mock(Box\Mod\Staff\Repository\AdminRepository::class);
    $adminRepo->shouldReceive('find')->once()->with(7)->andReturn($admin);
    $di['em']->shouldReceive('getRepository')->andReturnUsing(fn (string $entity): object => $entity === Box\Mod\Staff\Entity\Admin::class
        ? $adminRepo : Mockery::mock(str_replace('\\Entity\\', '\\Repository\\', $entity) . 'Repository')->shouldIgnoreMissing());
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['support' => $service, 'email' => $email]));
    $service->setDi($di);
    $fail = true;
    $dispatcher->addListener(AfterTicketClosedEvent::class, [$service, 'notifyTicketClosed'], 10);
    $dispatcher->addListener(AfterTicketClosedEvent::class, function () use (&$fail): void {
        if ($fail) {
            throw new RuntimeException('later listener failed');
        }
    });
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::ADMIN, 7);
    expect(TicketCloseOutbox::deliver($di, $id))->toBeFalse()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(1);
    $fail = false;
    expect(TicketCloseOutbox::deliver($di, $id))->toBeTrue()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM test_email_queue'))->toBe(1);
});

test('the real ORM rolls back an email queue write with its delivery marker', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([
        dirname(__DIR__, 2) . '/Entity',
        dirname(__DIR__, 3) . '/Email/Entity',
    ], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\EventProxies');
    $em = new Doctrine\ORM\EntityManager($db, $config);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
        $em->getClassMetadata(Box\Mod\Support\Entity\TicketCloseEvent::class),
        $em->getClassMetadata(Box\Mod\Email\Entity\QueuedEmail::class),
    ]);
    $id = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::ADMIN, 7);
    $di = container();
    $di['em'] = $em;
    $queueEmail = static function (EntityManagerInterface $em): void {
        $email = new Box\Mod\Email\Entity\QueuedEmail();
        $email->setRecipient('to@example.com')->setSender('from@example.com')->setSubject('Closed')->setContent('Closed');
        $em->persist($email);
        $em->flush();
    };
    expect(fn () => TicketCloseOutbox::once($di, $id, 'notification_queued_at', function () use ($em, $queueEmail): void {
        $queueEmail($em);

        throw new RuntimeException('after queue');
    }))->toThrow(RuntimeException::class, 'after queue');
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)
        ->and($db->fetchOne('SELECT notification_queued_at FROM support_ticket_close_event WHERE id = ?', [$id]))->toBeNull();
    expect($em->isOpen())->toBeTrue();
    $retryEm = $em;
    $retryDi = container();
    $retryDi['em'] = $retryEm;
    TicketCloseOutbox::once($retryDi, $id, 'notification_queued_at', fn () => $queueEmail($retryEm));
    TicketCloseOutbox::once($retryDi, $id, 'notification_queued_at', fn () => $queueEmail($retryEm));
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(1)
        ->and($db->fetchOne('SELECT notification_queued_at FROM support_ticket_close_event WHERE id = ?', [$id]))->not->toBeNull();
});

test('a failed delivery does not poison the entity manager for the next queued event', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([
        dirname(__DIR__, 2) . '/Entity', dirname(__DIR__, 3) . '/Email/Entity',
    ], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\EventProxies');
    $em = new Doctrine\ORM\EntityManager($db, $config);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
        $em->getClassMetadata(Box\Mod\Support\Entity\TicketCloseEvent::class),
        $em->getClassMetadata(Box\Mod\Email\Entity\QueuedEmail::class),
    ]);
    $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject TEXT, status TEXT, client_id INTEGER, author_name TEXT, author_email TEXT, access_hash TEXT, support_helpdesk_id INTEGER)');
    $first = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::CLIENT, 5);
    $second = TicketCloseOutbox::enqueue($db, 11, TicketActorRole::CLIENT, 5);
    $di = container();
    $di['em'] = $em;
    $dispatcher = new EventDispatcher();
    $di['event_dispatcher'] = $dispatcher;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use ($di, $em): void {
        if ($event->ticketId !== 10) {
            return;
        }
        TicketCloseOutbox::once($di, $event->deliveryId, 'notification_queued_at', function () use ($em): void {
            $email = new Box\Mod\Email\Entity\QueuedEmail();
            $email->setRecipient('to@example.com')->setSender('from@example.com')->setSubject('Closed')->setContent('Closed');
            $em->persist($email);

            throw new RuntimeException('queue failed');
        });
    });
    TicketCloseOutbox::retryPending($di);
    expect($em->isOpen())->toBeTrue()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$first]))->toBeNull()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$second]))->not->toBeNull()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0);
});

test('an ORM flush failure stops the batch and leaves later events for a fresh manager', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $config = Doctrine\ORM\ORMSetup::createAttributeMetadataConfig([
        dirname(__DIR__, 2) . '/Entity', dirname(__DIR__, 3) . '/Email/Entity',
    ], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\EventProxies');
    $em = new Doctrine\ORM\EntityManager($db, $config);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([
        $em->getClassMetadata(Box\Mod\Support\Entity\TicketCloseEvent::class),
        $em->getClassMetadata(Box\Mod\Email\Entity\QueuedEmail::class),
    ]);
    $db->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject TEXT, status TEXT, client_id INTEGER, author_name TEXT, author_email TEXT, access_hash TEXT, support_helpdesk_id INTEGER)');
    $first = TicketCloseOutbox::enqueue($db, 10, TicketActorRole::CLIENT, 5);
    $second = TicketCloseOutbox::enqueue($db, 11, TicketActorRole::CLIENT, 5);
    $db->update('support_ticket_close_event', ['created_at' => '2000-01-01 00:00:00'], ['id' => $first]);
    $db->executeStatement("CREATE TRIGGER reject_email BEFORE INSERT ON email_queue BEGIN SELECT RAISE(ABORT, 'queue failure'); END");
    $di = container();
    $di['em'] = $em;
    $dispatcher = new EventDispatcher();
    $di['event_dispatcher'] = $dispatcher;
    $dispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use ($di): void {
        if ($event->ticketId !== 10) {
            return;
        }
        TicketCloseOutbox::once($di, $event->deliveryId, 'notification_queued_at', function () use ($di): void {
            $email = new Box\Mod\Email\Entity\QueuedEmail();
            $email->setRecipient('to@example.com')->setSender('from@example.com')->setSubject('Closed')->setContent('Closed');
            $di['em']->persist($email);
        });
    });
    TicketCloseOutbox::retryPending($di);
    expect($em->isOpen())->toBeFalse()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$first]))->toBeNull()
        ->and($db->fetchOne('SELECT attempted_at FROM support_ticket_close_event WHERE id = ?', [$second]))->toBeNull()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0);
    $db->executeStatement('DROP TRIGGER reject_email');
    $freshDi = container();
    $freshDi['em'] = new Doctrine\ORM\EntityManager($db, $config);
    $freshDispatcher = new EventDispatcher();
    $freshDi['event_dispatcher'] = $freshDispatcher;
    $freshDispatcher->addListener(AfterTicketClosedEvent::class, function (AfterTicketClosedEvent $event) use ($freshDi): void {
        if ($event->ticketId !== 10) {
            return;
        }
        TicketCloseOutbox::once($freshDi, $event->deliveryId, 'notification_queued_at', function () use ($freshDi): void {
            $email = new Box\Mod\Email\Entity\QueuedEmail();
            $email->setRecipient('to@example.com')->setSender('from@example.com')->setSubject('Closed')->setContent('Closed');
            $freshDi['em']->persist($email);
        });
    });
    TicketCloseOutbox::retryPending($freshDi);
    expect($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$first]))->not->toBeNull()
        ->and($db->fetchOne('SELECT delivered_at FROM support_ticket_close_event WHERE id = ?', [$second]))->not->toBeNull()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(1);
});
