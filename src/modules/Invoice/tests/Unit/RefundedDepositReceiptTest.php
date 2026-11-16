<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Service;
use Doctrine\ORM\EntityManager;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';
require_once PATH_TESTS . '/Helpers/DeferredPayPal.php';

/** Exercise the provider callback handlers, including their binding checks. */
function refundedDepositCallback(Service $service, EntityManager $em, int $transactionId, string $provider): void
{
    $tx = $em->find(Transaction::class, $transactionId);
    $invoice = $tx->getInvoice();
    $gatewayId = (int) $tx->getGateway()->getId();
    if ($provider === 'Stripe') {
        $secret = 'whsec_refunded_deposit';
        $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'test_webhook_secret' => $secret, 'gateway_id' => $gatewayId]);
        $adapter->setDi($service->getDi());
        $payload = json_encode(['id' => 'evt_refunded_deposit', 'object' => 'event', 'type' => 'payment_intent.succeeded', 'data' => ['object' => [
            'id' => 'pi_refunded_deposit', 'object' => 'payment_intent', 'status' => 'succeeded', 'amount' => 10000, 'currency' => 'usd',
            'metadata' => ['invoice_id' => (string) $invoice->getId(), 'client_id' => (string) $invoice->getClientId(), 'gateway_id' => (string) $gatewayId],
        ]]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $data = ['http_raw_post_data' => $payload, 'server' => ['HTTP_STRIPE_SIGNATURE' => 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret)]];
        (new ReflectionMethod($adapter, 'processWebhookEvent'))->invoke($adapter, new stdClass(), $tx, $data, $gatewayId);

        return;
    }
    $client = $em->find(Box\Mod\Client\Entity\Client::class, $invoice->getClientId());
    $api = deferredPayPalApi(['em' => $em, 'connection' => $em->getConnection(), 'client' => $client, 'di' => $service->getDi()]);
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapter->setDi($service->getDi());
    $payload = [
        'get' => ['invoice_id' => $invoice->getId(), 'sig' => FOSSBilling\Core\Security\Credential::signCallbackParams($gatewayId, $invoice->getId())],
        'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed', 'txn_id' => 'PAYPAL-REFUNDED-DEPOSIT', 'mc_gross' => '100.00', 'mc_currency' => 'USD'],
    ];
    $adapter->processTransaction($api, $transactionId, $payload, $gatewayId);
}

test('refunded deposits retain callback receipts and recover interruptions without settling again', function (string $provider, string $interruption): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setGateway($provider);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setNr('42')->setIssued(true)->setStatus(Invoice::STATUS_REFUNDED)->setRefund(100);
    $em->persist($invoice);
    $em->flush();
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)->setPrice(100)->setQuantity(1)->setCharged(true)->setStatus(InvoiceItem::STATUS_EXECUTED);
    $em->persist($item);
    $original = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setCurrency('USD')->setAmount('100')->setStatus(Transaction::STATUS_PROCESSED);
    $em->persist($original);
    $em->flush();
    $em->persist((new ClientBalance())->setClient($client)->setAmount('100')->setType('transaction')->setGatewayTransactionId($original->getId())->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':original')));
    $em->persist((new ClientBalance())->setClient($client)->setAmount('-100')->setType('refund'));
    $transaction->setInvoice($invoice)->setAmount('100');
    $em->flush();
    $id = (int) $transaction->getId();
    $invoiceId = (int) $invoice->getId();
    $itemId = (int) $item->getId();
    $clientId = (int) $client->getId();
    $connection = $em->getConnection();
    $table = $connection->quoteSingleIdentifier('transaction');
    $service = gatewayDepositTestService($em);
    $paidEvents = 0;
    $events = new Symfony\Component\EventDispatcher\EventDispatcher();
    $events->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$paidEvents): void { ++$paidEvents; });
    $service->getDi()['event_dispatcher'] = $events;
    if ($interruption === 'before commit') {
        $connection->executeStatement('CREATE TRIGGER interrupt_deposit_receipt BEFORE INSERT ON client_balance BEGIN SELECT RAISE(ABORT, \'receipt interrupted\'); END');
        expect(fn () => refundedDepositCallback($service, $em, $id, $provider))->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'receipt interrupted');
        expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_transaction_id = ?', [$id]))->toBe(0)
            ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);
        $connection->executeStatement('DROP TRIGGER interrupt_deposit_receipt');
    } elseif ($interruption === 'after commit') {
        $service->shouldReceive('completeGatewayPayment')->once()->andThrow(new Error('worker stopped after receipt commit'));
        expect(fn () => refundedDepositCallback($service, $em, $id, $provider))->toThrow(Error::class, 'worker stopped after receipt commit');
        expect($connection->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe(Transaction::STATUS_PROCESSED)
            ->and((int) $connection->fetchOne("SELECT gateway_completion_state FROM $table WHERE id = ?", [$id]))->toBe(Transaction::GATEWAY_COMPLETION_RUNNING)
            ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_transaction_id = ?', [$id]))->toBe(1)
            ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(100.0);
    }
    if ($interruption === 'stale invoice') {
        // Another worker refunds after this manager has loaded the unpaid invoice.
        $invoice->setStatus(Invoice::STATUS_UNPAID);
        $em->flush();
        $connection->update('invoice', ['status' => Invoice::STATUS_REFUNDED], ['id' => $invoiceId]);
        refundedDepositCallback($service, $em, $id, $provider);
    }
    // A fresh worker must recover the durable receipt without reapplying the deposit.
    $fresh = new EntityManager($connection, $em->getConfiguration());
    $service = gatewayDepositTestService($fresh);
    $service->getDi()['event_dispatcher'] = $events;
    if ($interruption === 'after commit') {
        $connection->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $id]);
        $worker = new Box\Mod\Invoice\ServiceTransaction();
        $worker->setDi($service->getDi());
        $worker->processAndCatchErrors($id);
    } else {
        refundedDepositCallback($service, $fresh, $id, $provider);
    }
    // Canonical replay and a separate duplicate delivery both retain one incoming receipt.
    refundedDepositCallback($service, $fresh, $id, $provider);
    $canonical = $fresh->find(Transaction::class, $id);
    $duplicate = (new Transaction())->setInvoice($fresh->find(Invoice::class, $invoiceId))->setGateway($canonical->getGateway())->setAmount('100')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $fresh->persist($duplicate);
    $fresh->flush();
    refundedDepositCallback($service, $fresh, (int) $duplicate->getId(), $provider);
    expect($fresh->find(Invoice::class, $invoiceId)->getStatus())->toBe(Invoice::STATUS_REFUNDED)
        ->and((float) $fresh->find(Invoice::class, $invoiceId)->getRefund())->toBe(100.0)
        ->and($fresh->find(Invoice::class, $invoiceId)->getNr())->toBe('42')
        ->and($fresh->find(InvoiceItem::class, $itemId)->getStatus())->toBe(InvoiceItem::STATUS_EXECUTED)
        ->and($canonical->getStatus())->toBe(Transaction::STATUS_PROCESSED)
        ->and($canonical->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_transaction_id = ?', [$id]))->toBe(1)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(3)
        ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(100.0)
        ->and($service->getFreeCredit($clientId))->toBe(100.0)
        ->and($paidEvents)->toBe(0);
})->with(['Stripe', 'PayPalEmail'])->with(['none', 'before commit', 'after commit', 'stale invoice']);
