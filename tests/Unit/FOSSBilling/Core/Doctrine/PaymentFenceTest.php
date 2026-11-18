<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\GatewayChargeAttempt;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\GatewayChargeAttempts;
use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\DriverManagerFactory;
use FOSSBilling\Core\Doctrine\EntityManagerFactory;
use FOSSBilling\Core\Payment\OwnershipLost;
use FOSSBilling\Core\Payment\ProcessingFence;
use Symfony\Component\Filesystem\Filesystem;

function paymentFenceDatabase(string $driver): array
{
    $dir = sys_get_temp_dir() . '/fb-payment-fence-' . bin2hex(random_bytes(6));
    $filesystem = new Filesystem();
    $filesystem->mkdir($dir);
    $admin = null;
    $database = null;
    if ($driver === 'sqlite') {
        $config = ['driver' => 'pdo_sqlite', 'path' => $dir . '/test.sqlite'];
    } else {
        $raw = getenv('ATTACHMENT_TEST_' . strtoupper($driver) . '_PARAMS');
        if ($raw === false) {
            $filesystem->remove($dir);
            PHPUnit\Framework\Assert::markTestSkipped('An isolated database server is required.');
        }
        $params = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        $admin = DriverManager::getConnection($params);
        $database = 'fb_fence_' . bin2hex(random_bytes(5));
        $admin->executeStatement('CREATE DATABASE ' . $admin->quoteSingleIdentifier($database));
        $config = ['name' => $database] + $params;
    }
    $db = DriverManagerFactory::getConnection(dbConfig: $config);
    $em = EntityManagerFactory::create($db);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Box\Mod\Invoice\Entity\InvoiceItem::class, Box\Mod\Invoice\Entity\InvoiceCredit::class, Client::class, PayGateway::class, Invoice::class, Transaction::class, ClientBalance::class, GatewayChargeAttempt::class, Box\Mod\Invoice\Entity\InvoiceCardCharge::class]));
    $client = (new Client())->setCurrency('USD');
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($client);
    $em->persist($gateway);
    $em->flush();
    $invoice = (new Invoice())->setClientId(1)->setCurrency('USD')->setStatus('unpaid')->setIssued(true)->setGateway($gateway);
    $em->persist($invoice);
    $em->flush();
    $tx = (new Transaction())->setInvoice($invoice)->setGateway($gateway)->setStatus('received')->setTxnStatus('processing')->setAmount('10.00')->setCurrency('USD');
    $balance = (new ClientBalance())->setClient($client)->setAmount('0.00');
    $em->persist($tx);
    $em->persist($balance);
    $em->flush();
    $di = Tests\Helpers\container();
    $di['em'] = $em;
    $service = new ServiceTransaction();
    $service->setDi($di);
    $cleanup = function () use ($db, $admin, $database, $dir, $filesystem): void {
        $db->close();
        if ($admin !== null) {
            $admin->executeStatement('DROP DATABASE ' . $admin->quoteSingleIdentifier($database));
            $admin->close();
        }
        $filesystem->remove($dir);
    };

    return [$db, $config, $service, $cleanup];
}

function paymentFenceWorker(array $config, string $action, string $charge = '', int $id = 1): array
{
    $env = array_merge(getenv(), [
        'PAYMENT_FENCE_DB' => json_encode($config, JSON_THROW_ON_ERROR),
        'PAYMENT_FENCE_ACTION' => $action, 'PAYMENT_FENCE_CHARGE' => $charge, 'PAYMENT_FENCE_TX' => (string) $id,
    ]);
    $process = proc_open([PHP_BINARY, PATH_TESTS . '/Support/PaymentFenceWorker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start payment worker');
    }
    stream_set_timeout($pipes[1], 15);
    $line = trim((string) fgets($pipes[1]));
    if ($line !== 'ready') {
        proc_terminate($process);

        throw new RuntimeException('Worker did not reach barrier: ' . $line . stream_get_contents($pipes[2]));
    }

    return [$process, $pipes];
}

function paymentFenceResult(array $worker): array
{
    [$process, $pipes] = $worker;
    $result = trim((string) fgets($pipes[1]));
    foreach ([0, 1] as $index) {
        fclose($pipes[$index]);
    }
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    expect(proc_close($process))->toBe(0, $error);

    return json_decode($result, true, flags: JSON_THROW_ON_ERROR);
}

