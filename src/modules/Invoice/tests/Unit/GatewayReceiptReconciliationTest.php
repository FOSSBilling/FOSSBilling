<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\ORM\EntityManager;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('receipt sums are read-only and historical linking is explicit', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setStatus(Transaction::STATUS_PROCESSED);
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $transaction->getId())
        ->setDescription('Stripe transaction pi_pure_sum')->setAmount('15')
        ->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_pure_sum'));
    $em->persist($receipt);
    $em->flush();
    $repository = $em->getRepository(ClientBalance::class);

    expect($repository->getGatewayReceiptSumForInvoice((int) $invoice->getId()))->toBe(0.0)
        ->and($em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
    $repository->reconcileHistoricalGatewayReceiptsForInvoice((int) $invoice->getId());
    expect($repository->getGatewayReceiptSumForInvoice((int) $invoice->getId()))->toBe(15.0)
        ->and((int) $em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBe($transaction->getId());
});

test('a pre-upgrade wallet entry with no receipt is held for reconciliation', function (): void {
    [$service, $em, $client, , $transaction] = receiptTestContext();
    $legacy = (new ClientBalance())->setClient($client)->setType('transaction')->setDescription('Stripe transaction pi_old')->setAmount('15.00');
    $em->persist($legacy);
    $em->flush();
    expect(fn () => $service->recordGatewayPayment($transaction, $client, 'pi_old', 'Stripe transaction pi_old', 'transaction', (string) $transaction->getId()))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff reconciliation');
    expect($em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1);
});

test('an unlinked earlier receipt holds offline settlement for reconciliation', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-legacy');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_legacy_link', 'Stripe transaction pi_legacy_link', 'transaction', (string) $transaction->getId());
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL, rel_id = NULL WHERE gateway_receipt_key IS NOT NULL');
    expect(fn () => $service->approveOfflineTransaction($offline))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff reconciliation');
    expect($em->getConnection()->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(Invoice::STATUS_UNPAID)
        ->and($em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toEqual(40);
});

test('a historical receipt with a verified transaction reference is linked safely', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-linked');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_old_link', 'Stripe transaction pi_old_link', 'transaction', (string) $transaction->getId());
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL WHERE gateway_receipt_key IS NOT NULL');
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0)
        ->and((int) $em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe($transaction->getId());
});

test('an unresolved receipt of a different amount does not block an unrelated invoice', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-unrelated');
    $legacy = (new ClientBalance())->setClient($client)->setAmount('13')->setType('transaction')->setGatewayReceiptKey(hash('sha256', 'other'));
    $em->persist($offline);
    $em->persist($legacy);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_unrelated', 'Stripe transaction pi_unrelated', 'transaction', (string) $transaction->getId());
    expect($service->approveOfflineTransaction($offline))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(13.0);
});

