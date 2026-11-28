<?php

declare(strict_types=1);

use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\TicketCloseEvent;
use Box\Mod\Support\Service;
use Box\Mod\System\Entity\Setting;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Events\EventDispatcher;

use function Tests\Helpers\admin;
use function Tests\Helpers\container;

test('automatic closure recovers the shared manager and repositories after one ticket fails', function (string $failure): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = EntityManagerFactory::create($db);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Helpdesk::class, SupportTicket::class, TicketCloseEvent::class, Setting::class]));
    $db->executeStatement('CREATE TABLE support_ticket_secret (id INTEGER PRIMARY KEY, ticket_id INTEGER NOT NULL)');
    $helpdesk = (new Helpdesk())->setCloseAfter(24);
    $em->persist($helpdesk);
    $tickets = [];
    foreach ([1, 2] as $_) {
        $ticket = (new SupportTicket())->setStatus(SupportTicket::STATUS_ONHOLD)->setSupportHelpdesk($helpdesk);
        $em->persist($ticket);
        $tickets[] = $ticket;
    }
    $em->flush();
    [$first, $second] = array_map(fn ($ticket): int => $ticket->getId(), $tickets);
    $db->executeStatement("UPDATE support_ticket SET updated_at = '2020-01-01 00:00:00'");
    $db->insert('support_ticket_secret', ['id' => 1, 'ticket_id' => $first]);
    $trigger = $failure === 'purge'
        ? "BEFORE DELETE ON support_ticket_secret WHEN OLD.ticket_id = {$first}"
        : "BEFORE INSERT ON support_ticket_close_event WHEN NEW.ticket_id = {$first}";
    $db->executeStatement("CREATE TRIGGER reject_first {$trigger} BEGIN SELECT RAISE(ABORT, 'closure failed'); END");
    $di = container();
    $di['em'] = $em;
    $listeners = [];
    $di['event_dispatcher'] = new EventDispatcher(fn (): array => ['invoice'], function () use ($di, &$listeners): object {
        $bound = $di['em'];
        $listeners[] = $bound;
        $listener = Mockery::mock(Box\Mod\Invoice\Service::class)->shouldIgnoreMissing();
        $listener->shouldReceive('healMissingJournalEntries')->zeroOrMoreTimes()->andReturnUsing(function () use ($bound): void {
            expect($bound->isOpen())->toBeTrue();
            $bound->persist((new Setting())->setParam('after_support_listener')->setValue('healthy'));
            $bound->flush();
        });

        return $listener;
    });
    // Build retained listeners against the original manager before its failure.
    $di['event_dispatcher']->dispatch(new Box\Mod\Cron\Event\BeforeAdminCronRunEvent());
    $service = new Service();
    $service->setDi($di);
    $oldRepository = $service->getSupportTicketRepository();
    $api = new Box\Mod\Support\Api\Admin();
    $api->setDi($di);
    $api->setService($service);
    $api->setIdentity(admin());

    expect($api->batch_ticket_auto_close([]))->toBeTrue()
        ->and($em->isOpen())->toBeFalse()
        ->and($di['em']->isOpen())->toBeTrue()
        ->and($di['em'])->not->toBe($em)
        ->and($service->getSupportTicketRepository())->not->toBe($oldRepository)
        ->and($service->getTicketById($first)->getStatus())->toBe(SupportTicket::STATUS_ONHOLD)
        ->and($service->getTicketById($second)->getStatus())->toBe(SupportTicket::STATUS_CLOSED)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM support_ticket_secret'))->toBe(1)
        ->and($db->fetchFirstColumn('SELECT ticket_id FROM support_ticket_close_event'))->toBe([$second]);
    // A later module's ORM write and the retained dispatcher both use the replacement.
    $di['em']->persist((new Setting())->setParam('later_cron_task')->setValue('healthy'));
    $di['em']->flush();
    $di['event_dispatcher']->dispatch(new Box\Mod\Cron\Event\AfterAdminCronRunEvent());
    expect($listeners)->toBe([$em, $di['em']])
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM setting'))->toBe(2);
    $errors = array_filter($di['logger']->calls, fn ($call): bool => $call['method'] === 'error' && str_contains($call['params'][0], 'auto-closing'));
    expect($errors)->toHaveCount(1);
})->with(['purge', 'outbox']);
