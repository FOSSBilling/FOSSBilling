<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Service;
use Doctrine\ORM\Tools\SchemaTool;

use function Tests\Helpers\container;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('deferred invoice issuance survives listener failure and is retried', function (): void {
    [$service, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $calls = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use (&$calls): void {
        if (++$calls === 1) {
            throw new RuntimeException('interrupted notification');
        }
    });
    $di = $service->getDi();
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    expect(fn () => $service->notifyDeferredInvoiceIssued($invoice))->toThrow(RuntimeException::class, 'interrupted notification');
    expect((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(2);
    $service->doBatchDeliverIssuedNotifications();
    expect($calls)->toBe(2)->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(0);
    $service->doBatchDeliverIssuedNotifications();
    expect($calls)->toBe(2);
});

test('checkout issuance persists pending notification and credit intent in its transaction', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setNr('42')->setIssueNotificationState(1);
    $em->persist($invoice);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->issueInvoice($invoice, ['use_credits' => false, 'defer_issue_event' => true, 'deferred_issue_use_credits' => true]))->toBeTrue();
    expect((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(1);
});

test('a recovered checkout attempts its intended credit payment before issuing its notification', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(1);
    $em->persist($invoice);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('tryPayWithCredits')->once()->with($invoice)->andReturnUsing(function () use ($invoice, $em): bool {
        $invoice->setStatus(Invoice::STATUS_PAID);
        $em->flush();

        return true;
    });
    $observed = [];
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use (&$observed, $invoice): void { $observed[] = $invoice->getStatus(); });
    $di = $service->getDi();
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $service->doBatchDeliverIssuedNotifications();
    expect($observed)->toBe([Invoice::STATUS_PAID])->and($invoice->getIssueNotificationState())->toBe(0);
});

test('a deferred issued invoice email is retried after the built-in listener fails', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $attempts = 0;
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldReceive('sendTemplate')->twice()->andReturnUsing(function () use (&$attempts): bool {
        if (++$attempts === 1) {
            throw new RuntimeException('temporary email failure');
        }

        return true;
    });
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->twice()->andReturn(['id' => $invoice->getId(), 'total' => 15.0, 'status' => Invoice::STATUS_UNPAID, 'client' => ['id' => $client->getId()]]);
    $service->shouldReceive('getInvoicePdfAttachment')->twice()->with($invoice)->andReturnNull();
    $service->shouldReceive('extendInvoiceHashLifetime')->once()->with($invoice);
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, [$service, 'sendIssuedInvoiceEmail']);
    $di = container();
    $di['em'] = $em;
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $service->setDi($di);
    expect(fn () => $service->notifyDeferredInvoiceIssued($invoice))->toThrow(RuntimeException::class, 'temporary email failure');
    expect((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(2);
    $service->doBatchDeliverIssuedNotifications();
    expect($attempts)->toBe(2)
        ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(0);
    $service->doBatchDeliverIssuedNotifications();
    expect($attempts)->toBe(2);
});

test('issuance retries preserve the queued email checkpoint after a later listener fails', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2)->setNr('42');
    $em->persist($invoice);
    $em->flush();
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldReceive('sendTemplate')->once()->andReturn(true);
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('recordJournalEvent')->zeroOrMoreTimes();
    $service->shouldReceive('toApiArray')->once()->andReturn(['id' => $invoice->getId(), 'total' => 15.0, 'status' => Invoice::STATUS_UNPAID, 'client' => ['id' => $client->getId()]]);
    $service->shouldReceive('getInvoicePdfAttachment')->once()->andReturnNull();
    $service->shouldReceive('extendInvoiceHashLifetime')->once();
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, [$service, 'sendIssuedInvoiceEmail']);
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function (): void { throw new RuntimeException('later listener failed'); }, -10);
    $di = container();
    $di['em'] = $em;
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $service->setDi($di);
    expect(fn () => $service->issueInvoice($invoice, []))->toThrow(RuntimeException::class);
    expect($invoice->getIssueNotificationState())->toBe(6);
    expect(fn () => $service->issueInvoice($invoice, []))->toThrow(RuntimeException::class);
    expect($invoice->getIssueNotificationState())->toBe(6);
});