test('staff can reconcile a matching historical receipt but cannot link it incorrectly', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40')->setTxnId('pi_staff_review');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-review');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_staff_review', 'Stripe transaction pi_staff_review', 'transaction', (string) $transaction->getId());
    $receiptId = (int) $em->getConnection()->fetchOne('SELECT id FROM client_balance WHERE gateway_receipt_key IS NOT NULL');
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_transaction_id = NULL, rel_id = NULL WHERE id = ?', [$receiptId]);
    $em->clear();
    $transactionId = (int) $transaction->getId();
    $offlineId = (int) $offline->getId();
    expect(fn () => $service->reconcileGatewayReceipt($receiptId, $transactionId, 'pi_wrong'))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    // A rejected transaction closes Doctrine, as it would on a real request.
    $em = new EntityManager($em->getConnection(), $em->getConfiguration());
    $service = gatewayDepositTestService($em);
    $service->reconcileGatewayReceipt($receiptId, $transactionId, 'pi_staff_review');
    expect($service->approveOfflineTransaction($em->find(Transaction::class, $offlineId)))->toBeTrue()
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('pre-fingerprint receipt is held until staff links the verified provider payment', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-pre-key');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_pre_key', 'Stripe transaction pi_pre_key', 'transaction', (string) $transaction->getId());
    $receiptId = (int) $em->getConnection()->fetchOne('SELECT id FROM client_balance WHERE gateway_receipt_key IS NOT NULL');
    $txId = (int) $transaction->getId();
    $offlineId = (int) $offline->getId();
    $clientId = (int) $client->getId();
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_receipt_key = NULL, gateway_transaction_id = NULL WHERE id = ?', [$receiptId]);
    $em->clear();
    expect(fn () => $service->approveOfflineTransaction($em->find(Transaction::class, $offlineId)))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff reconciliation');
    $em = new EntityManager($em->getConnection(), $em->getConfiguration());
    $service = gatewayDepositTestService($em);
    $service->reconcileGatewayReceipt($receiptId, $txId, 'pi_pre_key');
    expect($service->approveOfflineTransaction($em->find(Transaction::class, $offlineId)))->toBeTrue()
        ->and($service->getFreeCredit($clientId))->toBe(0.0)
        ->and($em->getConnection()->fetchOne('SELECT gateway_receipt_key FROM client_balance WHERE id = ?', [$receiptId]))->toBe(hash('sha256', $gateway->getId() . ':pi_pre_key'));
});

test('staff reconciliation finalizes an errored historical receipt without releasing its funds', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setAmount('40');
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setGateway($manualGateway)->setInvoice($invoice)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-error');
    $em->persist($offline);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->recordGatewayPayment($transaction, $client, 'pi_error_old', 'Stripe transaction pi_error_old', 'transaction', (string) $transaction->getId());
    $receiptId = (int) $em->getConnection()->fetchOne('SELECT id FROM client_balance WHERE gateway_receipt_key IS NOT NULL');
    $txId = (int) $transaction->getId();
    $offlineId = (int) $offline->getId();
    $clientId = (int) $client->getId();
    $transaction->setStatus(Transaction::STATUS_ERROR)->setError('interrupted');
    $em->flush();
    $em->getConnection()->executeStatement('UPDATE client_balance SET gateway_receipt_key = NULL, gateway_transaction_id = NULL WHERE id = ?', [$receiptId]);
    $em->clear();
    expect($service->getFreeCredit($clientId))->toBe(0.0);
    expect(fn () => $service->approveOfflineTransaction($em->find(Transaction::class, $offlineId)))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff reconciliation');
    $em = new EntityManager($em->getConnection(), $em->getConfiguration());
    $service = gatewayDepositTestService($em);
    $service->reconcileGatewayReceipt($receiptId, $txId, 'pi_error_old');
    expect($em->find(Transaction::class, $txId)->getStatus())->toBe(Transaction::STATUS_PROCESSED)
        ->and($em->find(Transaction::class, $txId)->getError())->toBeNull()
        ->and($service->approveOfflineTransaction($em->find(Transaction::class, $offlineId)))->toBeTrue()
        ->and($service->getFreeCredit($clientId))->toBe(0.0);
});

