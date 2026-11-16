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
