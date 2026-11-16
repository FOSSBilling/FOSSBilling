<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Exception\InformationException;

require_once PATH_TESTS . '/Helpers/DeferredPayPal.php';

test('verified PayPal renewal receipts remain credit during deletion recovery, including retries', function (string $txnType, bool $crash, string $gross): void {
    $c = deferredPayPalReceiptContext(txnType: $txnType, amount: $gross);
    $transaction = $c['receipt'];
    $payload = $c['payload'];
    $payload['get']['sig'] = FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId());
    $api = $c['api'];
    $adapter = $c['adapter'];
    $expiresAt = clone $c['order']->getExpiresAt();
    if ($crash) {
        $api->crash = true;
        expect(fn () => $adapter->processTransaction($api, $transaction->getId(), $payload, $c['gateway']->getId()))
            ->toThrow(Error::class, 'Interrupted before receipt completion');
        expect($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
            ->and($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processing');
        // A restarted worker gets a fresh entity manager after the rollback.
        $c['em'] = new EntityManager($c['connection'], $c['config']);
        $c['di']['em'] = $c['em'];
        $c['client'] = $c['em']->find(Box\Mod\Client\Entity\Client::class, $c['client']->getId());
        $api = deferredPayPalApi($c);
    }
    $adapter->processTransaction($api, $transaction->getId(), $payload, $c['gateway']->getId());
    // Even if staff recover deletion between callbacks, replay must not renew.
    $c['connection']->delete('client_order_meta', ['client_order_id' => $c['order']->getId()]);
    $adapter->processTransaction($api, $transaction->getId(), $payload, $c['gateway']->getId());
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe((float) $gross)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processed')
        ->and($c['connection']->fetchOne('SELECT invoice_id FROM "transaction"'))->toBe($c['invoice']->getId())
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and($c['order']->getExpiresAt())->toEqual($expiresAt);
})->with(['subscr_payment', 'recurring_payment'])->with([false, true])->with(['10.00', '3.00']);

test('deferred PayPal renewal credits reject invalid amounts and currencies before adding funds', function (string $amount, string $currency): void {
    $c = deferredPayPalReceiptContext(amount: $amount, currency: $currency);
    $transaction = $c['receipt'];
    $payload = $c['payload'];
    $payload['get']['sig'] = FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId());
    $api = $c['api'];
    $adapter = $c['adapter'];
    expect(fn () => $adapter->processTransaction($api, $transaction->getId(), $payload, $c['gateway']->getId()))->toThrow(Exception::class);
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processing');
})->with([
    'negative' => ['-1', 'USD'],
    'not numeric' => ['invalid', 'USD'],
    'non-finite' => ['1e999', 'USD'],
    'wrong currency' => ['10', 'EUR'],
]);