test('staff cannot reconcile a receipt to another client transaction', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $other = (new Client())->setCurrency('USD');
    $em->persist($invoice);
    $em->persist($other);
    $em->flush();
    $otherInvoice = (new Invoice())->setClientId($other->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($otherInvoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setAmount('40')->setStatus(Transaction::STATUS_PROCESSED);
    $otherTx = (new Transaction())->setGateway($gateway)->setInvoice($otherInvoice)->setCurrency('USD')->setAmount('40')->setStatus(Transaction::STATUS_PROCESSED);
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setAmount('40')->setDescription('Stripe transaction pi_cross')->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_cross'));
    $em->persist($otherTx);
    $em->persist($receipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect(fn () => $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $otherTx->getId(), 'pi_cross'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'do not match');
    expect($em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
});

test('reconciliation rejects a different same-client Stripe or PayPal payment', function (string $gatewayName, string $type): void {
    [, $em, $client, $gateway, $first] = receiptTestContext();
    $gateway->setGateway($gatewayName);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $otherInvoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->persist($otherInvoice);
    $em->flush();
    $first->setInvoice($invoice)->setAmount('10')->setTxnId('pi_true')->setStatus(Transaction::STATUS_PROCESSED);
    $second = (new Transaction())->setInvoice($otherInvoice)->setGateway($gateway)->setAmount('10')->setCurrency('USD')->setTxnId('pi_other')->setStatus(Transaction::STATUS_PROCESSED);
    $description = ($gatewayName === 'Stripe' ? 'Stripe transaction ' : 'PayPal transaction ') . 'pi_true';
    $receipt = (new ClientBalance())->setClient($client)->setType($type)->setRelId($gatewayName === 'Stripe' ? (string) $first->getId() : 'pi_true')->setDescription($description)->setAmount('10');
    $em->persist($second);
    $em->persist($receipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect(fn () => $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $second->getId(), 'pi_true'))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'do not match');
    expect($em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
})->with(['stripe' => ['Stripe', 'transaction'], 'paypal' => ['PayPalEmail', 'PayPal']]);

test('a legacy receipt is not reserved twice for an unrelated equal payment', function (string $gatewayName, string $type, string $provider): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $gateway->setGateway($gatewayName);
    $first = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $second = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($first);
    $em->persist($second);
    $em->flush();
    foreach ([$first, $second] as $invoice) {
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    }
    $transaction->setInvoice($first)->setAmount('10')->setTxnId($provider)->setStatus(Transaction::STATUS_PROCESSED);
    $unrelated = (new Transaction())->setInvoice($second)->setGateway($gateway)->setAmount('10')->setCurrency('USD')->setStatus(Transaction::STATUS_ERROR);
    $legacy = (new ClientBalance())->setClient($client)->setType($type)->setRelId($gatewayName === 'Stripe' ? (string) $transaction->getId() : null)->setDescription(($gatewayName === 'Stripe' ? 'Stripe transaction ' : 'PayPal transaction ') . $provider)->setAmount('10');
    $gift = (new ClientBalance())->setClient($client)->setType('gift')->setAmount('10');
    $em->persist($unrelated);
    $em->persist($legacy);
    $em->persist($gift);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->getReservedCredit((int) $client->getId()))->toBe(10.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(10.0);
})->with(['stripe' => ['Stripe', 'transaction', 'pi_original'], 'paypal long id' => ['PayPalEmail', 'PayPal', 'PAYPAL-LONG-TRANSACTION-ID']]);

test('a legacy unallocated Stripe payment can be reconciled without an invoice', function (): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $transaction->setStatus(Transaction::STATUS_ERROR)->setError('interrupted');
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $transaction->getId())->setDescription('Stripe transaction pi_unallocated')->setAmount('15');
    $em->persist($receipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $service->reconcileGatewayReceipt((int) $receipt->getId(), (int) $transaction->getId(), 'pi_unallocated');
    expect($receipt->getGatewayTransactionId())->toBe($transaction->getId())->and($transaction->getInvoice())->toBeNull()->and($transaction->getStatus())->toBe(Transaction::STATUS_PROCESSED)->and($service->getFreeCredit((int) $client->getId()))->toBe(15.0);
});

test('a known historical receipt does not block a different equal-amount invoice', function (): void {
    [, $em, $client, $gateway, $old] = receiptTestContext();
    $oldInvoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($oldInvoice);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $old->setInvoice($oldInvoice)->setAmount('10')->setStatus(Transaction::STATUS_PROCESSED);
    $failed = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setAmount('10')->setCurrency('USD')->setStatus(Transaction::STATUS_ERROR);
    $manualGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Custom');
    $em->persist($manualGateway);
    $offline = (new Transaction())->setInvoice($invoice)->setGateway($manualGateway)->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING)->setTxnId('offline-unrelated');
    $legacy = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $old->getId())->setDescription('Stripe transaction pi_old_other')->setAmount('10');
    $em->persist($failed);
    $em->persist($offline);
    $em->persist($legacy);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->approveOfflineTransaction($offline))->toBeTrue()->and($invoice->getStatus())->toBe(Invoice::STATUS_PAID);
});

