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
    $offline = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline');
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
    expect($service->recordGatewayPayment($gatewayTransaction, $client, 'pi_manual_mix', 'Stripe transaction pi_manual_mix', 'transaction', (string) $gatewayTransaction->getId()))->toBeFalse()
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
    $offline = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-paypal');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, $provider, 'PayPal transaction ' . $provider, 'PayPal', $provider);
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL WHERE gateway_receipt_key IS NOT NULL');
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});
