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

test('a paid deposit credits its wallet exactly once even when the callback repeats', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $deposit = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(15)->setQuantity(1)->setCharged(true);
    $em->persist($deposit);
    $transaction->setInvoice($invoice);
    $em->flush();
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('commitReservedPromoRedemptionsForInvoice')->once();
    $currencyRepository = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->with('USD')->andReturn(1.0);
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository')->andReturn($currencyRepository);
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('countIncome')->once();
    $service->shouldReceive('recordJournalEvent')->once();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ([$module, $sub]) {
        ['Invoice', 'InvoiceItem'] => $itemService,
        ['Product', ''] => $productService,
        ['currency', ''] => $currencyService,
        ['Order', ''] => Mockery::mock(Box\Mod\Order\Service::class),
    });
    $itemService->setDi($di);
    $service->setDi($di);

    expect($service->recordGatewayPayment($transaction, $client, 'pi_deposit', 'Stripe transaction pi_deposit', 'transaction', (string) $transaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15);
    $duplicate = (new Transaction())->setGateway($gateway)->setAmount('15')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setInvoice($invoice);
    $em->persist($duplicate);
    $em->flush();
    expect($service->recordGatewayPayment($duplicate, $client, 'pi_deposit', 'Stripe transaction pi_deposit', 'transaction', (string) $duplicate->getId()))->toBeFalse()
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15);
});

test('a mixed deposit invoice credits only the deposit after settlement', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $deposit = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(15)->setQuantity(1)->setCharged(true);
    $em->persist($deposit);
    $serviceItem = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(7)->setTitle('Service')->setQuantity(1)->setCharged(false);
    $em->persist($serviceItem);
    $transaction->setAmount('22');
    $transaction->setInvoice($invoice);
    $em->flush();
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('commitReservedPromoRedemptionsForInvoice')->once();
    $currencyRepository = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepository->shouldReceive('getRateByCode')->with('USD')->andReturn(1.0);
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository')->andReturn($currencyRepository);
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('countIncome')->once();
    $service->shouldReceive('recordJournalEvent')->once();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ([$module, $sub]) {
        ['Invoice', 'InvoiceItem'] => $itemService,
        ['Invoice', ''] => $service,
        ['Product', ''] => $productService,
        ['currency', ''] => $currencyService,
        ['Order', ''] => Mockery::mock(Box\Mod\Order\Service::class),
    });
    $itemService->setDi($di);
    $service->setDi($di);

    expect($service->recordGatewayPayment($transaction, $client, 'pi_deposit', 'Stripe transaction pi_deposit', 'transaction', (string) $transaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15);
    $duplicate = (new Transaction())->setGateway($gateway)->setAmount('22')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setInvoice($invoice);
    $em->persist($duplicate);
    $em->flush();
    expect($service->recordGatewayPayment($duplicate, $client, 'pi_deposit', 'Stripe transaction pi_deposit', 'transaction', (string) $duplicate->getId()))->toBeFalse()
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15);
});

test('a deposit receipt and paid state roll back together and retry once after interruption', function (): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $deposit = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(15)->setQuantity(1)->setCharged(true);
    $em->persist($deposit);
    $transaction->setInvoice($invoice);
    $em->flush();
    $clientId = $client->getId();
    $txId = $transaction->getId();
    $invoiceId = $invoice->getId();
    $connection = $em->getConnection();
    $connection->executeStatement('CREATE TRIGGER fail_deposit BEFORE UPDATE OF status ON invoice BEGIN SELECT RAISE(ABORT, \'deposit interruption\'); END');
    $service = gatewayDepositTestService($em);
    expect(fn () => $service->recordGatewayPayment($transaction, $client, 'pi_deposit_retry', 'Stripe transaction pi_deposit_retry', 'transaction', (string) $txId))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'deposit interruption');
    expect($connection->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_UNPAID)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$txId]))->toBe(Transaction::STATUS_PROCESSING);
    $connection->executeStatement('DROP TRIGGER fail_deposit');
    $restartedEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($restartedEm);
    $retryClient = $restartedEm->find(Client::class, $clientId);
    $retryTx = $restartedEm->find(Transaction::class, $txId);
    expect($retry->recordGatewayPayment($retryTx, $retryClient, 'pi_deposit_retry', 'Stripe transaction pi_deposit_retry', 'transaction', (string) $txId))->toBeTrue()
        ->and($connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(15)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_PAID);
});

test('partial deposit receipts stay reserved until the invoice is fully paid', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(true));
    $transaction->setInvoice($invoice)->setAmount('5');
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->recordGatewayPayment($transaction, $client, 'pi_deposit_partial', 'Stripe transaction pi_deposit_partial', 'transaction', (string) $transaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($service->getReservedCredit((int) $client->getId()))->toBe(5.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    $remainder = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('95')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $em->persist($remainder);
    $em->flush();
    expect($service->recordGatewayPayment($remainder, $client, 'pi_deposit_rest', 'Stripe transaction pi_deposit_rest', 'transaction', (string) $remainder->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0);
});

test('offline settlement of a mixed deposit consumes prior gateway funds and credits only the deposit', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(80)->setQuantity(1)->setCharged(true));
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(20)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-mixed');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_mixed_partial', 'Stripe transaction pi_mixed_partial', 'transaction', (string) $transaction->getId());
    expect($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and((float) $offline->getAmount())->toBe(60.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(80.0);
});

test('mixed deposit allocates aggregate tax consistently for gateway payments', function (float $price, string $received): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setTaxrate(10);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(true)->setTaxed(false));
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice($price)->setQuantity(2)->setCharged(false)->setTaxed(true));
    $transaction->setInvoice($invoice)->setAmount($received);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->recordGatewayPayment($transaction, $client, 'pi_tax_' . $received, 'Stripe transaction pi_tax_' . $received, 'transaction', (string) $transaction->getId()))->toBeTrue()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0);
})->with(['rounded up per item' => [0.05, '100.11'], 'rounded down per item' => [0.04, '100.09']]);

