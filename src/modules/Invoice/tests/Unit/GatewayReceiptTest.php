<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Service;
use Doctrine\ORM\EntityManager;

use function Tests\Helpers\container;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('gateway receipts are durable and duplicate provider payments do not credit twice', function (): void {
    [$service, $em, $client, $gateway, $transaction] = receiptTestContext();
    expect($service->recordGatewayPayment($transaction, $client, 'pi_123', 'Stripe transaction pi_123', 'transaction', (string) $transaction->getId()))->toBeTrue();
    $duplicate = (new Transaction())->setGateway($gateway)->setAmount('15.00')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $em->persist($duplicate);
    $em->flush();
    expect($service->recordGatewayPayment($duplicate, $client, 'pi_123', 'Stripe transaction pi_123', 'transaction', (string) $duplicate->getId()))->toBeFalse();
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15)
        ->and($transaction->getStatus())->toBe(Transaction::STATUS_PROCESSED);
});

test('an interrupted receipt transaction rolls back its wallet entry and can be retried', function (string $providerId, string $type): void {
    [$service, $em, $client, $gateway, $transaction] = receiptTestContext();
    $connection = $em->getConnection();
    $connection->executeStatement('CREATE TRIGGER fail_gateway_receipt BEFORE UPDATE OF status ON "transaction" BEGIN SELECT RAISE(ABORT, \'simulated interruption\'); END');
    $clientId = $client->getId();
    $txId = $transaction->getId();
    $description = $type . ' transaction ' . $providerId;
    expect(fn () => $service->recordGatewayPayment($transaction, $client, $providerId, $description, $type, (string) $txId))->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'simulated interruption');
    expect($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$txId]))->toBe(Transaction::STATUS_PROCESSING);
    $connection->executeStatement('DROP TRIGGER fail_gateway_receipt');
    $newEm = new EntityManager($connection, $em->getConfiguration());
    $di = container();
    $di['em'] = $newEm;
    $retry = new Service();
    $retry->setDi($di);
    $reloadedClient = $newEm->find(Client::class, $clientId);
    $reloadedTransaction = $newEm->find(Transaction::class, $txId);
    expect($retry->recordGatewayPayment($reloadedTransaction, $reloadedClient, $providerId, $description, $type, (string) $txId))->toBeTrue()
        ->and($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$txId]))->toBe(Transaction::STATUS_PROCESSED);
})->with(['stripe intent' => ['pi_retry', 'transaction'], 'paypal transaction' => ['PAYPAL-RETRY', 'PayPal'], 'stripe subscription invoice' => ['in_retry', 'transaction']]);

test('invoice receipt and settlement roll back together across provider retries', function (string $providerId, string $type): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(15)->setQuantity(1)->setCharged(false);
    $em->persist($item);
    $transaction->setInvoice($invoice);
    $em->flush();
    $clientId = $client->getId();
    $txId = $transaction->getId();
    $invoiceId = $invoice->getId();
    $description = $type . ' transaction ' . $providerId;
    $connection = $em->getConnection();
    $connection->executeStatement('CREATE TRIGGER fail_invoice BEFORE UPDATE OF status ON invoice BEGIN SELECT RAISE(ABORT, \'invoice interruption\'); END');
    $service = gatewayDepositTestService($em);
    expect(fn () => $service->recordGatewayPayment($transaction, $client, $providerId, $description, $type, (string) $txId))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'invoice interruption');
    expect($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_UNPAID)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$txId]))->toBe(Transaction::STATUS_PROCESSING);
    $connection->executeStatement('DROP TRIGGER fail_invoice');
    $restartedEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($restartedEm);
    expect($retry->recordGatewayPayment($restartedEm->find(Transaction::class, $txId), $restartedEm->find(Client::class, $clientId), $providerId, $description, $type, (string) $txId))->toBeTrue()
        ->and($connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(0)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_PAID);
})->with(['stripe one-time' => ['pi_restart', 'transaction'], 'paypal' => ['PAYPAL-RESTART', 'PayPal'], 'stripe subscription' => ['in_restart', 'transaction']]);