test('a displaced worker cannot mutate payment state through any application SQL path', function (string $driver, string $action): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $worker = paymentFenceWorker($config, $action);
        $table = $db->quoteSingleIdentifier('transaction');
        $old = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $db->executeStatement("UPDATE {$table} SET updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        expect($service->releaseStaleProcessing(1, 9, $old))->toBeTrue()
            ->and($service->claimForProcessing(1))->toBeTrue();
        $current = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        expect($current)->not->toBe($old);
        $db->executeStatement("UPDATE {$table} SET amount = '20.00', error = 'new owner' WHERE id = 1");
        fwrite($worker[1][0], "go\n");
        $result = paymentFenceResult($worker);
        expect($result['error'])->toBe(OwnershipLost::class)->and($result['scopes'])->toBe(0)
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = 1'))->toBe('unpaid')
            ->and((float) $db->fetchOne('SELECT COALESCE(refund, 0) FROM invoice WHERE id = 1'))->toBe(0.0)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
            ->and((float) $db->fetchOne("SELECT amount FROM {$table} WHERE id = 1"))->toBe(20.0)
            ->and($db->fetchOne("SELECT error FROM {$table} WHERE id = 1"))->toBe('new owner')
            ->and($db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1"))->toBe($current);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with(['raw', 'swallowed', 'reconnect', 'refresh_then_write', 'refund_write', 'query_write', 'prepared', 'wrapped_prepared', 'orm', 'other_manager', 'client_funds', 'invoice_paid', 'clearing', 'transaction_update']);

test('revoked charge ownership also fences downstream invoice and balance writes', function (string $driver, bool $pendingRecovery): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $attempt = GatewayChargeAttempts::begin($db, 1, 1);
        $worker = paymentFenceWorker($config, 'client_funds', $attempt);
        $oldOwner = GatewayChargeAttempts::pending($db, 1)['owner_token'];
        if ($pendingRecovery) {
            $newOwner = GatewayChargeAttempts::takeOwnership($db, $attempt, $oldOwner);
            expect($newOwner)->not->toBe($oldOwner);
            $replacement = $attempt;
        } else {
            GatewayChargeAttempts::resolve($db, $attempt, 'failed', ['staff_id' => 9, 'note' => 'Provider reconciled; old worker stopped.']);
            $replacement = GatewayChargeAttempts::begin($db, 1, 1);
        }
        fwrite($worker[1][0], "go\n");
        $result = paymentFenceResult($worker);
        expect($result['error'])->toBe(OwnershipLost::class)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
            ->and(GatewayChargeAttempts::pending($db, 1)['id'])->toBe($replacement);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([true, false]);

test('ownership revocation is atomic with settlement commit or rollback', function (string $driver, bool $commit): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $worker = paymentFenceWorker($config, $commit ? 'settlement_commit' : 'settlement_rollback');
        $table = $db->quoteSingleIdentifier('transaction');
        $db->executeStatement("UPDATE {$table} SET updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        fwrite($worker[1][0], "go\n");
        expect(trim((string) fgets($worker[1][1])))->toBe('written');
        $release = paymentFenceWorker($config, 'release');
        usleep(250000);
        expect(proc_get_status($release[0])['running'])->toBeTrue();
        fwrite($worker[1][0], "go\n");
        if (!$commit) {
            expect(trim((string) fgets($worker[1][1])))->toBe('rolled_back');
        }
        $released = paymentFenceResult($release);
        expect($released['released'])->toBe(!$commit);
        if (!$commit) {
            expect($service->claimForProcessing(1))->toBeTrue();
            fwrite($worker[1][0], "go\n");
        }
        $result = paymentFenceResult($worker);
        if ($commit) {
            expect($result['completed'])->toBeTrue()->and($service->claimForProcessing(1))->toBeFalse();
        } else {
            expect($result['error'])->toBe(OwnershipLost::class);
        }
        expect((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe($commit ? 10.0 : 0.0)
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = 1'))->toBe($commit ? 'paid' : 'unpaid')
            ->and($db->fetchOne("SELECT status FROM {$table} WHERE id = 1"))->toBe($commit ? 'processed' : 'processing');
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([true, false]);

test('a stale recovery form cannot release the replacement processing claim', function (): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase('sqlite');

    try {
        $table = $db->quoteSingleIdentifier('transaction');
        expect($service->claimForProcessing(1))->toBeTrue();
        $old = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $db->executeStatement("UPDATE {$table} SET updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        $service->releaseStaleProcessing(1, 9, $old);
        $service->claimForProcessing(1);
        $current = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $db->executeStatement("UPDATE {$table} SET updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        expect(fn () => $service->releaseStaleProcessing(1, 9, $old))->toThrow(FOSSBilling\Core\Exception\InformationException::class)
            ->and($db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1"))->toBe($current);
    } finally {
        $cleanup();
    }
});

test('native access and transaction leaks cannot bypass a payment fence', function (string $driver): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $service->claimForProcessing(1);
        $token = $db->fetchOne('SELECT processing_token FROM ' . $db->quoteSingleIdentifier('transaction') . ' WHERE id = 1');
        ProcessingFence::transaction($db, 1, $token, function () use ($db): void {
            expect(fn () => $db->getNativeConnection())->toThrow(LogicException::class)
                ->and(fn () => $db->executeStatement('CREATE TABLE bypass (id INTEGER)'))->toThrow(LogicException::class)
                ->and(fn () => $db->executeStatement('SET autocommit = 1'))->toThrow(LogicException::class)
                ->and(fn () => $db->executeStatement('LOCK TABLES client_balance WRITE'))->toThrow(LogicException::class)
                ->and(fn () => $db->executeStatement('XA COMMIT \'old-owner\''))->toThrow(LogicException::class)
                ->and(fn () => $db->executeStatement('ANALYZE TABLE client_balance'))->toThrow(LogicException::class)
                ->and(fn () => $db->executeQuery('SELECT 1; UPDATE client_balance SET amount = 999'))->toThrow(LogicException::class);
        });
        expect(fn () => ProcessingFence::transaction($db, 1, $token, function () use ($db): void {
            $db->beginTransaction();
            $db->executeStatement('UPDATE client_balance SET amount = 999 WHERE id = 1');
            // Forgetting the commit must roll back before the scope is removed.
        }))->toThrow(LogicException::class, 'unbalanced');
        expect($db->isTransactionActive())->toBeFalse()
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
            ->and(ProcessingFence::claims())->toBe([]);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('legacy payment rows gain fencing through additive and repeatable upgrades', function (string $driver): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $attempt = GatewayChargeAttempts::begin($db, 1, 1);
        $table = $db->quoteSingleIdentifier('transaction');
        $db->executeStatement("UPDATE {$table} SET status = 'processing', updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        $db->executeStatement("ALTER TABLE {$table} DROP COLUMN processing_token");
        $db->executeStatement("ALTER TABLE {$table} DROP COLUMN credit_recovery_state");
        $db->executeStatement('ALTER TABLE invoice_gateway_charge_attempt DROP COLUMN owner_token');
        $db->createSchemaManager()->dropIndex('uniq_payment_credit', 'client_balance');
        $db->executeStatement('ALTER TABLE client_balance DROP COLUMN payment_key');
        if ($driver === 'mysql') {
            $patcher = new class($db) extends FOSSBilling\Core\Update\Patcher {
                public function __construct(private readonly Doctrine\DBAL\Connection $connection)
                {
                    parent::__construct();
                }

                public function tableHasColumn(string $table, string $column): bool
                {
                    return $this->connection->createSchemaManager()->introspectTable($table)->hasColumn($column);
                }

                public function tableHasIndex(string $table, string $indexName): bool
                {
                    return $this->connection->createSchemaManager()->introspectTable($table)->hasIndex($indexName);
                }

                public function executeSql(string $sql, array $params = []): void
                {
                    $this->connection->executeStatement($sql, $params);
                }
            };
            $patch = new FOSSBilling\Core\Update\Patch\Patch161();
            $patch->apply($patcher);
            $patch->apply($patcher);
        }
        $classes = [Transaction::class, GatewayChargeAttempt::class, ClientBalance::class];
        FOSSBilling\Core\Doctrine\SchemaSynchronizer::syncEntities($service->getDi()['em'], $classes);
        $repeat = FOSSBilling\Core\Doctrine\SchemaSynchronizer::syncEntities($service->getDi()['em'], $classes);
        $service->getDi()['em']->clear();
        expect($repeat['applied'])->toBe([])
            ->and($db->fetchOne("SELECT credit_recovery_state FROM {$table} WHERE id = 1"))->toBeNull()
            ->and($db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1"))->toBeNull()
            ->and(GatewayChargeAttempts::pending($db, 1)['owner_token'])->toBeNull();
        expect($service->releaseStaleProcessing(1, 9, null))->toBeTrue()
            ->and($service->claimForProcessing(1))->toBeTrue()
            ->and($db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1"))->toMatch('/^[a-f0-9]{32}$/');
        $owner = GatewayChargeAttempts::takeOwnership($db, $attempt, null);
        ProcessingFence::charge($db, $attempt, $owner, fn () => $db->executeStatement("UPDATE invoice SET status = 'unpaid' WHERE id = 1"));
        expect(GatewayChargeAttempts::pending($db, 1)['id'])->toBe($attempt);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('a fenced adapter may deduplicate its own row without emitting a completion event', function (): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase('sqlite');

    try {
        $di = $service->getDi();
        $em = $di['em'];
        $model = $em->find(Transaction::class, 1);
        $adapter = new readonly class($em) {
            public function __construct(private Doctrine\ORM\EntityManagerInterface $em)
            {
            }

            public function processTransaction($api, int $id, array $data, int $gatewayId): void
            {
                $this->em->remove($this->em->find(Transaction::class, $id));
                $this->em->flush();
            }
        };
        $gateways = Mockery::mock();
        $gateways->shouldReceive('getPaymentAdapter')->once()->andReturn($adapter);
        $di['mod_service'] = $di->protect(fn (): object => $gateways);
        $di['api_system'] = new stdClass();
        $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
        $events = [];
        $di['event_dispatcher']->addListener(Box\Mod\Invoice\Event\AfterAdminTransactionProcessEvent::class, function ($event) use (&$events): void { $events[] = $event; });
        expect($service->preProcessTransaction($model))->toBeTrue()
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(0)
            ->and($events)->toBe([])->and(ProcessingFence::claims())->toBe([]);
    } finally {
        $cleanup();
    }
});

test('suppressed ownership loss closes the transaction worker manager before stale objects can escape', function (): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase('sqlite');
    $admin = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $config['path']]);

    try {
        $di = $service->getDi();
        $em = $di['em'];
        $model = $em->find(Transaction::class, 1);
        $adapter = new readonly class($db, $admin, $model) {
            public function __construct(private Doctrine\DBAL\Connection $db, private Doctrine\DBAL\Connection $admin, private Transaction $model)
            {
            }

            public function processTransaction($api, int $id, array $data, int $gatewayId): void
            {
                $this->model->setAmount('999');
                // This connection represents a different worker/request context.
                $this->admin->executeStatement('UPDATE "transaction" SET processing_token = ?, amount = ?, error = ? WHERE id = 1', [str_repeat('b', 32), '20.00', 'new owner']);

                try {
                    $this->db->fetchOne('SELECT 1');
                } catch (OwnershipLost) {
                    // Deliberately simulate an adapter swallowing the exception.
                }
            }
        };
        $gateways = Mockery::mock();
        $gateways->shouldReceive('getPaymentAdapter')->once()->andReturn($adapter);
        $di['mod_service'] = $di->protect(fn (): object => $gateways);
        $di['api_system'] = new stdClass();
        expect(fn () => $service->preProcessTransaction($model))->toThrow(OwnershipLost::class)
            ->and($em->isOpen())->toBeFalse()
            ->and(fn () => $em->flush())->toThrow(Doctrine\ORM\Exception\EntityManagerClosed::class)
            ->and((float) $db->fetchOne('SELECT amount FROM "transaction" WHERE id = 1'))->toBe(20.0)
            ->and($db->fetchOne('SELECT error FROM "transaction" WHERE id = 1'))->toBe('new owner')
            ->and(ProcessingFence::claims())->toBe([]);
    } finally {
        $admin->close();
        $cleanup();
    }
});

test('a displaced saved-charge worker cannot leak a dirty invoice into later work', function (): void {
    [$db, $config, $transactions, $cleanup] = paymentFenceDatabase('sqlite');
    $admin = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $config['path']]);

    try {
        $db->executeStatement('UPDATE "transaction" SET status = ?, txn_status = ? WHERE id = 1', ['error', 'failed']);
        $di = $transactions->getDi();
        $em = $di['em'];
        $invoice = $em->find(Invoice::class, 1);
        $fees = Mockery::mock();
        $fees->shouldReceive('apply')->once();
        $invoices = new Box\Mod\Invoice\Service();
        $invoices->setDi($di);
        $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === '' ? $invoices : $fees);
        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('gatewayId')->andReturn(1);
        $adapter->shouldReceive('savedCard')->once()->andReturn(['card' => 'test']);
        $adapter->shouldReceive('chargeSavedCard')->once()->andReturnUsing(function (Invoice $invoice, string $attempt) use ($db, $admin): string {
            $invoice->setNotes('stale dirty invoice');
            $admin->executeStatement('UPDATE invoice_gateway_charge_attempt SET owner_token = ? WHERE id = ?', [str_repeat('b', 32), $attempt]);

            try {
                $db->fetchOne('SELECT 1');
            } catch (OwnershipLost) {
                // A successful provider response cannot restore ownership.
            }

            return 'pi_taken';
        });
        $service = new Box\Mod\Invoice\ServiceSavedCards();
        $service->setDi($di);
        expect(fn () => $service->charge($invoice, $adapter, true))->toThrow(OwnershipLost::class)
            ->and($em->isOpen())->toBeFalse()
            ->and(fn () => $em->flush())->toThrow(Doctrine\ORM\Exception\EntityManagerClosed::class)
            ->and($db->fetchOne('SELECT notes FROM invoice WHERE id = 1'))->toBeNull()
            ->and(GatewayChargeAttempts::pending($db, 1)['payment_id'])->toBeNull()
            ->and(ProcessingFence::claims())->toBe([]);
    } finally {
        $admin->close();
        $cleanup();
    }
});

test('a displaced recovery worker discards its dirty unit of work', function (): void {
    [$db, $config, $transactions, $cleanup] = paymentFenceDatabase('sqlite');
    $admin = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $config['path']]);

    try {
        $di = $transactions->getDi();
        $em = $di['em'];
        $invoice = $em->find(Invoice::class, 1);
        $attempt = GatewayChargeAttempts::begin($db, 1, 1);
        GatewayChargeAttempts::recordPayment($db, $attempt, 'pi_paid');
        $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
        $adapter->shouldReceive('inspectPayment')->once()->with('pi_paid', 1)->andReturn(['state' => 'settled', 'reference' => 'pi_paid', 'currency' => 'USD']);
        $gateways = Mockery::mock();
        $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
        $processor = Mockery::mock();
        $processor->shouldReceive('createAndProcess')->once()->andReturnUsing(function () use ($db, $admin, $invoice, $attempt): int {
            $invoice->setNotes('stale recovery state');
            $admin->executeStatement('UPDATE invoice_gateway_charge_attempt SET owner_token = ? WHERE id = ?', [str_repeat('c', 32), $attempt]);

            try {
                $db->fetchOne('SELECT 1');
            } catch (OwnershipLost) {
                // A child processor must not suppress the loss for its caller.
            }

            return 44;
        });
        $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['invoice:paygateway' => $gateways, 'invoice:transaction' => $processor]));
        $recovery = new Box\Mod\Invoice\ServicePaymentRecovery();
        $recovery->setDi($di);
        expect(fn () => $recovery->reconcile($attempt, null, false, 'Provider checked', 9))->toThrow(OwnershipLost::class)
            ->and($em->isOpen())->toBeFalse()
            ->and(fn () => $em->flush())->toThrow(Doctrine\ORM\Exception\EntityManagerClosed::class)
            ->and($db->fetchOne('SELECT notes FROM invoice WHERE id = 1'))->toBeNull()
            ->and(GatewayChargeAttempts::pending($db, 1)['owner_token'])->toBe(str_repeat('c', 32))
            ->and(ProcessingFence::claims())->toBe([]);
    } finally {
        $admin->close();
        $cleanup();
    }
});

test('recovery does not repeat a wallet credit committed before takeover', function (string $driver): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $worker = paymentFenceWorker($config, 'prefix_credit');
        $table = $db->quoteSingleIdentifier('transaction');
        $old = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $db->executeStatement("UPDATE {$table} SET updated_at = '2000-01-01 00:00:00' WHERE id = 1");
        fwrite($worker[1][0], "go\n");
        expect(trim((string) fgets($worker[1][1])))->toBe('credited');
        expect((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0);
        $service->releaseStaleProcessing(1, 9, $old);
        $service->claimForProcessing(1);
        $current = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $clients = new Box\Mod\Client\Service();
        $clients->setDi($service->getDi());
        $client = $service->getDi()['em']->find(Client::class, 1);
        ProcessingFence::transaction($db, 1, $current, fn () => $clients->addFunds($client, 10, 'Retry after takeover'));
        expect(fn () => ProcessingFence::transaction($db, 1, $current, fn () => $clients->addFunds($client, 20, 'Wrong retry amount')))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
        fwrite($worker[1][0], "go\n");
        $result = paymentFenceResult($worker);
        expect($result['error'])->toBe(OwnershipLost::class)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE payment_key IS NOT NULL'))->toBe(1);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('concurrent callback rows share one provider credit without closing their managers', function (string $driver): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $em = $service->getDi()['em'];
        $second = (new Transaction())->setInvoice($em->find(Invoice::class, 1))->setGateway($em->find(PayGateway::class, 1))->setStatus('received');
        $em->persist($second);
        $em->flush();
        $firstWorker = paymentFenceWorker($config, 'credit_once');
        $secondWorker = paymentFenceWorker($config, 'credit_once', id: 2);
        fwrite($firstWorker[1][0], "go\n");
        fwrite($secondWorker[1][0], "go\n");
        expect(paymentFenceResult($firstWorker)['completed'])->toBeTrue()
            ->and(paymentFenceResult($secondWorker)['completed'])->toBeTrue()
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE payment_key IS NOT NULL'))->toBe(1);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql']);

test('legacy wallet recovery requires an explicit reviewed credit or absence decision', function (string $driver, bool $existingCredit): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $em = $service->getDi()['em'];
        $gateway = $em->find(PayGateway::class, 1);
        $gateway->setGateway('Custom');
        $em->flush();
        $table = $db->quoteSingleIdentifier('transaction');
        $db->executeStatement("UPDATE {$table} SET credit_recovery_state = NULL WHERE id = 1");
        if ($existingCredit) {
            $db->insert('client_balance', ['id' => 2, 'client_id' => 1, 'type' => 'gift', 'amount' => '10.00', 'description' => 'Historic credit']);
        }
        $em->clear();
        $service->claimForProcessing(1);
        $token = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        $client = $em->find(Client::class, 1);
        $clients = new Box\Mod\Client\Service();
        $clients->setDi($service->getDi());
        expect(fn () => ProcessingFence::transaction($db, 1, $token, fn () => $clients->addFunds($client, 10, 'Retry')))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'wallet');
        expect($service->reconcileWalletCredit(1, $existingCredit ? 2 : null, !$existingCredit, $token, 9, 'Gateway and wallet checked; worker stopped.'))->toBeTrue();
        expect($db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1"))->toBeNull()
            ->and($db->fetchOne("SELECT note FROM {$table} WHERE id = 1"))->toContain('Staff #9');
        $service->claimForProcessing(1);
        $current = $db->fetchOne("SELECT processing_token FROM {$table} WHERE id = 1");
        ProcessingFence::transaction($db, 1, $current, function () use ($clients, $client): void {
            $clients->addFunds($client, 10, 'Finish recovery');
            $clients->addFunds($client, 10, 'Retry again');
        });
        expect((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE payment_key IS NOT NULL'))->toBe(1);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with([true, false]);

test('wallet reconciliation rejects a stale claim and mismatched credit without changing money', function (string $invalid): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase('sqlite');

    try {
        $em = $service->getDi()['em'];
        $em->find(PayGateway::class, 1)->setGateway('Custom');
        $em->flush();
        $db->insert('client_balance', ['id' => 2, 'client_id' => 1, 'type' => 'gift', 'amount' => $invalid === 'amount' ? '20.00' : '10.00']);
        $service->claimForProcessing(1);
        $token = $db->fetchOne('SELECT processing_token FROM "transaction" WHERE id = 1');
        expect(fn () => $service->reconcileWalletCredit(1, 2, false, $invalid === 'token' ? str_repeat('f', 32) : $token, 9, 'Reviewed'))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
        expect($db->fetchOne('SELECT payment_key FROM client_balance WHERE id = 2'))->toBeNull()
            ->and($db->fetchOne('SELECT processing_token FROM "transaction" WHERE id = 1'))->toBe($token);
    } finally {
        $cleanup();
    }
})->with(['amount', 'token']);

test('fresh callbacks require review of matching unmarked wallet history', function (string $driver, string $evidence): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $em = $service->getDi()['em'];
        $transaction = $em->find(Transaction::class, 1);
        $transaction->setTxnId('pi_legacy');
        $em->flush();
        if ($evidence === 'transaction') {
            $old = new Transaction();
            $old->setGateway($transaction->getGateway())->setInvoice($transaction->getInvoice())
                ->setTxnId('pi_legacy')->setCreditRecoveryState(null);
            $em->persist($old);
            $em->flush();
        } else {
            $db->insert('client_balance', ['id' => 2, 'client_id' => 1, 'type' => 'transaction', 'amount' => '10.00', 'description' => 'Stripe transaction pi_legacy']);
        }
        $service->claimForProcessing(1);
        $token = $db->fetchOne('SELECT processing_token FROM ' . $db->quoteSingleIdentifier('transaction') . ' WHERE id = 1');
        $clients = new Box\Mod\Client\Service();
        $clients->setDi($service->getDi());
        $client = $em->find(Client::class, 1);
        expect(fn () => ProcessingFence::transaction($db, 1, $token, fn () => $clients->addFunds($client, 10, 'Retry', ['type' => 'transaction'])))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE payment_key IS NOT NULL'))->toBe(0);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with(['transaction', 'ledger']);

test('a duplicate credit arriving after an earlier transaction read remains idempotent', function (string $driver): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $em = $service->getDi()['em'];
        $second = (new Transaction())->setInvoice($em->find(Invoice::class, 1))->setGateway($em->find(PayGateway::class, 1))->setStatus('received');
        $em->persist($second);
        $em->flush();
        $worker = paymentFenceWorker($config, 'credit_snapshot', id: 2);
        fwrite($worker[1][0], "go\n");
        expect(trim((string) fgets($worker[1][1])))->toBe('snapshot');
        $service->claimForProcessing(1);
        $token = $db->fetchOne('SELECT processing_token FROM ' . $db->quoteSingleIdentifier('transaction') . ' WHERE id = 1');
        $clients = new Box\Mod\Client\Service();
        $clients->setDi($service->getDi());
        $client = $em->find(Client::class, 1);
        ProcessingFence::transaction($db, 1, $token, fn () => $clients->addFunds($client, 10, 'Winning credit', ['type' => 'transaction', 'payment_reference' => 'pi_once']));
        fwrite($worker[1][0], "go\n");
        $result = paymentFenceResult($worker);
        PHPUnit\Framework\Assert::assertTrue($result['completed'] ?? false, json_encode($result, JSON_THROW_ON_ERROR));
        expect((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE payment_key IS NOT NULL'))->toBe(1);
    } finally {
        $cleanup();
    }
})->with(['mysql']);

test('concurrent Stripe callbacks preserve one atomically settled receipt', function (string $driver, string $action): void {
    [$db, $config, $service, $cleanup] = paymentFenceDatabase($driver);

    try {
        $em = $service->getDi()['em'];
        $second = (new Transaction())->setInvoice($em->find(Invoice::class, 1))->setGateway($em->find(PayGateway::class, 1))->setStatus('received')->setCurrency('USD');
        $em->persist($second);
        $em->flush();
        $firstWorker = paymentFenceWorker($config, 'stripe_initial');
        fwrite($firstWorker[1][0], "go\n");
        expect(trim((string) fgets($firstWorker[1][1])))->toBe('settled');
        // The first callback is still live. Its money and invoice already committed together.
        expect($db->fetchOne('SELECT status FROM invoice WHERE id = 1'))->toBe('paid')
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0);
        $secondWorker = paymentFenceWorker($config, $action, id: 2);
        fwrite($secondWorker[1][0], "go\n");
        expect(trim((string) fgets($secondWorker[1][1])))->toBe('settled');
        fwrite($secondWorker[1][0], "go\n");
        fwrite($firstWorker[1][0], "go\n");
        expect(paymentFenceResult($firstWorker)['completed'])->toBeTrue()
            ->and(paymentFenceResult($secondWorker)['completed'])->toBeTrue()
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10.0)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((int) $db->fetchOne('SELECT gateway_transaction_id FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((float) $db->fetchOne('SELECT amount FROM ' . $db->quoteSingleIdentifier('transaction') . ' WHERE id = 2'))->toBe(0.0);
    } finally {
        $cleanup();
    }
})->with(['sqlite', 'mysql', 'pgsql'])->with(['stripe_initial', 'stripe_invoice']);
