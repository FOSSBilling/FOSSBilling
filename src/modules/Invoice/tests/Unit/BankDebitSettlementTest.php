<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Symfony\Component\EventDispatcher\EventDispatcher;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('cleared bank payments atomically record their receipt and pending marker', function (string $gatewayCode, string $failure, bool $locallyFailed): void {
    $paypal = $gatewayCode !== 'Stripe';
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $gateway->setGateway($gatewayCode);
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
    if ($locallyFailed) {
        $clearing->setStatus(Transaction::STATUS_ERROR);
    }
    $em->flush();
    $connection = $em->getConnection();
    $id = $clearing->getId();
    $attempt = Box\Mod\Invoice\GatewayChargeAttempts::begin($connection, (int) $invoice->getId(), (int) $gateway->getId());
    Box\Mod\Invoice\GatewayChargeAttempts::recordPayment($connection, $attempt, (string) $payment->getTxnId());
    $service = gatewayDepositTestService($em);
    $notified = 0;
    $dispatcher = new EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use ($connection, $attempt, &$notified): void {
        expect($connection->isTransactionActive())->toBeFalse()
            ->and($connection->fetchOne('SELECT state FROM invoice_gateway_charge_attempt WHERE id = ?', [$attempt]))->toBe('settled');
        ++$notified;
    });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    $settle = function () use ($paypal, $gatewayCode, $service, $invoice, $clearing, $payment, $gateway, $em): void {
        if ($gatewayCode === 'PayPalAutoPay') {
            $adapter = new Payment_Adapter_PayPalAutoPay(['api_username' => 'test', 'api_password' => 'test', 'api_signature' => 'test', 'gateway_id' => $gateway->getId()]);
            $adapter->setDi($service->getDi());
            $api = Mockery::mock();
            $api->shouldReceive('invoice_transaction_update')->once()->andReturnUsing(function (array $row) use ($payment, $em): void {
                $payment->setTxnStatus($row['txn_status']);
                $em->flush();
            });
            (new ReflectionMethod($adapter, 'applyPayment'))->invoke($adapter, $api, (int) $payment->getId(), (string) $payment->getTxnId(), $invoice, 15.0, 'USD', [], '');

            return;
        }
        if ($paypal) {
            $service->settleClearingPayment($invoice, $clearing, $payment, 'PayPal');

            return;
        }
        $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'gateway_id' => $gateway->getId()]);
        $adapter->setDi($service->getDi());
        $intent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_test', 'status' => 'succeeded', 'amount' => 1500, 'currency' => 'usd',
            'metadata' => ['invoice_id' => (string) $invoice->getId()],
        ]);
        (new ReflectionMethod($adapter, 'handlePaymentIntentSucceededWebhook'))->invoke($adapter, null, $payment, (object) ['data' => (object) ['object' => $intent]], (int) $gateway->getId());
    };

    if ($failure !== 'none') {
        if ($failure === 'settlement') {
            $service->shouldReceive('markAsPaid')->once()->andThrow(new RuntimeException('Settlement interrupted'));
        } elseif ($failure === 'journal') {
            $connection->executeStatement("CREATE TRIGGER fail_resolution BEFORE UPDATE OF state ON invoice_gateway_charge_attempt BEGIN SELECT RAISE(ABORT, 'Journal interrupted'); END");
        } else {
            $balanceCalls = 0;
            $service->shouldReceive('getBalanceDue')->andReturnUsing(function () use ($connection, $attempt, $gatewayCode, &$balanceCalls): float {
                if (++$balanceCalls > ($gatewayCode === 'PayPalAutoPay' ? 1 : 0)) {
                    $connection->update('invoice_gateway_charge_attempt', ['owner_token' => str_repeat('c', 32)], ['id' => $attempt]);
                }

                return 15.0;
            });
        }
        expect($settle)->toThrow(match ($failure) {
            'settlement' => RuntimeException::class,
            'journal' => Doctrine\DBAL\Exception\DriverException::class,
            default => FOSSBilling\Core\Payment\OwnershipLost::class,
        });
        expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
            ->and($connection->fetchOne('SELECT txn_status FROM ' . $connection->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$id]))->toBe('processing')
            ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe('unpaid')
            ->and($connection->fetchOne('SELECT state FROM invoice_gateway_charge_attempt WHERE id = ?', [$attempt]))->toBe('pending')
            ->and($notified)->toBe(0);
    } else {
        $settle();
        expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and($connection->fetchOne('SELECT txn_status FROM ' . $connection->quoteSingleIdentifier('transaction') . ' WHERE id = ?', [$id]))->toBe($paypal ? 'Completed' : 'succeeded')
            ->and($payment->getStatus())->toBe('processed')
            ->and($invoice->getStatus())->toBe('paid')
            ->and($connection->fetchOne('SELECT state FROM invoice_gateway_charge_attempt WHERE id = ?', [$attempt]))->toBe('settled')
            ->and($notified)->toBe(1);
    }
})->with(['Stripe', 'PayPalEmail', 'PayPalAutoPay'])->with([['none', false], ['settlement', false], ['journal', false], ['owner', false], ['none', true]]);

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