test('an incomplete gateway receipt stays reserved for its unpaid invoice', function (): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(15)->setQuantity(1)->setCharged(false);
    $em->persist($item);
    $transaction->setInvoice($invoice)->setAmount('10');
    $em->flush();
    $service = gatewayDepositTestService($em);

    expect($service->recordGatewayPayment($transaction, $client, 'pi_partial', 'Stripe transaction pi_partial', 'transaction', (string) $transaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(10)
        ->and($service->getReservedCredit((int) $client->getId()))->toBe(10.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('the database rejects duplicate provider receipt fingerprints', function (): void {
    [$service, $em, $client, , $transaction] = receiptTestContext();
    $service->recordGatewayPayment($transaction, $client, 'pi_unique', 'Stripe transaction pi_unique', 'transaction', (string) $transaction->getId());
    $receipt = $em->getRepository(ClientBalance::class)->findOneBy(['type' => 'transaction']);
    $duplicate = (new ClientBalance())->setClient($client)->setAmount('15')->setGatewayReceiptKey($receipt->getGatewayReceiptKey());
    $em->persist($duplicate);
    expect(fn () => $em->flush())->toThrow(Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
});

test('offline settlement consumes the earlier gateway receipt exactly once', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_offline_partial', 'Stripe transaction pi_offline_partial', 'transaction', (string) $transaction->getId());
    expect($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and((float) $offline->getAmount())->toBe(60.0)
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('partial provider receipts reprice the remaining gateway fee before the next payment', function (bool $taxed, float $expectedFee, float $remainderDue, float $totalPaid): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setName('Stripe')->setConfig(['fee' => ['percent' => 10, 'taxable' => $taxed]]);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')
        ->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setTaxrate($taxed ? 20 : 0);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setTaxed($taxed)->setCharged(false));
    $fee = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(Box\Mod\Invoice\ServiceGatewayFees::TASK)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setTitle('Stripe fee')->setPrice(10)->setQuantity(1)->setTaxed($taxed)->setCharged(false);
    $em->persist($fee);
    $transaction->setInvoice($invoice)->setAmount('50');
    $em->flush();
    $service = gatewayDepositTestService($em);

    expect($service->recordGatewayPayment($transaction, $client, 'pi_fee_part', 'Stripe transaction pi_fee_part', 'transaction', (string) $transaction->getId()))->toBeTrue();
    $fee = $em->getRepository(InvoiceItem::class)->findOneBy(['invoice' => $invoice, 'task' => Box\Mod\Invoice\ServiceGatewayFees::TASK]);
    expect($service->getBalanceDue($invoice))->toBe($remainderDue)
        ->and($service->getAmountPaid($invoice))->toBe(50.0)
        ->and((float) $fee->getPrice())->toBe($expectedFee);

    $remainder = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount((string) $remainderDue)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $em->persist($remainder);
    $em->flush();
    expect($service->recordGatewayPayment($remainder, $client, 'pi_fee_rest', 'Stripe transaction pi_fee_rest', 'transaction', (string) $remainder->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getAmountPaid($invoice))->toBe($totalPaid);
})->with([
    'untaxed fee' => [false, 5.0, 55.0, 105.0],
    'taxed fee and item' => [true, 7.0, 78.4, 128.4],
]);

test('failed fee repricing rolls back the partial provider receipt for a safe retry', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setName('Stripe')->setConfig(['fee' => ['percent' => 10]]);
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')
        ->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(Box\Mod\Invoice\ServiceGatewayFees::TASK)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setTitle('Stripe fee')->setPrice(10)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('50');
    $em->flush();
    $connection = $em->getConnection();
    $connection->executeStatement("CREATE TRIGGER fail_fee_reprice BEFORE DELETE ON invoice_item WHEN OLD.task = 'gateway_fee' BEGIN SELECT RAISE(ABORT, 'fee write interrupted'); END");
    $service = gatewayDepositTestService($em);

    expect(fn () => $service->recordGatewayPayment($transaction, $client, 'pi_fee_retry', 'Stripe transaction pi_fee_retry', 'transaction', (string) $transaction->getId()))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'fee write interrupted');
    expect($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$transaction->getId()]))->toBe(Transaction::STATUS_PROCESSING);

    $connection->executeStatement('DROP TRIGGER fail_fee_reprice');
    $retryEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($retryEm);
    $retryTx = $retryEm->find(Transaction::class, $transaction->getId());
    $retryClient = $retryEm->find(Client::class, $client->getId());
    $retryInvoice = $retryEm->find(Invoice::class, $invoice->getId());
    expect($retry->recordGatewayPayment($retryTx, $retryClient, 'pi_fee_retry', 'Stripe transaction pi_fee_retry', 'transaction', (string) $retryTx->getId()))->toBeTrue()
        ->and($retry->getBalanceDue($retryInvoice))->toBe(55.0)
        ->and($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1);
});

test('a partial staff payment and a gateway receipt settle without charging the manual amount to the wallet', function (): void {
    [, $em, $client, $gateway, $gatewayTransaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $manual = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('40')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSED);
    $gatewayTransaction->setInvoice($invoice)->setAmount('60');
    $em->persist($manual);
    $em->flush();
    $service = gatewayDepositTestService($em);

    expect($service->getReservedCredit((int) $client->getId()))->toBe(0.0);
    expect($service->recordGatewayPayment($gatewayTransaction, $client, 'pi_manual_mix', 'Stripe transaction pi_manual_mix', 'transaction', (string) $gatewayTransaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and((float) $em->getConnection()->fetchOne("SELECT SUM(amount) FROM client_balance WHERE type = 'invoice'"))->toBe(-60.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    expect($service->recordGatewayPayment($gatewayTransaction, $client, 'pi_manual_mix', 'Stripe transaction pi_manual_mix', 'transaction', (string) $gatewayTransaction->getId()))->toBeTrue()
        ->and((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM client_balance WHERE type = 'invoice'"))->toBe(1);
});

test('staff marking an invoice paid consumes its earlier gateway receipt', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_admin_partial', 'Stripe transaction pi_admin_partial', 'transaction', (string) $transaction->getId());
    $service->shouldReceive('validateAdminMarkAsPaidRequest')->andReturn($gateway);
    expect($service->markAsPaidByAdmin($invoice))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('a long PayPal provider id can identify an old receipt safely', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setGateway('PayPalEmail');
    $provider = 'PAYPAL-LONG-PROVIDER-TRANSACTION-IDENTIFIER';
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40')->setTxnId($provider);
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-paypal');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, $provider, 'PayPal transaction ' . $provider, 'PayPal', $provider);
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL WHERE gateway_receipt_key IS NOT NULL');
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('negative unpaid invoices cannot create spendable credit or reduce other reservations', function (bool $taxed): void {
    [, $em, $client] = receiptTestContext();
    $negative = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setTaxrate($taxed ? '20' : '0');
    $positive = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($negative);
    $em->persist($positive);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($negative)->setPrice(-50)->setQuantity(1)->setTaxed($taxed));
    $em->persist((new InvoiceItem())->setInvoice($positive)->setPrice(100)->setQuantity(1));
    $em->persist((new Box\Mod\Invoice\Entity\InvoiceCredit())->setInvoice($positive)->setClient($client)->setAmount('30'));
    $em->persist((new ClientBalance())->setClient($client)->setType('credit')->setAmount('100'));
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->getReservedCredit((int) $client->getId()))->toBe(30.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(70.0);
})->with([false, true]);

test('manual settlement keeps receipt overpayments as spendable credit across retries', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setPrice(100)->setQuantity(1)->setCharged(true));
    $em->persist((new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('80')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSED));
    $transaction->setInvoice($invoice)->setAmount('60')->setStatus(Transaction::STATUS_PROCESSED);
    $em->persist((new ClientBalance())->setClient($client)->setType('transaction')->setAmount('60')->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_manual_overpaid'))->setGatewayTransactionId($transaction->getId()));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('validateAdminMarkAsPaidRequest')->andReturn($gateway);
    expect($service->markAsPaidByAdmin($invoice))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(40.0);
    expect($service->markAsPaidByAdmin($invoice))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(40.0)
        ->and((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM client_balance WHERE type = 'invoice'"))->toBe(1);
});

test('claiming unfinished completion never releases a partial receipt reservation', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setPrice(100)->setQuantity(1));
    $transaction->setInvoice($invoice)->setAmount('15')->setStatus(Transaction::STATUS_PROCESSED)->setGatewayCompletionState(Transaction::GATEWAY_COMPLETION_RUNNING);
    $em->persist((new ClientBalance())->setClient($client)->setType('transaction')->setAmount('15')->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_claim'))->setGatewayTransactionId($transaction->getId()));
    $em->flush();
    $table = $em->getConnection()->quoteSingleIdentifier('transaction');
    $em->getConnection()->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $transaction->getId()]);
    $worker = new Box\Mod\Invoice\ServiceTransaction();
    $billing = gatewayDepositTestService($em);
    $worker->setDi($billing->getDi());
    expect($billing->getFreeCredit((int) $client->getId()))->toBe(0.0)
        ->and($worker->claimForProcessing((int) $transaction->getId()))->toBeTrue()
        ->and($worker->claimForProcessing((int) $transaction->getId()))->toBeFalse()
        ->and($billing->getFreeCredit((int) $client->getId()))->toBe(0.0)
        ->and($em->getConnection()->fetchOne("SELECT status FROM $table WHERE id = ?", [$transaction->getId()]))->toBe(Transaction::STATUS_PROCESSED);
});

test('retrying a completed partial receipt still runs completion when later manual funding settles its invoice', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setPrice(30)->setQuantity(1));
    $transaction->setInvoice($invoice)->setAmount('15');
    $em->flush();
    $billing = gatewayDepositTestService($em);
    $notifications = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$notifications): void { ++$notifications; });
    $billing->getDi()['event_dispatcher'] = $dispatcher;
    $billing->recordGatewayPayment($transaction, $client, 'pi_partial_finish', 'Stripe transaction pi_partial_finish', 'transaction');
    expect($transaction->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)->and($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID);
    $em->persist((new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('15')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSED));
    $em->flush();
    $billing->recordGatewayPayment($transaction, $client, 'pi_partial_finish', 'Stripe transaction pi_partial_finish', 'transaction');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_PAID)->and($notifications)->toBe(1)
        ->and($transaction->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)
        ->and($billing->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('local gateway completion validates its receipt before delivering payment work', function (string $mismatch): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID);
    $em->persist($invoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setStatus(Transaction::STATUS_PROCESSED)->setGatewayCompletionState(Transaction::GATEWAY_COMPLETION_PENDING);
    $receipt = (new ClientBalance())->setClient($client)->setAmount('15')->setGatewayTransactionId($transaction->getId())
        ->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_local_completion'));
    match ($mismatch) {
        'amount' => $receipt->setAmount('16'),
        'fingerprint' => $receipt->setGatewayReceiptKey(null),
        'currency' => $transaction->setCurrency('EUR'),
        'invoice owner' => $invoice->setClientId($client->getId() + 1),
        'receipt owner' => $receipt->setGatewayTransactionId($transaction->getId() + 1),
    };
    $em->persist($receipt);
    $em->flush();
    $billing = gatewayDepositTestService($em);
    $dispatcher = Mockery::mock(Symfony\Component\EventDispatcher\EventDispatcherInterface::class);
    $dispatcher->shouldNotReceive('dispatch');
    $billing->getDi()['event_dispatcher'] = $dispatcher;
    expect(fn () => $billing->completeGatewayPayment($transaction))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'committed receipt');
    expect($transaction->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_PENDING);
})->with(['amount', 'fingerprint', 'currency', 'invoice owner', 'receipt owner']);

test('local gateway completion keeps its mutex through listeners and clears stale diagnostics', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID);
    $em->persist($invoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setStatus(Transaction::STATUS_PROCESSED)->setGatewayCompletionState(Transaction::GATEWAY_COMPLETION_PENDING);
    $em->persist((new ClientBalance())->setClient($client)->setAmount('15')->setGatewayTransactionId($transaction->getId())
        ->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_completion_lock')));
    $em->flush();
    $billing = gatewayDepositTestService($em);
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $calls = 0;
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use ($em, $transaction, &$calls): void {
        ++$calls;
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($em->getConnection(), 'fb:gateway-completion:' . $transaction->getId(), 0))->toBeFalse();
        // Model a stale worker writing diagnostics outside this identity map.
        $em->getConnection()->update($em->getConnection()->quoteSingleIdentifier('transaction'), ['error' => 'completion busy', 'error_code' => 1], ['id' => $transaction->getId()]);
    });
    $billing->getDi()['event_dispatcher'] = $dispatcher;
    $billing->completeGatewayPayment($transaction);
    $billing->completeGatewayPayment($transaction);
    expect($calls)->toBe(1)->and($transaction->getError())->toBeNull()->and($transaction->getErrorCode())->toBeNull()
        ->and($transaction->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE);
});