test('overlapping PayPal workers credit an expired claim only once and refresh stale receipt state', function (string $mode): void {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $path = $filesystem->tempnam(sys_get_temp_dir(), 'paypal-overlap-');
    $c = deferredPayPalReceiptContext($path);
    $input = new Symfony\Component\Process\InputStream();
    $worker = new Symfony\Component\Process\Process([PHP_BINARY, PATH_TESTS . '/Fixtures/paypal-receipt-worker.php', $path, PATH_DATA, (string) $c['receipt']->getId(), $mode]);
    $worker->setInput($input);

    try {
        if ($mode === 'claim') {
            $c['api']->beforeInvoiceRead = function () use ($c, $worker): void {
                // This worker still owns the receipt mutex, but its persisted
                // processing claim can expire while it is stalled.
                $c['connection']->executeStatement('UPDATE "transaction" SET updated_at = ? WHERE id = ?', [date('Y-m-d H:i:s', time() - 600), $c['receipt']->getId()]);
                $worker->start();
                expect($worker->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
            };
        } else {
            // The old worker loads processing state before the winner commits.
            $worker->start();
            expect($worker->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
        }
        $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
        if ($mode === 'wait') {
            // Recovery must not let the stale processor generate a new invoice.
            $c['connection']->delete('client_order_meta', ['client_order_id' => $c['order']->getId()]);
            $input->write("go\n");
        }
        $input->close();
        $worker->wait();
        expect($worker->getExitCode())->toBe(0)
            ->and($worker->getOutput())->toContain($mode === 'claim' ? 'retained' : 'processed')
            ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0)
            ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
            ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
            ->and($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processed');
    } finally {
        $worker->stop();
        $input->close();
        $c['connection']->close();
        $filesystem->remove([$path, $path . '-wal', $path . '-shm']);
    }
})->with(['claim', 'wait']);

test('deferred PayPal refunds reverse only received credit across partials and duplicate receipts', function (bool $spent, bool $earlierAttempt): void {
    $c = deferredPayPalReceiptContext(earlierAttempt: $earlierAttempt);
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    if ($spent) {
        $debit = (new Box\Mod\Client\Entity\ClientBalance())->setClient($c['client'])->setType('invoice')->setAmount('-3');
        $c['em']->persist($debit);
        $c['em']->flush();
    }
    foreach ([['REFUND-1', '-1'], ['REFUND-1', '-1'], ['REFUND-2', '-2']] as [$externalId, $amount]) {
        [$refund, $payload] = deferredPayPalRefund($c, $externalId, $amount);
        $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
        // Replay the same row as well as the distinct row with the same PayPal ID.
        $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
        expect($refund->getStatus())->toBe('processed');
    }
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe($spent ? -3.0 : 0.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance WHERE amount < 0'))->toBe($spent ? 3 : 2)
        ->and($c['invoice']->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and((float) $c['invoice']->getRefund())->toBe(0.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
})->with([false, true])->with([false, true]);

test('deferred PayPal refund debit and receipt completion recover atomically after interruption', function (): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-CRASH', '-3');
    $c['api']->crash = true;
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))
        ->toThrow(Error::class, 'Interrupted before receipt completion');
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe('processing');
    $c['em'] = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $c['em'];
    $c['client'] = $c['em']->find(Box\Mod\Client\Entity\Client::class, $c['client']->getId());
    $c['api'] = deferredPayPalApi($c);
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(2)
        ->and($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_PAID);
});

test('deferred PayPal refunds reject over-refunds and invalid amounts or currencies', function (string $amount, string $currency): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-INVALID', $amount, $currency);
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))->toThrow(Exception::class);
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0)
        ->and($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_PAID)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe('processing');
})->with([['-4', 'USD'], ['3', 'USD'], ['-3', 'EUR'], ['-1e999', 'USD'], ['bad', 'USD']]);

test('deferred PayPal ledger checkpoints cannot be removed through balance deletion', function (bool $refundEntry): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    if ($refundEntry) {
        [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-DELETE', '-1');
        $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    }
    $entryId = (int) $c['connection']->fetchOne('SELECT MAX(id) FROM client_balance');
    $staff = Mockery::mock(Box\Mod\Staff\Service::class);
    $staff->shouldReceive('checkPermissionsAndThrowException')->once();
    $c['di']['mod_service'] = $c['di']->protect(fn (): object => $staff);
    $api = apiEndpoint(new Box\Mod\Client\Api\Admin());
    $api->setDi($c['di']);
    expect(fn () => $api->balance_delete(['id' => $entryId]))->toThrow(InformationException::class, 'cannot be deleted')
        ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe($refundEntry ? 2.0 : 3.0);
})->with([false, true]);

test('deferred PayPal refund totals and identities cannot be changed by later callbacks', function (bool $sameRefundId): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    [$first, $payload] = deferredPayPalRefund($c, 'REFUND-PARTIAL', '-1');
    $c['adapter']->processTransaction($c['api'], $first->getId(), $payload, $c['gateway']->getId());
    [$second, $payload] = deferredPayPalRefund($c, $sameRefundId ? 'REFUND-PARTIAL' : 'REFUND-EXCESS', '-3');
    expect(fn () => $c['adapter']->processTransaction($c['api'], $second->getId(), $payload, $c['gateway']->getId()))->toThrow(Payment_Exception::class);
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(2.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(2)
        ->and($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_PAID);
})->with([false, true]);

test('a stale PayPal error status cannot replay retained credit after deletion recovery', function (): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    // A competing processor can time out on the mutex and record its old
    // failure after the winner's durable credit/completion commit.
    $c['connection']->executeStatement('UPDATE "transaction" SET status = ?, error = ? WHERE id = ?', ['error', 'Old worker timed out', $c['receipt']->getId()]);
    $c['connection']->delete('client_order_meta', ['client_order_id' => $c['order']->getId()]);
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and($c['receipt']->getStatus())->toBe('processed');
});