test('a canceled invoice does not send a deferred issue email', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_CANCELED)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldNotReceive('sendTemplate');
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->once()->andReturn(['id' => $invoice->getId(), 'total' => 15.0, 'status' => Invoice::STATUS_CANCELED, 'client' => ['id' => $client->getId()]]);
    $service->shouldReceive('extendInvoiceHashLifetime')->once();
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, [$service, 'sendIssuedInvoiceEmail']);
    $di = container();
    $di['em'] = $em;
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $service->setDi($di);
    $service->doBatchDeliverIssuedNotifications();
    expect($invoice->getIssueNotificationState())->toBe(0);
});

test('a failure after queue insertion rolls back the email checkpoint and permits retry', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Email\Entity\QueuedEmail::class)]);
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldReceive('sendTemplate')->twice()->andReturnUsing(function () use ($em): bool {
        $queue = (new Box\Mod\Email\Entity\QueuedEmail())->setRecipient('client@example.test')->setSender('admin@example.test')->setSubject('test')->setContent('content');
        $em->persist($queue);
        $em->flush();

        return true;
    });
    $attempt = 0;
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('toApiArray')->twice()->andReturn(['id' => $invoice->getId(), 'total' => 15.0, 'status' => Invoice::STATUS_UNPAID, 'client' => ['id' => $client->getId()]]);
    $service->shouldReceive('getInvoicePdfAttachment')->twice()->andReturnNull();
    $service->shouldReceive('extendInvoiceHashLifetime')->twice()->andReturnUsing(function () use (&$attempt): void {
        if (++$attempt === 1) {
            throw new RuntimeException('hash update failed');
        }
    });
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, [$service, 'sendIssuedInvoiceEmail']);
    $di = container();
    $di['em'] = $em;
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $service->setDi($di);
    expect(fn () => $service->notifyDeferredInvoiceIssued($invoice))->toThrow(RuntimeException::class, 'hash update failed');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(0)
        ->and($invoice->getIssueNotificationState())->toBe(2);
    $service->notifyDeferredInvoiceIssued($invoice);
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM email_queue'))->toBe(1)
        ->and($invoice->getIssueNotificationState())->toBe(0);
});

test('deferred email rechecks invoice status after acquiring its row lock', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldNotReceive('sendTemplate');
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('lockInvoiceState')->once()->andReturnUsing(function () use ($em, $invoice): array {
        $em->getConnection()->executeStatement('UPDATE invoice SET status = ? WHERE id = ?', [Invoice::STATUS_CANCELED, $invoice->getId()]);

        return ['status' => Invoice::STATUS_CANCELED, 'issued' => true];
    });
    $service->shouldReceive('toApiArray')->once()->andReturnUsing(fn (): array => ['id' => $invoice->getId(), 'total' => 15.0, 'status' => $invoice->getStatus(), 'client' => ['id' => $client->getId()]]);
    $service->shouldReceive('extendInvoiceHashLifetime')->once();
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, [$service, 'sendIssuedInvoiceEmail']);
    $di = container();
    $di['em'] = $em;
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (): object => $email);
    $service->setDi($di);
    $service->notifyDeferredInvoiceIssued($invoice);
    expect($invoice->getStatus())->toBe(Invoice::STATUS_CANCELED)->and($invoice->getIssueNotificationState())->toBe(0);
});

test('ordinary issuance still notifies after credit failure and recovers an interrupted notification', function (bool $closed): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true)->setNr('42')->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('tryPayWithCredits')->once()->andReturnUsing(function () use ($em, $closed): void {
        if ($closed) {
            $em->close();
        }

        throw new RuntimeException('credit lock unavailable');
    });
    $fresh = new Doctrine\ORM\EntityManager($em->getConnection(), $em->getConfiguration());
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->zeroOrMoreTimes()->andReturn($fresh);
    $calls = 0;
    $events = new Symfony\Component\EventDispatcher\EventDispatcher();
    $events->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use (&$calls): void {
        if (++$calls === 1) {
            throw new Error('notification crash');
        }
    });
    $service->getDi()['event_dispatcher'] = $events;
    expect(fn () => $service->issueInvoice($invoice, ['use_credits' => true]))->toThrow(Error::class, 'notification crash');
    expect($calls)->toBe(1)
        ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(2);
    $service->doBatchDeliverIssuedNotifications();
    expect($calls)->toBe(2)
        ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(0);
})->with([false, true]);