test('offline completion survives listener failures and process crashes without settling twice', function (bool $crash): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setGateway('Custom');
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $item = (new InvoiceItem())->setInvoice($invoice)->setType('deposit')->setTask('void')->setPrice(15)->setQuantity(1)->setStatus('pending_payment')->setCharged(false);
    $em->persist($item);
    $transaction->setInvoice($invoice);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $calls = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$calls, $crash): void {
        if (++$calls === 1) {
            throw $crash ? new Error('crash after offline commit') : new RuntimeException('listener failure');
        }
    });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    expect(fn () => $service->approveOfflineTransaction($transaction))->toThrow($crash ? Error::class : RuntimeException::class);
    $id = $transaction->getId();
    $connection = $em->getConnection();
    expect($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$id]))->toBe('processed')
        ->and((int) $connection->fetchOne('SELECT gateway_completion_state FROM "transaction" WHERE id = ?', [$id]))->toBe(Transaction::GATEWAY_COMPLETION_RUNNING);
    $retryEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($retryEm);
    $di = $retry->getDi();
    $di['event_dispatcher'] = $dispatcher;
    $adapter = Mockery::mock();
    // Recovery must never call the adapter's approval/settlement again.
    $adapter->shouldNotReceive('approveTransaction');
    $gateways = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === 'PayGateway' ? $gateways : $services($module, $sub));
    $approver = new Box\Mod\Invoice\ServiceTransaction();
    $approver->setDi($di);
    $connection->executeStatement('UPDATE "transaction" SET updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 3600), $id]);
    $row = $retryEm->find(Transaction::class, $id);
    expect($approver->toApiArray($row)['completion_pending'])->toBeTrue();
    expect($approver->approveTransaction($row))->toBeTrue()
        ->and($row->getGatewayCompletionState())->toBe(Transaction::GATEWAY_COMPLETION_DONE)
        ->and($calls)->toBe(2)
        ->and($approver->toApiArray($row)['completion_pending'])->toBeFalse()
        ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(15.0)
        ->and($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('executed');
    expect($approver->approveTransaction($row))->toBeTrue()->and($calls)->toBe(2);
})->with([false, true]);