test('out-of-order PayPal refunds wait for the parent receipt and retry without refunding the old invoice', function (?string $parentStatus): void {
    $c = deferredPayPalReceiptContext();
    if ($parentStatus === null) {
        // The refund arrives before the completed callback has supplied its ID.
        $c['receipt']->setTxnId(null);
    } else {
        $c['receipt']->setStatus($parentStatus);
    }
    $c['em']->flush();
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-EARLY', '-3');
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect($refund->getStatus())->toBe('received')
        ->and($refund->getError())->toContain('awaiting its original payment')
        ->and($c['em']->isOpen())->toBeTrue()
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($c['invoice']->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and((float) $c['invoice']->getRefund())->toBe(0.0);
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect($refund->getStatus())->toBe('processed')
        ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(2)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and($c['invoice']->getStatus())->toBe(Invoice::STATUS_PAID)
        ->and((float) $c['invoice']->getRefund())->toBe(0.0);
})->with([null, 'received', 'processing', 'error']);

test('MySQL deferred PayPal refunds for independent clients do not lock each others ledger rows', function (): void {
    $em = deferredPayPalMysqlEntityManager();
    $connection = $em->getConnection();
    $metadata = array_map($em->getClassMetadata(...), [
        Box\Mod\Client\Entity\Client::class, Box\Mod\Client\Entity\ClientBalance::class,
        Invoice::class, Box\Mod\Invoice\Entity\PayGateway::class,
        Box\Mod\Invoice\Entity\Transaction::class, Box\Mod\Invoice\Entity\Subscription::class,
    ]);
    $schema = new SchemaTool($em);
    $workers = [];
    $inputs = [];

    try {
        $schema->createSchema($metadata);
        $gateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('PayPalEmail')->setEnabled(true);
        $em->persist($gateway);
        $em->flush();
        $clientIds = [];
        $refundIds = [];
        foreach ([1, 2] as $_) {
            $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
            $em->persist($client);
            $em->flush();
            $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setGateway($gateway)->setIssued(true)->setStatus(Invoice::STATUS_PAID);
            $em->persist($invoice);
            $em->flush();
            $parent = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($invoice)->setGateway($gateway)->setTxnId('PAYMENT-' . $client->getId())->setStatus('processed')->setTxnStatus('Completed')->setType('subscr_payment')->setAmount('3')->setCurrency('USD');
            $em->persist($parent);
            $em->flush();
            $credit = (new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setAmount('3')->setType(Box\Mod\Client\Entity\ClientBalance::TYPE_PAYPAL_DEFERRED)->setRelId((string) $parent->getId());
            $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('SUB-' . $client->getId())->setRelType('invoice')->setRelId($invoice->getId());
            $refund = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($invoice)->setGateway($gateway)->setStatus('processing');
            foreach ([$credit, $subscription, $refund] as $entity) {
                $em->persist($entity);
            }
            $em->flush();
            $clientIds[] = $client->getId();
            $refundIds[] = $refund->getId();
        }
        // Keep unrelated wallet rows so this catches locking scans of the ledger,
        // rather than relying on an empty or unusually small table.
        $padding = [];
        for ($n = 0; $n < 100; ++$n) {
            $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
            $em->persist($client);
            $padding[] = $client;
        }
        $em->flush();
        foreach ($padding as $client) {
            $em->persist((new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setAmount('1')->setType('gift'));
        }
        $em->flush();
        foreach ($refundIds as $refundId) {
            $input = new Symfony\Component\Process\InputStream();
            $worker = new Symfony\Component\Process\Process([
                PHP_BINARY, '-d', 'sys_temp_dir=' . sys_get_temp_dir(), PATH_TESTS . '/Fixtures/paypal-refund-mysql-worker.php', PATH_DATA, (string) $refundId,
            ]);
            $worker->setInput($input);
            $worker->setTimeout(20);
            $workers[] = $worker;
            $inputs[] = $input;
            $worker->start();
            expect($worker->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
        }
        foreach ($inputs as $input) {
            $input->write("go\n");
            $input->close();
        }
        foreach ($workers as $worker) {
            $worker->wait();
            $this->assertSame(0, $worker->getExitCode(), $worker->getOutput() . $worker->getErrorOutput());
            expect($worker->getOutput())->toContain('processed');
        }
        foreach ($clientIds as $clientId) {
            expect((float) $connection->fetchOne('SELECT SUM(amount) FROM client_balance WHERE client_id = ?', [$clientId]))->toBe(0.0);
        }
        expect($connection->fetchOne("SELECT COUNT(*) FROM invoice WHERE status <> 'paid'"))->toBe(0)
            ->and($connection->fetchOne("SELECT COUNT(*) FROM `transaction` WHERE status <> 'processed'"))->toBe(0);
    } finally {
        foreach ($workers as $worker) {
            $worker->stop();
        }
        foreach ($inputs as $input) {
            $input->close();
        }
        $schema->dropSchema($metadata);
        $connection->close();
    }
})->skip(fn (): bool => getenv('DELETION_HOLD_MYSQL_PORT') === false, 'Requires an isolated MySQL hold_regression database');

test('a completed PayPal receipt without a settled invoice is not treated as an invoice refund', function (): void {
    $c = deferredPayPalReceiptContext();
    $c['invoice']->setStatus(Invoice::STATUS_UNPAID);
    $c['receipt']->setStatus('processed');
    $c['em']->flush();
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-UNSETTLED', '-3');
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect($refund->getStatus())->toBe('received')
        ->and($c['invoice']->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
});

test('renewal refunds resolve the settled parent invoice without trusting another client or gateway', function (bool $sameClient, bool $sameGateway): void {
    $c = deferredPayPalReceiptContext();
    $client = $c['client'];
    $gateway = $c['gateway'];
    if (!$sameClient) {
        $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
        $c['em']->persist($client);
    }
    if (!$sameGateway) {
        $gateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('PayPalEmail')->setEnabled(true);
        $c['em']->persist($gateway);
    }
    $c['em']->flush();
    $renewal = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setGateway($gateway)->setIssued(true)->setStatus(Invoice::STATUS_PAID);
    $c['em']->persist($renewal);
    $c['em']->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($renewal)->setType('custom')->setPrice(3)->setQuantity(1));
    $c['receipt']->setInvoice($renewal)->setGateway($gateway)->setStatus('processed');
    $c['em']->flush();
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-RENEWAL', '-3');
    $c['api']->allowInvoiceRefund = true;
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect($c['api']->refunds)->toBe($sameClient && $sameGateway ? [['id' => $renewal->getId(), 'note' => 'PayPal refund PAYPAL-DEFERRED', 'expected_amount' => 3.0]] : [])
        ->and($refund->getStatus())->toBe($sameClient && $sameGateway ? 'processed' : 'received')
        ->and($refund->getInvoice()->getId())->toBe($sameClient && $sameGateway ? $renewal->getId() : $c['invoice']->getId())
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
})->with([[true, true], [false, true], [true, false]]);

test('conflicting settled parent invoices do not select an arbitrary refund target', function (): void {
    $c = deferredPayPalReceiptContext();
    $c['receipt']->setStatus('processed');
    $other = (new Invoice())->setClientId($c['client']->getId())->setCurrency('USD')->setGateway($c['gateway'])->setIssued(true)->setStatus(Invoice::STATUS_PAID);
    $c['em']->persist($other);
    $duplicate = (clone $c['receipt'])->setInvoice($other);
    $c['em']->persist($duplicate);
    $c['em']->flush();
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-CONFLICT', '-3');
    $c['api']->allowInvoiceRefund = true;
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))->toThrow(Payment_Exception::class, 'conflicting');
    expect($c['api']->refunds)->toBeEmpty();
});

test('receipt deletion preserves deferred credit checkpoints after a stale error status', function (bool $refundOnly): void {
    $c = deferredPayPalReceiptContext();
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
    if ($refundOnly) {
        [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-PROTECTED', '-1');
        $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
        $c['connection']->executeStatement('DELETE FROM client_balance WHERE amount > 0');
    }
    $c['connection']->update($c['connection']->quoteSingleIdentifier('transaction'), ['status' => 'error'], ['id' => $c['receipt']->getId()]);
    $c['em']->refresh($c['receipt']);
    $service = new Box\Mod\Invoice\ServiceTransaction();
    $service->setDi($c['di']);
    expect(fn () => $service->delete($c['receipt']))->toThrow(InformationException::class, 'backing gateway receipt')
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction" WHERE id = ?', [$c['receipt']->getId()]))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1);
    if (!$refundOnly) {
        [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-AFTER-DELETE', '-3');
        $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
        expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);
    }
})->with([false, true]);

test('receipt deletion refuses an outer transaction and still removes an ordinary unused receipt', function (): void {
    $c = deferredPayPalReceiptContext();
    $service = new Box\Mod\Invoice\ServiceTransaction();
    $service->setDi($c['di']);
    $c['connection']->beginTransaction();

    try {
        expect(fn () => $service->delete($c['receipt']))->toThrow(InformationException::class, 'inside a database transaction');
        expect($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(1);
    } finally {
        $c['connection']->rollBack();
    }
    $c['receipt']->setStatus('received');
    $c['em']->flush();
    expect($service->delete($c['receipt']))->toBeTrue()
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(0);
});

test('receipt deletion waits for an overlapping callback and refreshes its stale status', function (): void {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $path = $filesystem->tempnam(sys_get_temp_dir(), 'receipt-delete-');
    $c = deferredPayPalReceiptContext($path);
    $worker = new Symfony\Component\Process\Process([PHP_BINARY, PATH_TESTS . '/Fixtures/receipt-delete-worker.php', $path, PATH_DATA, (string) $c['receipt']->getId()]);
    $c['api']->beforeInvoiceRead = function () use ($worker): void {
        $worker->start();
        expect($worker->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
    };

    try {
        $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $c['payload'], $c['gateway']->getId());
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
        expect($worker->getOutput())->toContain('blocked')
            ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(1)
            ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0);
    } finally {
        $worker->stop();
        $c['connection']->close();
        $filesystem->remove([$path, $path . '-wal', $path . '-shm']);
    }
});

test('renewal refund retries retain their parent invoice after interruption and authenticate the original callback', function (): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-RETRY', '-3');
    $payload['get']['sig'] = FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId());
    $c['api']->crashRefund = true;
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))->toThrow(Error::class, 'Interrupted');
    expect((int) $c['connection']->fetchOne('SELECT invoice_id FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe($renewal->getId());
    $c['em'] = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $c['em'];
    $c['client'] = $c['em']->find(Box\Mod\Client\Entity\Client::class, $c['client']->getId());
    $api = deferredPayPalApi($c);
    $api->allowInvoiceRefund = true;
    $c['adapter']->processTransaction($api, $refund->getId(), $payload, $c['gateway']->getId());
    expect($api->refunds)->toBe([['id' => $renewal->getId(), 'note' => 'PayPal refund PAYPAL-DEFERRED', 'expected_amount' => 3.0]])
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe('processed');
});

test('a committed renewal refund recovers interrupted receipt completion without refunding twice', function (): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-COMMITTED', '-3');
    $c['api']->allowInvoiceRefund = true;
    $c['api']->crash = true;
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))->toThrow(Error::class, 'Interrupted before receipt completion');
    expect($c['api']->refunds)->toHaveCount(1)
        ->and($c['connection']->fetchOne('SELECT status FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(Invoice::STATUS_REFUNDED);
    $c['em'] = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $c['em'];
    $c['client'] = $c['em']->find(Box\Mod\Client\Entity\Client::class, $c['client']->getId());
    $api = deferredPayPalApi($c); // Any second invoice_refund call throws.
    $c['adapter']->processTransaction($api, $refund->getId(), $payload, $c['gateway']->getId());
    expect($api->refunds)->toBeEmpty()
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe('processed');
});

