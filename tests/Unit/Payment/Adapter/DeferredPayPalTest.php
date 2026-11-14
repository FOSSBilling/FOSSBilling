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
            ->and($worker->getOutput())->toContain('processed')
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