test('reconciling split legacy receipts settles a covered ordinary invoice once', function (): void {
    [, $em, $client, $gateway, $first] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100)->setQuantity(1)->setCharged(false));
    $first->setInvoice($invoice)->setAmount('50')->setStatus(Transaction::STATUS_ERROR);
    $second = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('50')->setCurrency('USD')->setStatus(Transaction::STATUS_ERROR);
    $em->persist($second);
    $em->flush();
    $firstReceipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $first->getId())->setDescription('Stripe transaction pi_split_a')->setAmount('50');
    $em->persist($firstReceipt);
    $secondReceipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $second->getId())->setDescription('Stripe transaction pi_split_b')->setAmount('50');
    $em->persist($secondReceipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $paidEvents = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$paidEvents): void { ++$paidEvents; });
    $di = $service->getDi();
    unset($di['event_dispatcher']);
    $di['event_dispatcher'] = $dispatcher;
    $service->reconcileGatewayReceipt((int) $firstReceipt->getId(), (int) $first->getId(), 'pi_split_a');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)->and($paidEvents)->toBe(0);
    expect($service->getAmountPaid($invoice))->toBe(50.0)->and($second->getStatus())->toBe(Transaction::STATUS_ERROR);
    $service->reconcileGatewayReceipt((int) $secondReceipt->getId(), (int) $second->getId(), 'pi_split_b');
    expect($invoice->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and($paidEvents)->toBe(1)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
    $service->reconcileGatewayReceipt((int) $secondReceipt->getId(), (int) $second->getId(), 'pi_split_b');
    expect($paidEvents)->toBe(1)->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('a paid historical receipt does not reserve unrelated funds', function (): void {
    [, $em, $client, $gateway, $old] = receiptTestContext();
    $oldInvoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($oldInvoice);
    $em->persist($invoice);
    $em->flush();
    $old->setInvoice($oldInvoice)->setAmount('10')->setStatus(Transaction::STATUS_PROCESSED);
    $failed = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setAmount('10')->setCurrency('USD')->setStatus(Transaction::STATUS_ERROR);
    $legacy = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $old->getId())->setDescription('Stripe transaction pi_old_paid')->setAmount('10');
    $debit = (new ClientBalance())->setClient($client)->setType('invoice')->setRelId((string) $oldInvoice->getId())->setAmount('-10');
    $gift = (new ClientBalance())->setClient($client)->setType('gift')->setAmount('10');
    $em->persist($failed);
    $em->persist($legacy);
    $em->persist($debit);
    $em->persist($gift);
    $em->flush();
    $service = gatewayDepositTestService($em);
    expect($service->getFreeCredit((int) $client->getId()))->toBe(10.0);
});