test('partial renewal refunds remain for staff reconciliation across fresh-worker retries', function (string $amount): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-PARTIAL', $amount);
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    $c['em'] = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $c['em'];
    $api = deferredPayPalApi($c);
    $c['adapter']->processTransaction($api, $refund->getId(), $payload, $c['gateway']->getId());
    expect($api->refunds)->toBeEmpty()
        ->and($c['connection']->fetchOne('SELECT status FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(Invoice::STATUS_PAID)
        ->and((float) $c['connection']->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(0.0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(2)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction" WHERE id = ?', [$refund->getId()]))->toBe('received')
        ->and($c['connection']->fetchOne('SELECT error FROM "transaction" WHERE id = ?', [$refund->getId()]))->toContain('staff reconciliation');
})->with(['-1', '-2', '-4']);

test('renewal refunds validate currency and signed amount before accounting', function (string $amount, string $currency): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-INVALID', $amount, $currency);
    expect(fn () => $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId()))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
    expect($c['api']->refunds)->toBeEmpty()
        ->and($renewal->getStatus())->toBe(Invoice::STATUS_PAID);
})->with([['-3', 'EUR'], ['0', 'USD'], ['3', 'USD'], ['NaN', 'USD']]);

test('duplicate renewal refund receipts recover after completion crashes without relaxing transaction binding', function (bool $crashFirst, bool $crashDuplicate): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$first, $payload] = deferredPayPalRefund($c, 'REFUND-DUPLICATE', '-3');
    [$duplicate] = deferredPayPalRefund($c, 'REFUND-DUPLICATE', '-3');
    $c['api']->allowInvoiceRefund = true;
    $c['api']->crash = $crashFirst;
    if ($crashFirst) {
        expect(fn () => $c['adapter']->processTransaction($c['api'], $first->getId(), $payload, $c['gateway']->getId()))->toThrow(Error::class, 'Interrupted before receipt completion');
    } else {
        $c['adapter']->processTransaction($c['api'], $first->getId(), $payload, $c['gateway']->getId());
    }
    expect($c['api']->refunds)->toHaveCount(1);
    $c['em'] = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $c['em'];
    $api = deferredPayPalApi($c); // Another refund call must fail.
    $api->crash = $crashDuplicate;
    if ($crashDuplicate) {
        expect(fn () => $c['adapter']->processTransaction($api, $duplicate->getId(), $payload, $c['gateway']->getId()))->toThrow(Error::class, 'Interrupted before receipt completion');
        $c['em'] = new EntityManager($c['connection'], $c['config']);
        $c['di']['em'] = $c['em'];
        $api = deferredPayPalApi($c);
    }
    foreach ([$duplicate, $first, $duplicate] as $receipt) {
        $c['adapter']->processTransaction($api, $receipt->getId(), $payload, $c['gateway']->getId());
    }
    expect($api->refunds)->toBeEmpty()
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction" WHERE txn_status = \'Refunded\' AND status = \'processed\' AND invoice_id = ?', [$renewal->getId()]))->toBe(2);
    $c['invoice'] = $c['em']->find(Invoice::class, $c['invoice']->getId());
    $c['gateway'] = $c['em']->find(Box\Mod\Invoice\Entity\PayGateway::class, $c['gateway']->getId());
    [$unrelated] = deferredPayPalRefund($c, 'UNRELATED', '-3');
    $service = new Box\Mod\Invoice\ServiceTransaction();
    $service->setDi($c['di']);
    expect(fn () => $service->update($unrelated, ['invoice_id' => $renewal->getId()]))->toThrow(InformationException::class, 'Release the interrupted transaction');
})->with([[false, false], [true, false], [false, true], [true, true]]);