test('clearing settlement preserves unrelated attempts and an enclosing charge claim', function (string $scenario): void {
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus(Invoice::STATUS_UNPAID);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(15)->setQuantity(1));
    $payment->setInvoice($invoice)->setTxnId('pi_test')->setTxnStatus('processing');
    $em->flush();
    $db = $em->getConnection();
    $attempt = Box\Mod\Invoice\GatewayChargeAttempts::begin($db, (int) $invoice->getId(), (int) $gateway->getId(), $owner);
    Box\Mod\Invoice\GatewayChargeAttempts::recordPayment($db, $attempt, 'pi_test');
    $changes = match ($scenario) {
        'invoice' => ['invoice_id' => 999],
        'gateway' => ['gateway_id' => 999],
        'reference' => ['payment_id' => 'pi_other'],
        'unknown reference' => ['payment_id' => null],
        'legacy owner' => ['owner_token' => null],
        default => [],
    };
    if ($changes !== []) {
        $db->update('invoice_gateway_charge_attempt', $changes, ['id' => $attempt]);
    }
    $service = gatewayDepositTestService($em);
    $settle = fn () => $service->settleClearingPayment($invoice, $payment, $payment);
    if ($scenario === 'enclosing charge') {
        FOSSBilling\Core\Payment\ProcessingFence::charge($db, $attempt, $owner, function () use ($settle, $db): void {
            $settle();
            // A subsequent fenced query must still be authorized.
            expect($db->fetchOne('SELECT 1'))->toBe(1);
        });
    } elseif ($scenario === 'direct settlement') {
        // Cron can observe the succeeded provider payment before its webhook.
        $payment->setTxnStatus('succeeded');
        $service->recordGatewayPayment($payment, $client, 'pi_test', 'Stripe transaction pi_test', 'transaction', (string) $payment->getId());
    } else {
        $settle();
    }
    expect($invoice->getStatus())->toBe('paid')
        ->and($db->fetchOne('SELECT state FROM invoice_gateway_charge_attempt WHERE id = ?', [$attempt]))->toBe(in_array($scenario, ['legacy owner', 'direct settlement'], true) ? 'settled' : 'pending');
})->with(['invoice', 'gateway', 'reference', 'unknown reference', 'legacy owner', 'enclosing charge', 'direct settlement']);

test('a receipt retry resolves only the invoice retained by the canonical payment', function (): void {
    [, $em, $client, $gateway, $payment] = receiptTestContext();
    $original = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus('unpaid');
    $em->persist($original);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($original)->setType('custom')->setTask('void')->setPrice(15)->setQuantity(1));
    $payment->setInvoice($original)->setTxnId('pi_test')->setTxnStatus('succeeded');
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($payment, $client, 'pi_test', 'Stripe transaction pi_test', 'transaction');
    $other = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setIssued(true)->setStatus('unpaid');
    $em->persist($other);
    $em->flush();
    $db = $em->getConnection();
    $attempt = Box\Mod\Invoice\GatewayChargeAttempts::begin($db, (int) $other->getId(), (int) $gateway->getId());
    Box\Mod\Invoice\GatewayChargeAttempts::recordPayment($db, $attempt, 'pi_test');
    $payment->setInvoice($other);
    $service->recordGatewayPayment($payment, $client, 'pi_test', 'Stripe transaction pi_test', 'transaction');
    expect($payment->getInvoice()->getId())->toBe($original->getId())
        ->and($other->getStatus())->toBe('unpaid')
        ->and($db->fetchOne('SELECT state FROM invoice_gateway_charge_attempt WHERE id = ?', [$attempt]))->toBe('pending');
});