test('staff reconciliation completion survives listener failures and crashes without moving money twice', function (bool $crash): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus('unpaid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setStatus('pending_payment')->setPrice(15)->setQuantity(1)->setCharged(false));
    $transaction->setInvoice($invoice)->setStatus('error')->setTxnId('pi_staff_completion');
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setAmount('15')
        ->setRelId((string) $transaction->getId())->setDescription('Stripe transaction pi_staff_completion');
    $em->persist($receipt);
    $em->flush();
    $service = gatewayDepositTestService($em);
    $calls = 0;
    $events = new Symfony\Component\EventDispatcher\EventDispatcher();
    $events->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$calls, $crash): void {
        if (++$calls === 1) {
            throw $crash ? new Error('crash after staff commit') : new RuntimeException('staff listener failure');
        }
    });
    $service->getDi()['event_dispatcher'] = $events;
    expect(fn () => $service->reconcileGatewayReceipt($receipt->getId(), $transaction->getId(), 'pi_staff_completion'))->toThrow($crash ? Error::class : RuntimeException::class);
    $connection = $em->getConnection();
    expect($transaction->getStatus())->toBe('processed')->and($invoice->getStatus())->toBe('paid')
        ->and((int) $connection->fetchOne('SELECT gateway_completion_state FROM "transaction" WHERE id = ?', [$transaction->getId()]))->toBe(Transaction::GATEWAY_COMPLETION_PENDING);
    $retryEm = new EntityManager($connection, $em->getConfiguration());
    $retry = gatewayDepositTestService($retryEm);
    $retry->getDi()['event_dispatcher'] = $events;
    $retry->reconcileGatewayReceipt($receipt->getId(), $transaction->getId(), 'pi_staff_completion');
    $retry->reconcileGatewayReceipt($receipt->getId(), $transaction->getId(), 'pi_staff_completion');
    expect($calls)->toBe(2)
        ->and((int) $connection->fetchOne('SELECT gateway_completion_state FROM "transaction" WHERE id = ?', [$transaction->getId()]))->toBe(Transaction::GATEWAY_COMPLETION_DONE)
        ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
        ->and($connection->fetchOne('SELECT status FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))->toBe('executed');
})->with([false, true]);

class ReceiptClassificationConnection extends Doctrine\DBAL\Connection
{
    public ?Closure $afterReceiptRead = null;
    public ?Closure $beforeReceiptLink = null;

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        $rows = parent::fetchAllAssociative($query, $params, $types);
        if ($this->afterReceiptRead !== null && str_contains($query, 'FROM client_balance b LEFT JOIN')) {
            $callback = $this->afterReceiptRead;
            $this->afterReceiptRead = null;
            $callback();
        }

        return $rows;
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        if ($this->beforeReceiptLink !== null && str_starts_with($sql, 'UPDATE client_balance SET gateway_transaction_id')) {
            ($this->beforeReceiptLink)();
        }

        return parent::executeStatement($sql, $params, $types);
    }
}