function firstIssuanceTestService(Doctrine\ORM\EntityManager $em, bool $autoIssued = true): Service
{
    $service = gatewayDepositTestService($em);
    $settings = Mockery::mock(Box\Mod\System\Service::class);
    $settings->shouldReceive('getCompany')->andReturn(['name' => 'Issuer']);
    $settings->shouldReceive('getParamValue')->andReturnUsing(fn (string $key, mixed $default = null): mixed => $key === 'invoice_auto_issue' ? $autoIssued : $default);
    $clients = Mockery::mock(Box\Mod\Client\Service::class);
    $clients->shouldReceive('toApiArray')->andReturn(['first_name' => 'Buyer']);
    $di = $service->getDi();
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($module) {
        'system' => $settings,
        'Client' => $clients,
        default => $services($module, $sub),
    });

    return $service;
}

test('first issuance commits notification intent from a draft with the default checkpoint', function (bool $deferred): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid');
    $em->persist($invoice);
    $em->flush();
    expect($invoice->isIssued())->toBeFalse()->and($invoice->getIssueNotificationState())->toBe(0);
    $service = firstIssuanceTestService($em);
    $service->shouldReceive('getNextInvoiceNumber')->once()->andReturn(42);
    if (!$deferred) {
        $service->shouldReceive('tryPayWithCredits')->once()->andReturnUsing(function () use ($em, $invoice): never {
            expect((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(2);

            throw new RuntimeException('credit failed after issuance commit');
        });
        $events = new Symfony\Component\EventDispatcher\EventDispatcher();
        $events->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use ($em, $invoice): never {
            expect((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(2);

            throw new Error('notification interrupted');
        });
        $service->getDi()['event_dispatcher'] = $events;
    }
    $issue = fn () => $service->issueInvoice($invoice, ['defer_issue_event' => $deferred, 'deferred_issue_use_credits' => $deferred, 'use_credits' => !$deferred]);
    if ($deferred) {
        expect($issue())->toBeTrue();
    } else {
        expect($issue)->toThrow(Error::class, 'notification interrupted');
    }
    expect((int) $em->getConnection()->fetchOne('SELECT issued FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(1)
        ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe($deferred ? 1 : 2);
})->with([true, false]);

test('Add Funds initializes auto-issued notification intent after creating its deposit line', function (bool $autoIssued): void {
    [, $em, $client] = receiptTestContext();
    $service = firstIssuanceTestService($em, $autoIssued);
    // Configuration defaults are separate from the creation/issuance protocol.
    $service->shouldReceive('setInvoiceDefaults')->once();
    $service->shouldReceive('getNextInvoiceNumber')->once()->andReturn(42);
    $events = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use (&$events): void { ++$events; });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    $invoice = $service->generateFundsInvoice($client, 25);
    expect($invoice->getNr())->toBe($autoIssued ? '42' : null)
        ->and($invoice->isIssued())->toBe($autoIssued)->and($invoice->getIssueNotificationState())->toBe($autoIssued ? 2 : 0)
        ->and((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ? AND type = 'deposit'", [$invoice->getId()]))->toBe(1);
    expect($service->issueInvoice($invoice, []))->toBeTrue()->and($events)->toBe(1)
        ->and($invoice->getIssueNotificationState())->toBe(0);
    expect($service->issueInvoice($invoice, []))->toBeTrue()->and($events)->toBe(1);
})->with([true, false]);

test('Add Funds publishes its number and notification atomically and rolls back interrupted creation', function (bool $crash): void {
    $path = Symfony\Component\Filesystem\Path::join(sys_get_temp_dir(), 'funds-issuance-' . bin2hex(random_bytes(8)) . '.sqlite');
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $reader = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);

    try {
        [, $em, $client] = receiptTestContext($connection);
        $clientId = $client->getId();
        $configuration = $em->getConfiguration();
        $connection->executeStatement('CREATE TABLE setting (id INTEGER PRIMARY KEY AUTOINCREMENT, param VARCHAR(255) UNIQUE, value TEXT, created_at TEXT, updated_at TEXT)');
        $connection->insert('setting', ['param' => 'invoice_starting_number', 'value' => '42']);
        $makeService = function (Doctrine\ORM\EntityManagerInterface $manager) {
            $service = firstIssuanceTestService($manager);
            $service->shouldReceive('setInvoiceDefaults')->once();
            $settings = new Box\Mod\System\Service();
            $settings->setDi(new Pimple\Container(['em' => $manager, 'dbal' => $manager->getConnection()]));
            $service->shouldReceive('getNextInvoiceNumber')->once()->andReturnUsing(fn () => $settings->reserveNextNumericParamValue('invoice_starting_number'));

            return $service;
        };
        $service = $makeService($em);
        $observer = new class($reader, $crash) {
            public int $checks = 0;

            public function __construct(private readonly Doctrine\DBAL\Connection $reader, private readonly bool $crash)
            {
            }

            public function postFlush(Doctrine\ORM\Event\PostFlushEventArgs $event): void
            {
                ++$this->checks;
                expect((int) $this->reader->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(0)
                    ->and($this->reader->fetchOne("SELECT value FROM setting WHERE param = 'invoice_starting_number'"))->toBe('42');
                if ($this->crash && $event->getObjectManager()->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice') == 2) {
                    throw new Error('creation interrupted before commit');
                }
            }
        };
        $em->getEventManager()->addEventListener(['postFlush'], $observer);
        if ($crash) {
            expect(fn () => $service->generateFundsInvoice($client, 25))->toThrow(Error::class, 'creation interrupted before commit');
            expect((int) $reader->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(0)
                ->and((int) $reader->fetchOne('SELECT COUNT(*) FROM invoice_item'))->toBe(0)
                ->and($reader->fetchOne("SELECT value FROM setting WHERE param = 'invoice_starting_number'"))->toBe('42');
            $em = new Doctrine\ORM\EntityManager($connection, $configuration);
            $service = $makeService($em);
            $client = $em->find(Box\Mod\Client\Entity\Client::class, $clientId);
        }
        $invoice = $service->generateFundsInvoice($client, 25);
        $em->getEventManager()->removeEventListener(['postFlush'], $observer);
        expect($observer->checks)->toBeGreaterThan(1)
            ->and($reader->fetchAssociative('SELECT nr, issued, issue_notification_state FROM invoice'))->toMatchArray(['nr' => '42', 'issued' => 1, 'issue_notification_state' => 2])
            ->and((int) $reader->fetchOne("SELECT COUNT(*) FROM invoice_item WHERE type = 'deposit'"))->toBe(1)
            ->and($reader->fetchOne("SELECT value FROM setting WHERE param = 'invoice_starting_number'"))->toBe('43');
        $events = 0;
        $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
        $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function () use (&$events, $reader): void {
            ++$events;
            expect($reader->fetchOne('SELECT nr FROM invoice'))->toBe('42');
        });
        $service->getDi()['event_dispatcher'] = $dispatcher;
        $service->issueInvoice($invoice, []);
        $service->issueInvoice($invoice, []);
        expect($events)->toBe(1)->and($invoice->getNr())->toBe('42')
            ->and((int) $reader->fetchOne('SELECT issue_notification_state FROM invoice'))->toBe(0);
    } finally {
        $reader->close();
        $connection->close();
        (new Symfony\Component\Filesystem\Filesystem())->remove($path);
    }
})->with([false, true]);

test('notification recovery continues past a receipt reconciliation hold after replacing its manager', function (int $checkpoint, int $readyCheckpoint): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $held = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setIssued(true)->setStatus(Invoice::STATUS_UNPAID)->setIssueNotificationState($checkpoint);
    $ready = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setIssued(true)->setStatus(Invoice::STATUS_UNPAID)->setIssueNotificationState($readyCheckpoint);
    $em->persist($held);
    $em->persist($ready);
    $em->flush();
    $transaction->setInvoice($held)->setStatus(Box\Mod\Invoice\Entity\Transaction::STATUS_PROCESSED)->setTxnId('pi_held');
    $receipt = (new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $transaction->getId())->setDescription('Stripe transaction pi_held')->setAmount('15');
    $em->persist($receipt);
    $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($held)->setType('custom')->setPrice(100)->setQuantity(1));
    $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($ready)->setType('custom')->setPrice(100)->setQuantity(1));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $replacements = 0;
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->twice()->andReturnUsing(function ($connection) use ($em, &$replacements): Doctrine\ORM\EntityManager {
        ++$replacements;

        return new Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
    });
    $delivered = [];
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoiceIssueEvent::class, function ($event) use (&$delivered): void { $delivered[] = $event->invoiceId; });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    foreach ([1, 2] as $attempt) {
        expect($service->doBatchDeliverIssuedNotifications())->toBeTrue()
            ->and($replacements)->toBe($attempt)
            ->and($delivered)->toBe([$ready->getId()])
            ->and($service->getDi()['em']->isOpen())->toBeTrue()
            ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$held->getId()]))->toBe($checkpoint)
            ->and((int) $em->getConnection()->fetchOne('SELECT issue_notification_state FROM invoice WHERE id = ?', [$ready->getId()]))->toBe(0)
            ->and($em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
    }
    expect($em->isOpen())->toBeFalse();
})->with([[1, 1], [1, 2], [5, 1], [5, 6]]);