test('renewal refund accounting commits once and rejects a partial accounting race under the invoice lock', function (bool $partialRace): void {
    $c = deferredPayPalReceiptContext();
    $renewal = settledPayPalRenewal($c);
    [$first, $payload] = deferredPayPalRefund($c, 'REFUND-ACCOUNTING', '-3');
    [$duplicate] = deferredPayPalRefund($c, 'REFUND-ACCOUNTING', '-3');
    $billing = Mockery::mock(Box\Mod\Invoice\Service::class)->makePartial();
    $billing->shouldReceive('countIncome')->andReturnNull();
    $billing->shouldReceive('recordJournalEvent')->andReturnNull();
    $billing->shouldReceive('toApiArray')->andReturn(['total' => 0]);
    $billing->shouldReceive('addNote')->andReturnTrue();
    $billing->setDi($c['di']);
    $items = new Box\Mod\Invoice\ServiceInvoiceItem();
    $items->setDi($c['di']);
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')->andReturnUsing(fn ($key, $default = null) => $default);
    $system->shouldReceive('reserveNextNumericParamValue')->andReturn(1);
    $c['di']['mod_service'] = $c['di']->protect(fn ($module, $submodule = null): object => $module === 'system' ? $system : ($submodule === 'InvoiceItem' ? $items : $billing));
    $c['api']->allowInvoiceRefund = true;
    $c['api']->crash = !$partialRace;
    $c['api']->refundHandler = function (array $data) use ($c, $billing, $renewal, $partialRace): int {
        if ($partialRace) {
            // A staff partial refund commits after the callback's preflight.
            $line = $c['em']->getRepository(Box\Mod\Invoice\Entity\InvoiceItem::class)->findOneBy(['invoice' => $renewal->getId()]);
            $credit = (new Invoice())->setClientId($c['client']->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_REFUNDED)->setCreditNoteForInvoiceId($renewal->getId());
            $c['em']->persist($credit);
            $c['em']->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($credit)->setPrice(-1)->setQuantity(1)->setRefundedItemId($line->getId()));
            $c['em']->flush();
            $c['connection']->update('invoice', ['refund' => 1], ['id' => $renewal->getId()]);
        }

        return $billing->refundInvoice($renewal, $data['note'], null, ['gateway_refund' => false, 'expected_amount' => $data['expected_amount']]);
    };
    if ($partialRace) {
        expect(fn () => $c['adapter']->processTransaction($c['api'], $first->getId(), $payload, $c['gateway']->getId()))->toThrow(InformationException::class, 'changed during reconciliation');
        expect($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice WHERE credit_note_for_invoice_id = ?', [$renewal->getId()]))->toBe(1)
            ->and((float) $c['connection']->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(1.0)
            ->and($c['connection']->fetchOne('SELECT status FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(Invoice::STATUS_PAID);
    } else {
        expect(fn () => $c['adapter']->processTransaction($c['api'], $first->getId(), $payload, $c['gateway']->getId()))->toThrow(Error::class, 'Interrupted before receipt completion');
        $c['em'] = new EntityManager($c['connection'], $c['config']);
        $c['di']['em'] = $c['em'];
        $api = deferredPayPalApi($c);
        foreach ([$duplicate, $first] as $receipt) {
            $c['adapter']->processTransaction($api, $receipt->getId(), $payload, $c['gateway']->getId());
        }
        expect($api->refunds)->toBeEmpty()
            ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice WHERE credit_note_for_invoice_id = ?', [$renewal->getId()]))->toBe(1)
            ->and((float) $c['connection']->fetchOne('SELECT refund FROM invoice WHERE id = ?', [$renewal->getId()]))->toBe(3.0)
            ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction" WHERE txn_status = \'Refunded\' AND status = \'processed\' AND invoice_id = ?', [$renewal->getId()]))->toBe(2);
    }
})->with([false, true]);

test('canonical PayPal gateway receipts survive stale errors and discard only independent duplicates', function (bool $duplicate): void {
    $c = deferredPayPalReceiptContext();
    $parentId = $c['receipt']->getId();
    $c['receipt']->setStatus('error');
    $credit = (new Box\Mod\Client\Entity\ClientBalance())->setClient($c['client'])->setType('PayPal')->setAmount('3')
        ->setDescription('PayPal transaction PAYPAL-DEFERRED')->setRelId('PAYPAL-DEFERRED')
        ->setGatewayReceiptKey(hash('sha256', $c['gateway']->getId() . ':PAYPAL-DEFERRED'))->setGatewayTransactionId($parentId);
    $c['em']->persist($credit);
    $receipt = $c['receipt'];
    if ($duplicate) {
        $receipt = (clone $receipt)->setStatus('processing');
        $c['em']->persist($receipt);
    }
    $c['em']->flush();
    $c['adapter']->processTransaction($c['api'], $receipt->getId(), $c['payload'], $c['gateway']->getId());
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT id FROM "transaction"'))->toBe($parentId)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0);
    expect($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processed');
    $c['connection']->update($c['connection']->quoteSingleIdentifier('transaction'), ['status' => 'error'], ['id' => $parentId]);
    $c['em']->refresh($c['receipt']);
    $transactions = new Box\Mod\Invoice\ServiceTransaction();
    $transactions->setDi($c['di']);
    expect(fn () => $transactions->delete($c['receipt']))->toThrow(InformationException::class, 'backing gateway receipt');
})->with([false, true]);

test('independent PayPal deliveries retain only one deferred payment during a renewal hold', function (): void {
    $c = deferredPayPalReceiptContext();
    $duplicate = (clone $c['receipt']);
    $c['em']->persist($duplicate);
    $c['em']->flush();
    foreach ([$c['receipt'], $duplicate] as $receipt) {
        $c['adapter']->processTransaction($c['api'], $receipt->getId(), $c['payload'], $c['gateway']->getId());
    }
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(3.0);
    [$refund, $payload] = deferredPayPalRefund($c, 'REFUND-SINGLE', '-3');
    $c['adapter']->processTransaction($c['api'], $refund->getId(), $payload, $c['gateway']->getId());
    expect((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);
});

test('PayPal resumes financially processed receipts through the transaction worker', function (bool $crash): void {
    require_once PATH_MODS . '/Invoice/tests/Fixtures/GatewayReceiptContext.php';
    [, $em, $client, $gateway, $receipt] = receiptTestContext();
    $gateway->setGateway('PayPalEmail');
    $invoice = (new Invoice())->setClientId($client->getId())->setGateway($gateway)->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setPrice(15)->setQuantity(1));
    $receipt->setInvoice($invoice)->setTxnId('PAYPAL-COMPLETION')->setStatus('received');
    $em->flush();
    $calls = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    $dispatcher->addListener(Box\Mod\Invoice\Event\AfterAdminInvoicePaymentReceivedEvent::class, function () use (&$calls, $crash): void {
        if (++$calls === 1) {
            throw $crash ? new Error('process stopped') : new RuntimeException('listener failed');
        }
    });
    $payload = ['get' => ['invoice_id' => $invoice->getId(), 'sig' => FOSSBilling\Core\Security\Credential::signCallbackParams($gateway->getId(), $invoice->getId())],
        'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed', 'txn_id' => 'PAYPAL-COMPLETION', 'mc_gross' => '15', 'mc_currency' => 'USD']];
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapterCalls = 0;
    $configure = function (EntityManager $manager) use ($dispatcher, $adapter, $payload, $client, $gateway, &$adapterCalls): array {
        $billing = gatewayDepositTestService($manager);
        $di = $billing->getDi();
        $di['event_dispatcher'] = $dispatcher;
        $api = deferredPayPalApi(['connection' => $manager->getConnection(), 'client' => $client, 'em' => $manager, 'di' => $di, 'billing' => $billing]);
        $adapter->setDi($di);
        $di['api_system'] = $api;
        $bridge = new class($adapter, $payload, $gateway->getId(), $adapterCalls) {
            public function __construct(private readonly Payment_Adapter_PayPalEmail $adapter, private readonly array $payload, private readonly int $gatewayId, private int &$calls)
            {
            }

            public function processTransaction(mixed $api, int $id, array $payload, int $gatewayId): void
            {
                ++$this->calls;
                $this->adapter->processTransaction($api, $id, $this->payload, $this->gatewayId);
            }
        };
        $gateways = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
        $gateways->shouldReceive('getPaymentAdapter')->andReturn($bridge);
        $services = $di['mod_service'];
        unset($di['mod_service']);
        $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === 'PayGateway' ? $gateways : $services($module, $sub));
        $worker = new Box\Mod\Invoice\ServiceTransaction();
        $worker->setDi($di);

        return [$worker, $api, $billing];
    };
    [$worker, $api] = $configure($em);
    if ($crash) {
        expect(fn () => $adapter->processTransaction($api, $receipt->getId(), $payload, $gateway->getId()))->toThrow(Error::class);
    } else {
        $worker->processAndCatchErrors($receipt->getId());
    }
    $connection = $em->getConnection();
    $table = $connection->quoteSingleIdentifier('transaction');
    expect($connection->fetchOne("SELECT status FROM $table WHERE id = ?", [$receipt->getId()]))->toBe('processed');
    if ($crash) {
        $connection->update($table, ['updated_at' => date('Y-m-d H:i:s', time() - 600)], ['id' => $receipt->getId()]);
    }
    $fresh = new EntityManager($connection, $em->getConfiguration());
    if ($fresh->getConnection()->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        $fresh->getEventManager()->addEventListener(Doctrine\ORM\Tools\ToolEvents::postGenerateSchemaTable, new FOSSBilling\Core\Doctrine\IndexLengthsListener());
    }
    [$retry, , $billing] = $configure($fresh);
    expect(array_column($retry->getReceived(), 'id'))->toContain($receipt->getId());
    $beforeRetry = $adapterCalls;
    $retry->processAndCatchErrors($receipt->getId());
    expect($adapterCalls)->toBe($beforeRetry);
    $stored = $fresh->find(Box\Mod\Invoice\Entity\Transaction::class, $receipt->getId());
    expect($stored->getError())->toBeEmpty();
    expect($stored->getGatewayCompletionState())->toBe(Box\Mod\Invoice\Entity\Transaction::GATEWAY_COMPLETION_DONE)
        ->and($stored->getStatus())->toBe('processed')->and($stored->getError())->toBeEmpty()
        ->and($billing->getFreeCredit($client->getId()))->toBe(0.0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
        ->and($calls)->toBe(2);
})->with([false, true]);

test('PayPal records collected funds after checkout credit is removed and retries without duplication', function (): void {
    $c = deferredPayPalReceiptContext(null, false, 'web_accept', '60');
    (new SchemaTool($c['em']))->createSchema([$c['em']->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceCredit::class)]);
    $c['invoice']->setStatus('unpaid')->setIssued(true);
    $c['connection']->executeStatement('DELETE FROM client_order_meta');
    $c['connection']->executeStatement('DELETE FROM invoice_item');
    $c['em']->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($c['invoice'])->setType('custom')->setTask('void')->setStatus('pending_payment')->setPrice(100)->setQuantity(1)->setCharged(false));
    $c['em']->flush();
    $fees = new Box\Mod\Invoice\ServiceGatewayFees();
    $fees->setDi($c['di']);
    $balance = new Box\Mod\Client\ServiceBalance();
    $balance->setDi($c['di']);
    $services = $c['di']['mod_service'];
    unset($c['di']['mod_service']);
    $c['di']['mod_service'] = $c['di']->protect(fn (string $module, ?string $sub = null): object => match ($sub) {
        'GatewayFees' => $fees,
        'Balance' => $balance,
        default => $services($module, $sub),
    });
    $payload = $c['payload'];
    $payload['get'] = ['invoice_id' => $c['invoice']->getId(), 'sig' => FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId())];
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $payload, $c['gateway']->getId());
    expect($c['invoice']->getStatus())->toBe('unpaid')
        ->and($c['billing']->getBalanceDue($c['invoice']))->toBe(40.0);
    $c['adapter']->processTransaction($c['api'], $c['receipt']->getId(), $payload, $c['gateway']->getId());
    expect((int) $c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(1)
        ->and((float) $c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(60.0);
});
