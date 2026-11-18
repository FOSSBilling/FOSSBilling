<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Service;

use function Tests\Helpers\container;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('Phase A partial refunds preserve main net income and gross wallet accounting', function (bool $toCredit, float $baseRate, string $clientCurrency): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true)->setTaxrate('20');
    $em->persist($invoice);
    $em->flush();
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setPrice(50)->setQuantity(2)->setTaxed(true)->setCharged(true);
    $em->persist($item);
    $em->flush();
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')->andReturnUsing(fn (string $key, mixed $default = null): mixed => match ($key) {
        'invoice_refund_logic' => 'credit_note',
        'invoice_hash_lifetime_days' => 90,
        'invoice_cn_series' => 'CN-',
        default => $default,
    });
    $number = 0;
    $system->shouldReceive('reserveNextNumericParamValue')->andReturnUsing(function () use (&$number): int { return ++$number; });
    $currency = Mockery::mock(Box\Mod\Currency\Service::class);
    $currency->shouldReceive('toBaseCurrency')->andReturnUsing(fn (string $code, float|int $amount): float => $amount * $baseRate);
    $service = Mockery::mock(Service::class)->makePartial();
    $journal = $service->shouldReceive('recordJournalEvent');
    if (!$toCredit || $clientCurrency !== 'EUR') {
        $journal->twice();
    }
    $service->shouldReceive('addNote')->andReturn(true);
    $service->shouldReceive('toApiArray')->andReturn([]);
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($module) {
        'system' => $system,
        'currency' => $currency,
        'Invoice' => $sub === 'InvoiceItem' ? $itemService : throw new LogicException('Unexpected subservice: ' . $sub),
        default => throw new LogicException('Unexpected module: ' . $module),
    });
    $service->setDi($di);
    $itemService->setDi($di);
    $client->setCurrency($clientCurrency);
    $em->flush();
    $options = ['to_credit' => $toCredit, 'gateway_refund' => false];
    if ($toCredit && $clientCurrency === 'EUR') {
        $connection = $em->getConnection();
        expect(fn () => $service->refundInvoice($invoice, null, [$item->getId() => 1], $options))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'currency');
        expect($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(Invoice::STATUS_PAID)
            ->and((float) $connection->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(0.0)
            ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
            ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);

        return;
    }
    $firstId = $service->refundInvoice($invoice, null, [$item->getId() => 1], $options);
    expect($em->find(Invoice::class, $firstId)->getCreditNoteForInvoiceId())->toBe($invoice->getId())
        ->and((float) $invoice->getRefund())->toBe(60.0)
        ->and((float) $invoice->getBaseIncome())->toBe(100.0 * $baseRate)
        ->and((float) $invoice->getBaseRefund())->toBe(50.0 * $baseRate)
        ->and((float) $invoice->getBaseRefundTax())->toBe(10.0 * $baseRate);
    $service->refundInvoice($invoice, null, [$item->getId() => 1], $options);
    expect($invoice->getStatus())->toBe(Invoice::STATUS_REFUNDED)
        ->and((float) $invoice->getRefund())->toBe(120.0)
        ->and((float) $invoice->getBaseRefund())->toBe(100.0 * $baseRate)
        ->and((float) $invoice->getBaseRefundTax())->toBe(20.0 * $baseRate)
        ->and($em->getRepository(ClientBalance::class)->getClientBalanceSum((int) $client->getId()))->toBe($toCredit ? 120.0 : 0.0);
    expect(fn () => $service->refundInvoice($invoice, null, null, $options))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect($em->getRepository(Invoice::class)->count(['creditNoteForInvoiceId' => $invoice->getId()]))->toBe(2);
})->with([true, false])->with([1.0, 2.0])->with(['USD', 'usd', 'EUR']);

function externalReversalTestContext(bool $deposit = false, ?Doctrine\DBAL\Connection $db = null): array
{
    [, $em, $client, $gateway] = receiptTestContext($db);
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceEvent::class)]);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType($deposit ? InvoiceItem::TYPE_DEPOSIT : InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setPrice(100)->setQuantity(1)->setTaxed(false)->setCharged(true));
    if ($deposit) {
        $em->persist((new ClientBalance())->setClient($client)->setAmount('100.00')->setType('deposit'));
    }
    $em->flush();
    $service = externalReversalTestService($em);
    $di = $service->getDi();
    $options = ['gateway_refund' => false, 'gateway_returned' => true,
        'custom_line' => ['title' => 'Partial reversal', 'amount' => 25.0],
        'external_reversal' => ['gateway_id' => (int) $gateway->getId(), 'payment_id' => 'P-original', 'reversal_id' => 'R-once', 'amount' => 25.0]];

    return compact('service', 'em', 'client', 'invoice', 'options', 'di', 'gateway');
}