test('processed foreign currency payments cannot settle an invoice as local money', function (string $currency): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setStatus('processed')->setAmount('40')->setCurrency('USD');
    $em->persist((new Transaction())->setInvoice($invoice)->setGateway($gateway)->setStatus('processed')->setAmount('60')->setCurrency($currency));
    $em->flush();
    $sum = fn () => $em->getRepository(Transaction::class)->getProcessedSumsByInvoiceIds([$invoice->getId()]);
    if ($currency === 'usd') {
        expect($sum())->toBe([$invoice->getId() => 100.0]);
    } else {
        expect($sum)->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'currency');
    }
})->with(['JPY', 'usd', '']);

test('Stripe records a collected partial payment after checkout credit is removed', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(100)->setQuantity(1)->setStatus('pending_payment')->setCharged(false));
    // Checkout collected the remaining 60; the former 40 credit is now gone.
    $transaction->setInvoice($invoice)->setAmount('60');
    $em->flush();
    $service = gatewayDepositTestService($em);
    $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy']);
    $adapter->setDi($service->getDi());
    $apply = new ReflectionMethod($adapter, 'applyOneTimePayment');
    expect($apply->invoke($adapter, $transaction, $invoice, (object) ['id' => 'pi_partial_credit_removed']))->toBeTrue()
        ->and($invoice->getStatus())->toBe('unpaid')->and($service->getBalanceDue($invoice))->toBe(40.0);
    expect($apply->invoke($adapter, $transaction, $invoice, (object) ['id' => 'pi_partial_credit_removed']))->toBeTrue()
        ->and((float) $em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(60.0)
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1);
});

