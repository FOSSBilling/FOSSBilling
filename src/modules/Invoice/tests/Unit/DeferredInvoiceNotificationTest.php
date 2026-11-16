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
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setNr('42');
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

test('a deferred email is queued once when a later listener repeatedly fails', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setIssueNotificationState(2);
    $em->persist($invoice);
    $em->flush();
    $email = Mockery::mock(Box\Mod\Email\Service::class);
    $email->shouldReceive('sendTemplate')->once()->andReturn(true);
    $service = Mockery::mock(Service::class)->makePartial();
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
    expect(fn () => $service->notifyDeferredInvoiceIssued($invoice))->toThrow(RuntimeException::class);
    expect($invoice->getIssueNotificationState())->toBe(6);
    $service->doBatchDeliverIssuedNotifications();
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