test('credit previews classify historical receipts without writes and payment links under its credit lock', function (string $provider, bool $ambiguous): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => ReceiptClassificationConnection::class]);
    [, $em, $client, $gateway, $transaction] = receiptTestContext($connection);
    $gateway->setGateway($provider);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(150)->setQuantity(1));
    $transaction->setInvoice($invoice)->setAmount('50')->setTxnId('historical-payment')->setStatus(Transaction::STATUS_PROCESSED);
    $em->persist((new Transaction())->setInvoice($invoice)->setGateway($gateway)->setAmount('40')->setCurrency('USD')->setTxnId('manual-partial')->setStatus(Transaction::STATUS_PROCESSED));
    $receipt = (new ClientBalance())->setClient($client)->setType($provider === 'Stripe' ? 'transaction' : 'PayPal')->setAmount('50')
        ->setRelId($ambiguous ? null : (string) $transaction->getId())
        ->setDescription(($provider === 'Stripe' ? 'Stripe transaction ' : 'PayPal transaction ') . 'historical-payment')
        ->setGatewayReceiptKey($ambiguous ? null : hash('sha256', $gateway->getId() . ':historical-payment'));
    $em->persist($receipt);
    $em->persist((new ClientBalance())->setClient($client)->setType('gift')->setAmount('60'));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $clientId = (int) $client->getId();
    $connection->executeStatement('CREATE TRIGGER reject_preview_writes BEFORE UPDATE ON client_balance BEGIN SELECT RAISE(ABORT, \'credit preview wrote a receipt\'); END');
    foreach ([1, 2] as $attempt) {
        expect($service->getReservedCredit($clientId))->toBe($ambiguous ? 90.0 : 50.0)
            ->and($service->getReservedCreditForClients([$clientId]))->toBe([$clientId => $ambiguous ? 90.0 : 50.0])
            ->and($service->getFreeCredit($clientId))->toBe($ambiguous ? 20.0 : 60.0)
            ->and($connection->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
    }
    $connection->executeStatement('DROP TRIGGER reject_preview_writes');
    $writes = 0;
    $connection->beforeReceiptLink = function () use ($connection, $clientId, &$writes): void {
        ++$writes;
        $name = 'fb:credit_payment:client:' . $clientId;
        $unlocked = FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, $name, 0);
        if ($unlocked) {
            FOSSBilling\Core\Doctrine\NamedLock::release($connection, $name);
        }
        expect($connection->isTransactionActive())->toBeTrue()->and($unlocked)->toBeFalse();
    };
    if ($ambiguous) {
        expect(fn () => $service->tryPayWithCredits($invoice))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff reconciliation');
        expect($writes)->toBe(0)->and($connection->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_UNPAID)
            ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(110.0);
    } else {
        expect($service->tryPayWithCredits($invoice))->toBeTrue()->and($writes)->toBe(1)
            ->and((int) $connection->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBe($transaction->getId())
            ->and($em->getRepository(ClientBalance::class)->getGatewayReceiptSumForInvoice((int) $invoice->getId()))->toBe(50.0)
            ->and((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
            ->and($service->tryPayWithCredits($invoice))->toBeFalse()->and($writes)->toBe(1);
    }
})->with(['Stripe', 'PayPalEmail'])->with([false, true]);

test('a receipt linked between preview reads is counted exactly once', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => ReceiptClassificationConnection::class]);
    [, $em, $client, $gateway, $transaction] = receiptTestContext($connection);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setPrice(100)->setQuantity(1));
    $transaction->setInvoice($invoice)->setAmount('50')->setStatus(Transaction::STATUS_PROCESSED);
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $transaction->getId())->setDescription('Stripe transaction pi_preview')->setAmount('50')->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_preview'));
    $em->persist($receipt);
    $em->flush();
    $connection->afterReceiptRead = fn () => $connection->update('client_balance', ['gateway_transaction_id' => $transaction->getId()], ['id' => $receipt->getId()]);
    $service = gatewayDepositTestService($em);
    expect($service->getReservedCredit((int) $client->getId()))->toBe(50.0)
        ->and($service->getReservedCredit((int) $client->getId()))->toBe(50.0)
        ->and($service->getFreeCredit((int) $client->getId()))->toBe(0.0);
});

test('receipt classification retains signed linked entries alongside historical receipts', function (): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $transaction->setInvoice($invoice)->setStatus(Transaction::STATUS_PROCESSED)->setAmount('50');
    foreach ([60, -10] as $amount) {
        $em->persist((new ClientBalance())->setClient($client)->setAmount((string) $amount)
            ->setGatewayTransactionId($transaction->getId())->setGatewayReceiptKey(hash('sha256', 'linked:' . $amount)));
    }
    $historicalTransaction = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setAmount('15')->setStatus(Transaction::STATUS_PROCESSED);
    $em->persist($historicalTransaction);
    $em->flush();
    $receipt = (new ClientBalance())->setClient($client)->setType('transaction')->setRelId((string) $historicalTransaction->getId())
        ->setDescription('Stripe transaction pi_signed_preview')->setAmount('15')
        ->setGatewayReceiptKey(hash('sha256', $gateway->getId() . ':pi_signed_preview'));
    $em->persist($receipt);
    $em->flush();
    $repository = $em->getRepository(ClientBalance::class);
    expect($repository->getGatewayReceiptReservationForInvoice((int) $invoice->getId()))->toBe(65.0)
        ->and($repository->getGatewayReceiptSumForInvoice((int) $invoice->getId()))->toBe(50.0)
        ->and($em->getConnection()->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE id = ?', [$receipt->getId()]))->toBeNull();
});