test('deposit quotes never advertise a fee that deposit settlement excludes', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid');
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('deposit')->setPrice(100)->setQuantity(1));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $fees = ($service->getDi()['mod_service'])('Invoice', 'GatewayFees');
    expect($fees->quote($invoice))->toBe(['base' => 0.0, 'fees' => []]);
});

test('staff transaction edits reject foreign currency settlement before mutating history', function (): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $transaction->setInvoice($invoice);
    $em->flush();
    $service = new Box\Mod\Invoice\ServiceTransaction();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->update($transaction, ['status' => 'processed', 'amount' => '60', 'currency' => 'JPY']))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'currency');
    expect($transaction->getCurrency())->toBe('USD')->and($transaction->getStatus())->toBe('processing');
    expect($service->update($transaction, ['status' => 'processed', 'amount' => '60', 'currency' => 'usd']))->toBeTrue()
        ->and($transaction->getCurrency())->toBe('usd');
});

test('automatic credit settlement rejects another wallet currency before fee or balance writes', function (): void {
    [, $em, $client] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('EUR')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(100)->setQuantity(1));
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('gateway_fee')->setPrice(3)->setQuantity(1));
    $em->persist((new ClientBalance())->setClient($client)->setAmount('103')->setType('credit'));
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect(fn () => $service->tryPayWithCredits($invoice))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'another currency');
    expect((float) $em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(103.0)
        ->and($em->getConnection()->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe('unpaid')
        ->and((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM invoice_item WHERE task = 'gateway_fee'"))->toBe(1);
});

test('historical currency mismatches still allow annotations but no financial edits', function (string $status): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('EUR')->setStatus($status)->setIssued(true);
    $em->persist($invoice);
    $transaction->setInvoice($invoice)->setStatus('processed');
    $em->flush();
    $service = new Box\Mod\Invoice\ServiceTransaction();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect($service->update($transaction, ['invoice_id' => $invoice->getId(), 'currency' => 'USD', 'amount' => '15.0', 'status' => 'processed', 'note' => 'Needs staff reconciliation', 'error' => 'Currency mismatch']))->toBeTrue()
        ->and($transaction->getNote())->toBe('Needs staff reconciliation');
    expect(fn () => $service->update($transaction, ['currency' => 'EUR']))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect($transaction->getCurrency())->toBe('USD');
})->with(['unpaid', 'refunded', 'canceled']);