test('unlinked partial deposit receipts stay reserved before reconciliation', function (bool $keyed): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(true));
    $transaction->setInvoice($invoice)->setAmount('40');
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_old_deposit', 'Stripe transaction pi_old_deposit', 'transaction', (string) $transaction->getId());
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL' . ($keyed ? '' : ', gateway_receipt_key = NULL') . ' WHERE gateway_receipt_key IS NOT NULL');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($service->getReservedCredit((int) $client->getId()))->toBe(40.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
})->with(['keyed' => true, 'before fingerprints' => false]);

test('staff settlement allocates aggregate tax consistently for mixed deposits', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setTaxrate(10);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(true)->setTaxed(false));
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(0.05)->setQuantity(2)->setCharged(false)->setTaxed(true));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-tax');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_tax_offline', 'Stripe transaction pi_tax_offline', 'transaction', (string) $transaction->getId());
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and((float) $offline->getAmount())->toBe(60.11)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0);
});

test('reconciling an errored fully funded deposit settles it once', function (): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(true));
    $transaction->setInvoice($invoice)->setAmount('100')->setStatus(Transaction::STATUS_ERROR)->setError('interrupted');
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $transaction->getId())->setDescription('Stripe transaction pi_recovered')->setAmount('100');
    $em->persist($receipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $transaction->getId(), 'pi_recovered');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_PAID)->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0);
    $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $transaction->getId(), 'pi_recovered');
    expect($service->getFreeCredit((int) $client->getId()))->toBe(100.0);
});

test('staff receipt reconciliation credits mixed manual deposit funding once', function (): void {
    [$em, $client, $invoice, $first, $last] = mixedManualDepositContext(20.0);
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($first, $client, 'pi_reconcile_first', 'Stripe transaction pi_reconcile_first', 'transaction', (string) $first->getId());
    $last->setStatus(Transaction::STATUS_ERROR);
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $last->getId())
        ->setDescription('Stripe transaction pi_reconcile_last')->setAmount('40');
    $em->persist($receipt);
    $em->flush();
    $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $last->getId(), 'pi_reconcile_last');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(80.0);
    $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $last->getId(), 'pi_reconcile_last');
    expect($service->getFreeCredit((int) $client->getId()))->toBe(80.0);
});

test('mixed manual and split gateway deposit funding credits all payments exactly once', function (float $serviceCharge): void {
    [$em, $client, $invoice, $first, $last] = mixedManualDepositContext($serviceCharge);
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($first, $client, 'pi_manual_first', 'Stripe transaction pi_manual_first', 'transaction', (string) $first->getId());
    expect($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    $service->recordGatewayPayment($last, $client, 'pi_manual_last', 'Stripe transaction pi_manual_last', 'transaction', (string) $last->getId());
    expect($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0 - $serviceCharge);
    expect($service->recordGatewayPayment($last, $client, 'pi_manual_last', 'Stripe transaction pi_manual_last', 'transaction', (string) $last->getId()))->toBeTrue();
    expect((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM client_balance WHERE description LIKE 'Manual payment funding%'"))->toBe(1)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(100.0 - $serviceCharge);
})->with(['pure deposit' => [0.0], 'deposit and service' => [20.0]]);

test('mixed deposit settlement rolls back and recovers manual credit across interruption', function (string $failure): void {
    [$em, $client, $invoice, $first, $last] = mixedManualDepositContext(20.0);
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($first, $client, 'pi_crash_first', 'Stripe transaction pi_crash_first', 'transaction', (string) $first->getId());
    $connection = $em->getConnection();
    $connection->executeStatement($failure === 'paid state'
        ? "CREATE TRIGGER fail_mixed BEFORE UPDATE OF status ON invoice BEGIN SELECT RAISE(ABORT, 'interrupted mixed deposit'); END"
        : "CREATE TRIGGER fail_mixed BEFORE INSERT ON client_balance WHEN NEW.description LIKE 'Manual payment funding%' BEGIN SELECT RAISE(ABORT, 'interrupted mixed deposit'); END");
    $invoiceId = $invoice->getId();
    $clientId = $client->getId();
    $lastId = $last->getId();
    expect(fn () => $service->recordGatewayPayment($last, $client, 'pi_crash_last', 'Stripe transaction pi_crash_last', 'transaction', (string) $lastId))
        ->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'interrupted mixed deposit');
    expect((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(20.0)
        ->and($connection->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoiceId]))->toBe(Invoice::STATUS_UNPAID)
        ->and($connection->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$lastId]))->toBe(Transaction::STATUS_PROCESSING);
    $connection->executeStatement('DROP TRIGGER fail_mixed');
    $retryEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($retryEm);
    $client = $retryEm->find(Client::class, $clientId);
    $last = $retryEm->find(Transaction::class, $lastId);
    expect($retry->recordGatewayPayment($last, $client, 'pi_crash_last', 'Stripe transaction pi_crash_last', 'transaction', (string) $lastId))->toBeTrue()
        ->and($retryEm->find(Invoice::class, $invoiceId)->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($retry->getFreeCredit((int) $clientId))->toBe(80.0);
    expect($retry->recordGatewayPayment($last, $client, 'pi_crash_last', 'Stripe transaction pi_crash_last', 'transaction', (string) $lastId))->toBeTrue()
        ->and($retry->getFreeCredit((int) $clientId))->toBe(80.0);
})->with(['paid state', 'manual credit']);
