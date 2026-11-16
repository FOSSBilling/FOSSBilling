<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('cleared bank payments atomically record their receipt and pending marker', function (bool $paypal, bool $fail): void {
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $gateway->setGateway($paypal ? 'PayPalEmail' : 'Stripe');
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus(Invoice::STATUS_UNPAID);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(15)->setQuantity(1));
    $payment->setInvoice($invoice)->setTxnId($paypal ? 'capture_test' : 'pi_test')->setTxnStatus('processing');
    $clearing = $payment;
    if (!$paypal) {
        $clearing = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setTxnId('pi_test')->setStatus(Transaction::STATUS_RECEIVED)->setTxnStatus('processing');
        $em->persist($clearing);
    }
    $em->flush();
    $connection = $em->getConnection();
    $id = $clearing->getId();
    $service = gatewayDepositTestService($em);
    $notified = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use ($connection, &$notified): void {
        expect($connection->isTransactionActive())->toBeFalse();
        ++$notified;
    });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    if ($fail) {
        $service->shouldReceive('markAsPaid')->once()->andThrow(new RuntimeException('Settlement interrupted'));
        expect(fn () => $service->settleClearingPayment($invoice, $clearing, $payment, $paypal ? 'PayPal' : 'Stripe'))->toThrow(RuntimeException::class, 'Settlement interrupted');
        expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
            ->and($connection->fetchOne('SELECT txn_status FROM ' . $connection->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$id]))->toBe('processing')
            ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe('unpaid')
            ->and($notified)->toBe(0);
    } else {
        $service->settleClearingPayment($invoice, $clearing, $payment, $paypal ? 'PayPal' : 'Stripe');
        expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and($connection->fetchOne('SELECT txn_status FROM ' . $connection->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$id]))->toBe($paypal ? 'Completed' : 'succeeded')
            ->and($payment->getStatus())->toBe('processed')
            ->and($invoice->getStatus())->toBe('paid')
            ->and($notified)->toBe(1);
    }
})->with([false, true])->with([false, true]);

test('canonical settlement retains the legacy wallet reconciliation gate', function (): void {
    [, $em, $client, , $payment] = receiptTestContext();
    $payment->setCreditRecoveryState(null)->setProcessingToken(str_repeat('a', 32));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $connection = $em->getConnection();
    expect(fn () => FOSSBilling\Core\Payment\ProcessingFence::transaction(
        $connection, $payment->getId(), str_repeat('a', 32),
        fn () => $service->recordGatewayPayment($payment, $client, 'pi_legacy', 'Stripe transaction pi_legacy', 'transaction')
    ))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Reconcile');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
});

test('fee changes preserve the amount of a bank debit still clearing', function (): void {
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $gateway->setConfig(['fee' => ['fixed' => 90]]);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus('unpaid');
    $em->persist($invoice);
    $em->flush();
    $fee = (new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask(Box\Mod\Invoice\ServiceGatewayFees::TASK)->setPrice(2)->setQuantity(1);
    $em->persist($fee);
    $payment->setInvoice($invoice)->setStatus('received')->setTxnStatus('processing');
    $em->flush();
    $service = gatewayDepositTestService($em);
    $fees = ($service->getDi()['mod_service'])('Invoice', 'GatewayFees');
    $fees->apply($invoice, $gateway);
    $em->refresh($fee);
    expect((float) $fee->getPrice())->toBe(2.0);
});

test('disabling a gateway waits for its clearing payments without moving invoices', function (): void {
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $gateway->setEnabled(true);
    $replacement = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom')->setEnabled(true);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus('unpaid');
    $em->persist($replacement);
    $em->persist($invoice);
    $em->flush();
    $payment->setInvoice($invoice)->setStatus('received')->setTxnStatus('processing');
    $em->flush();
    $connection = $em->getConnection();
    $service = new Box\Mod\Invoice\ServicePayGateway();
    $service->setDi(gatewayDepositTestService($em)->getDi());
    expect(fn () => $service->update($gateway, ['enabled' => false, 'replacement_id' => $replacement->getId()]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'still clearing');
    expect((int) $connection->fetchOne('SELECT enabled FROM pay_gateway WHERE id = ?', [$gateway->getId()]))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT gateway_id FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe($gateway->getId());
});