function externalReversalTestService(Doctrine\ORM\EntityManager $em): Service
{
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')->andReturnUsing(fn (string $key, mixed $default = null): mixed => match ($key) {
        'invoice_refund_logic' => 'credit_note', 'invoice_hash_lifetime_days' => 90, 'invoice_cn_series' => 'CN-', default => $default,
    });
    $number = 0;
    $system->shouldReceive('reserveNextNumericParamValue')->andReturnUsing(function () use (&$number): int { return ++$number; });
    $currency = Mockery::mock(Box\Mod\Currency\Service::class);
    $currency->shouldReceive('toBaseCurrency')->andReturnUsing(fn (string $code, float|int $amount): float => (float) $amount);
    $balance = new Box\Mod\Client\ServiceBalance();
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('addNote')->andReturn(true);
    $service->shouldReceive('toApiArray')->andReturn([]);
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ([$module, $sub]) {
        ['system', ''] => $system, ['currency', ''] => $currency,
        ['Invoice', 'InvoiceItem'] => $itemService, ['Client', 'Balance'] => $balance,
        default => throw new LogicException('Unexpected refund test service'),
    });
    foreach ([$service, $itemService, $balance] as $module) {
        $module->setDi($di);
    }

    return $service;
}

test('partial reversal receipts prevent replayed credit notes and deposit debits', function (bool $deposit): void {
    $c = externalReversalTestContext($deposit);
    $replayInvoice = $c['invoice'];
    $id = $c['service']->refundInvoice($c['invoice'], 'PayPal reversal', null, $c['options']);
    // An IPN acknowledgement can be lost after the complete refund transaction.
    expect($c['service']->refundInvoice($replayInvoice, 'PayPal reversal retry', null, $c['options']))->toBe($id);
    $db = $c['em']->getConnection();
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice WHERE credit_note_for_invoice_id = ?', [$c['invoice']->getId()]))->toBe(1)
        ->and((float) $db->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$c['invoice']->getId()]))->toBe(25.0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_event WHERE invoice_id = ?', [$c['invoice']->getId()]))->toBe(1)
        ->and((float) ($db->fetchOne('SELECT SUM(amount) FROM client_balance') ?: 0))->toBe($deposit ? 75.0 : 0.0);
    $next = $c['options'];
    $next['external_reversal']['reversal_id'] = 'R-distinct';
    expect($c['service']->refundInvoice($c['invoice'], null, null, $next))->not->toBe($id)
        ->and((float) $db->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$c['invoice']->getId()]))->toBe(50.0);
})->with([false, true]);

test('a reversal receipt failure rolls back the credit note and deposit debit', function (): void {
    $c = externalReversalTestContext(true);
    $db = $c['em']->getConnection();
    $db->executeStatement("CREATE TRIGGER reject_receipt BEFORE INSERT ON invoice_event BEGIN SELECT RAISE(ABORT, 'receipt failed'); END");
    expect(fn () => $c['service']->refundInvoice($c['invoice'], null, null, $c['options']))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and((float) $db->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$c['invoice']->getId()]))->toBe(0.0)
        ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(100.0)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_event'))->toBe(0);
});

test('an existing reversal rejects conflicting payment details and missing credit notes', function (string $change): void {
    $c = externalReversalTestContext();
    $id = $c['service']->refundInvoice($c['invoice'], null, null, $c['options']);
    $next = $c['options'];
    if ($change === 'amount') {
        $next['external_reversal']['amount'] = 30.0;
    } elseif ($change === 'payment') {
        $next['external_reversal']['payment_id'] = 'P-other';
    } else {
        $c['em']->getConnection()->delete('invoice', ['id' => $id]);
        $c['em']->clear();
    }
    expect(fn () => $c['service']->refundInvoice($c['invoice'], null, null, $next))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect((float) $c['em']->getConnection()->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$c['invoice']->getId()]))->toBe(25.0);
})->with(['amount', 'payment', 'missing credit note']);

test('independent reversal deliveries reuse the committed receipt despite a stale second manager', function (): void {
    $fs = new Symfony\Component\Filesystem\Filesystem();
    $path = $fs->tempnam(sys_get_temp_dir(), 'paypal-reversal-');
    $first = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $c = externalReversalTestContext(true, $first);
    $second = Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
    $secondEm = new Doctrine\ORM\EntityManager($second, $c['em']->getConfiguration());
    $secondService = externalReversalTestService($secondEm);
    $stale = $secondEm->find(Invoice::class, $c['invoice']->getId());

    try {
        $id = $c['service']->refundInvoice($c['invoice'], null, null, $c['options']);
        expect((float) $stale->getRefund())->toBe(0.0);
        expect($secondService->refundInvoice($stale, null, null, $c['options']))->toBe($id)
            ->and((int) $second->fetchOne('SELECT COUNT(*) FROM invoice WHERE credit_note_for_invoice_id = ?', [$c['invoice']->getId()]))->toBe(1)
            ->and((float) $second->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(75.0);
    } finally {
        $first->close();
        $second->close();
        $fs->remove($path);
    }
});
