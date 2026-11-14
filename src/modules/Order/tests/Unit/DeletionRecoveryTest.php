<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\DeletionRecovery;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Box\Mod\Order\Service;
use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Exception\InformationException;

use function Tests\Helpers\container;

require_once PATH_TESTS . '/Helpers/DeferredPayPal.php';

function deletionRecoveryContext(?string $databasePath = null): array
{
    $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('DeletionRecoveryTestProxies');
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite'] + ($databasePath === null ? ['memory' => true] : ['path' => $databasePath]));
    $em = new EntityManager($connection, $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Order::class, OrderMeta::class, OrderStatus::class, ServiceLicense::class, Invoice::class, InvoiceItem::class, Box\Mod\Invoice\Entity\Transaction::class, Box\Mod\Invoice\Entity\Subscription::class, Box\Mod\Invoice\Entity\PayGateway::class, Box\Mod\Client\Entity\Client::class, Box\Mod\Client\Entity\ClientBalance::class]));
    $license = new ServiceLicense();
    $em->persist($license);
    $em->flush();
    $order = (new Order())->setServiceType('license')->setServiceId($license->getId())->setStatus(Order::STATUS_ACTIVE)->setGroupMaster(false)
        ->setPrice(10)->setPeriod('1M')->setInvoiceOption('issue-invoice')->setExpiresAt(new DateTime('+1 day'));
    $em->persist($order);
    $em->flush();
    $di = container();
    $di['em'] = $em;
    $service = new Service();
    $service->setDi($di);
    $backend = new class extends Box\Mod\Servicelicense\Service {
        public int $calls = 0;
        public ?string $failure = null;

        public function action_delete(Order $order, bool $forceDelete = false): void
        {
            ++$this->calls;
            if ($this->failure === 'refusal') {
                throw new RuntimeException('Backend refused deletion');
            }
            parent::action_delete($order, $forceDelete);
            if ($this->failure === 'crash') {
                throw new Error('Interrupted after backend deletion');
            }
        }
    };
    $backend->setDi($di);
    $subscription = new class {
        public int $calls = 0;
        public bool $fail = true;

        public function cancelForOrder(Order $order, bool $force = false): int
        {
            ++$this->calls;
            if ($this->fail && !$force) {
                throw new RuntimeException('Gateway unavailable');
            }

            return 1;
        }
    };
    $product = new class {
        public bool $fail = false;

        public function releaseReservedPromoRedemptionsForOrder(...$args): void
        {
            if ($this->fail) {
                throw new RuntimeException('Local cleanup failed');
            }
        }

        public function releaseReservedStockForOrder(...$args): void
        {
        }
    };
    $system = new class {
        public function getParamValue(...$args): int
        {
            return 14;
        }
    };
    $di['mod_service'] = $di->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $service, 'servicelicense' => $backend, 'invoice' => $subscription, 'product' => $product, 'system' => $system,
    });

    return compact('config', 'connection', 'em', 'order', 'di', 'service', 'backend', 'subscription', 'product');
}

function failDeletionAtGateway(array $context): Order
{
    expect(fn () => $context['service']->deleteFromOrder($context['order']))->toThrow(RuntimeException::class, 'Gateway unavailable');
    $context['em']->clear();

    return $context['em']->find(Order::class, $context['order']->getId());
}

test('gateway failure leaves a durable billing hold and retry skips completed backend deletion', function (): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(0)
        ->and(DeletionRecovery::getState($c['connection'], $order->getId()))->toBe(DeletionRecovery::SERVICE_DELETED)
        ->and($c['service']->getSoonExpiringActiveOrders())->toBeEmpty();
    $c['service']->updateOrderMeta($order, []);
    expect(DeletionRecovery::getState($c['connection'], $order->getId()))->toBe(DeletionRecovery::SERVICE_DELETED);
    expect(fn () => $c['service']->updateOrderMeta($order, [Service::META_DELETION_STATE => '']))->toThrow(InformationException::class);
    $c['subscription']->fail = false;
    expect($c['service']->deleteFromOrder($order))->toBeTrue()
        ->and($c['backend']->calls)->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
});

test('held orders cannot enter alternate fulfillment paths', function (string $entry): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    expect(fn () => $c['service']->$entry($order, ...($entry === 'activateOrder' ? [['force' => true]] : [])))
        ->toThrow(InformationException::class, 'awaiting recovery');
    expect($c['backend']->calls)->toBe(1);
})->with(['renewFromOrder', 'createFromOrder', 'activateOrder', 'unsuspendFromOrder', 'uncancelFromOrder']);

test('held orders cannot generate direct or merged invoices, including unpaid invoice reuse', function (bool $merged): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID);
    $c['em']->persist($invoice);
    $c['em']->flush();
    $order->setUnpaidInvoiceId($invoice->getId());
    $c['em']->flush();
    $billing = new Box\Mod\Invoice\Service();
    $billing->setDi($c['di']);
    expect(fn () => $merged ? $billing->generateMergedInvoiceForOrders([$order]) : $billing->generateForOrder($order))
        ->toThrow(InformationException::class, 'awaiting recovery');
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
})->with([false, true]);

test('interrupted or refused backend deletion retains an uncertain hold without canceling billing', function (string $failure): void {
    $c = deletionRecoveryContext();
    $c['backend']->failure = $failure;
    expect(fn () => $c['service']->deleteFromOrder($c['order']))->toThrow($failure === 'crash' ? Error::class : RuntimeException::class);
    expect(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBe(DeletionRecovery::STARTED)
        ->and($c['subscription']->calls)->toBe(0)
        ->and($c['service']->getSoonExpiringActiveOrders())->toBeEmpty();
    expect(fn () => $c['service']->deleteFromOrder($c['order']))->toThrow(InformationException::class, 'Staff must review');
    $c['backend']->failure = null;
    expect($c['service']->deleteFromOrder($c['order'], true))->toBeTrue();
})->with(['refusal', 'crash']);

test('failed local cleanup cannot remove the hold before the order is removed', function (): void {
    $c = deletionRecoveryContext();
    $c['subscription']->fail = false;
    $c['product']->fail = true;
    expect(fn () => $c['service']->deleteFromOrder($c['order']))->toThrow(RuntimeException::class, 'Local cleanup failed');
    expect(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBe(DeletionRecovery::SERVICE_DELETED);
    $em = new EntityManager($c['connection'], $c['config']);
    $c['di']['em'] = $em;
    $service = new Service();
    $service->setDi($c['di']);
    $c['product']->fail = false;
    expect($service->deleteFromOrder($em->find(Order::class, $c['order']->getId())))->toBeTrue()
        ->and($c['backend']->calls)->toBe(1);
});

test('existing invoices cannot start checkout, issue or consume credits for a held order', function (string $operation): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID)->setIssued($operation !== 'issue')->setHash(str_repeat('a', 40))->setClientId(1);
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $order->getId())->setTask(InvoiceItem::TASK_RENEW)->setStatus(InvoiceItem::STATUS_PENDING_SETUP)->setPrice(10)->setQuantity(1);
    $c['em']->persist($invoice);
    $c['em']->persist($item);
    $c['em']->flush();
    $billing = new Box\Mod\Invoice\Service();
    $billing->setDi($c['di']);
    $call = match ($operation) {
        'checkout' => fn () => $billing->processInvoice(['hash' => $invoice->getHash(), 'gateway_id' => 1]),
        'credits' => fn () => $billing->tryPayWithCredits($invoice),
        'issue' => fn () => $billing->issueInvoice($invoice, ['use_credits' => true]),
    };
    if ($operation === 'checkout') {
        expect($call)->toThrow(InformationException::class, 'awaiting recovery');
    } else {
        expect($call())->toBeFalse();
    }
    expect($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_UNPAID);
})->with(['checkout', 'credits', 'issue']);

test('held paid invoice tasks and subscription callbacks cannot renew a deleted service', function (string $operation): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_PAID)->setIssued(true)->setClientId(1);
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $order->getId())->setTask(InvoiceItem::TASK_RENEW)->setStatus(InvoiceItem::STATUS_PENDING_SETUP)->setPrice(10)->setQuantity(1);
    $c['em']->persist($invoice);
    $c['em']->persist($item);
    $c['em']->flush();
    $expiry = $order->getExpiresAt()->format(DATE_ATOM);
    if ($operation === 'task') {
        $items = new Box\Mod\Invoice\ServiceInvoiceItem();
        $items->setDi($c['di']);
        $items->executeTask($item);
        expect($item->getStatus())->not->toBe(InvoiceItem::STATUS_EXECUTED);
    } else {
        $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('held-subscription')->setRelType('invoice')->setRelId($invoice->getId());
        $c['em']->persist($subscription);
        $c['em']->flush();
        $billing = new Box\Mod\Invoice\Service();
        $billing->setDi($c['di']);
        expect($billing->generateRenewalInvoiceForSubscriptionPayment('held-subscription', 1))->toBeNull();
    }
    expect($order->getExpiresAt()->format(DATE_ATOM))->toBe($expiry)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
})->with(['task', 'subscription']);

test('deletion refuses an outer transaction before any remote or durable changes', function (): void {
    $c = deletionRecoveryContext();
    $c['connection']->beginTransaction();

    try {
        expect(fn () => $c['service']->deleteFromOrder($c['order']))->toThrow(InformationException::class, 'inside a database transaction');
        expect($c['backend']->calls)->toBe(0)
            ->and(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBeNull();
    } finally {
        $c['connection']->rollBack();
    }
});

test('a concurrent callback reads the durable hold after acquiring the shared lifecycle lock', function (): void {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $databasePath = $filesystem->tempnam(sys_get_temp_dir(), 'deletion-hold-');
    $c = deletionRecoveryContext($databasePath);
    $lock = 'order_lifecycle_' . $c['order']->getId();
    $process = new Symfony\Component\Process\Process([PHP_BINARY, PATH_TESTS . '/Fixtures/deletion-held-callback.php', $databasePath, PATH_DATA, (string) $c['order']->getId()]);

    try {
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($c['connection'], $lock, 0))->toBeTrue();
        $process->start();
        expect($process->waitUntil(fn ($type, $output): bool => str_contains($output, 'ready')))->toBeTrue();
        // The callback has already loaded its Order entity, but must read the
        // hold afresh after the deleting worker releases the lifecycle lock.
        DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
        FOSSBilling\Core\Doctrine\NamedLock::release($c['connection'], $lock);
        $process->wait();
        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('"blocked":true');
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($c['connection'], $lock, 0))->toBeTrue();
    } finally {
        $process->stop();
        FOSSBilling\Core\Doctrine\NamedLock::release($c['connection'], $lock);
        $c['connection']->close();
        $filesystem->remove([$databasePath, $databasePath . '-wal', $databasePath . '-shm']);
    }
});

test('received gateway funds remain credit without settling or fulfilling a held invoice', function (bool $directItem): void {
    $c = deletionRecoveryContext();
    $order = failDeletionAtGateway($c);
    $client = new Box\Mod\Client\Entity\Client();
    $c['em']->persist($client);
    $c['em']->flush();
    $incoming = (new Box\Mod\Client\Entity\ClientBalance())->setClient($client)->setType('transaction')->setRelId('incoming-payment')->setAmount('10');
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setClientId($client->getId());
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $order->getId())->setTask(InvoiceItem::TASK_RENEW)->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(10)->setQuantity(1);
    foreach ([$incoming, $invoice, $item] as $entity) {
        $c['em']->persist($entity);
    }
    $c['em']->flush();
    if ($directItem) {
        $items = new Box\Mod\Invoice\ServiceInvoiceItem();
        $items->setDi($c['di']);
        expect(fn () => $items->markAsPaid($item))->toThrow(InformationException::class, 'awaiting recovery');
    } else {
        $billing = new Box\Mod\Invoice\Service();
        $billing->setDi($c['di']);
        $actuallyPaid = null;
        expect($billing->markAsPaid($invoice, true, true, actuallyPaid: $actuallyPaid))->toBeTrue()
            ->and($actuallyPaid)->toBeFalse();
    }
    expect($c['connection']->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(10)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance WHERE amount < 0'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_UNPAID)
        ->and($item->getCharged())->toBeFalse()
        ->and($c['backend']->calls)->toBe(1);
})->with([false, true]);

test('MySQL current reads detect deletion holds committed after a billing snapshot', function (): void {
    $params = [
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('DELETION_HOLD_MYSQL_PORT'),
        'user' => 'root', 'password' => getenv('DELETION_HOLD_MYSQL_PASSWORD'), 'dbname' => 'hold_regression',
    ];
    $deletion = DriverManager::getConnection($params);
    $billing = DriverManager::getConnection($params);

    try {
        $deletion->executeStatement('CREATE TABLE client_order (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
        $deletion->executeStatement('CREATE TABLE client_order_meta (id BIGINT AUTO_INCREMENT PRIMARY KEY, client_order_id BIGINT, name VARCHAR(255), value TEXT, INDEX (client_order_id)) ENGINE=InnoDB');
        $deletion->insert('client_order', ['id' => 1]);
        $billing->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $billing->beginTransaction();
        // Model the invoice-item lookup that starts a consistent snapshot.
        expect($billing->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
        DeletionRecovery::setState($deletion, 1, DeletionRecovery::STARTED);
        expect(DeletionRecovery::getState($billing, 1))->toBeNull();
        expect(fn () => DeletionRecovery::assertNotHeld($billing, 1, true))
            ->toThrow(InformationException::class, 'awaiting recovery');
    } finally {
        if ($billing->isTransactionActive()) {
            $billing->rollBack();
        }
        $billing->close();
        $deletion->executeStatement('DROP TABLE IF EXISTS client_order_meta');
        $deletion->executeStatement('DROP TABLE IF EXISTS client_order');
        $deletion->close();
    }
})->skip(fn (): bool => getenv('DELETION_HOLD_MYSQL_PORT') === false, 'Requires an isolated MySQL hold_regression database');

function deletionPaymentContext(?string $databasePath = null): array
{
    $c = deletionRecoveryContext($databasePath);
    $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
    $gateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('PayPalEmail')->setEnabled(true);
    $c['em']->persist($client);
    $c['em']->persist($gateway);
    $c['em']->flush();
    $c['order']->setClientId($client->getId())->setCurrency('USD');
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID)->setIssued(true)->setCurrency('USD')->setClientId($client->getId())->setGateway($gateway);
    $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $c['order']->getId())->setPrice(10)->setQuantity(1);
    $c['em']->persist($invoice);
    $c['em']->persist($item);
    $c['em']->flush();
    $billing = new Box\Mod\Invoice\Service();
    $billing->setDi($c['di']);
    $c['di']['mod_service'] = $c['di']->protect(fn (): object => $billing);

    return $c + compact('client', 'gateway', 'invoice', 'item', 'billing');
}

test('manual payment rejects deletion holds without changing gateway or recording money', function (string $gatewayName, bool $lateHold): void {
    $c = deletionPaymentContext();
    $manual = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway($gatewayName)->setEnabled(true);
    $c['em']->persist($manual);
    $c['em']->flush();
    if ($lateHold) {
        // Commit a hold after preflight but before the payment transaction,
        // modelling a deletion worker winning the race for the parent row.
        $em = new class($c['connection'], $c['config']) extends EntityManager {
            public int $orderId;

            public function wrapInTransaction(callable $func): mixed
            {
                DeletionRecovery::setState($this->getConnection(), $this->orderId, DeletionRecovery::STARTED);

                return parent::wrapInTransaction($func);
            }
        };
        $em->orderId = $c['order']->getId();
        $c['di']['em'] = $em;
        $c['invoice'] = $em->find(Invoice::class, $c['invoice']->getId());
    } else {
        DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    }
    $c['di']['event_dispatcher'] = Mockery::mock(Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
    $c['di']['event_dispatcher']->shouldNotReceive('dispatch');
    expect(fn () => $c['billing']->markAsPaidByAdmin($c['invoice'], ['gateway_id' => $manual->getId(), 'transactionId' => 'manual-held-payment', 'execute' => true]))
        ->toThrow(InformationException::class, 'awaiting recovery');
    expect($c['connection']->fetchOne('SELECT gateway_id FROM invoice'))->toBe($c['gateway']->getId())
        ->and($c['connection']->fetchOne('SELECT status FROM invoice'))->toBe(Invoice::STATUS_UNPAID)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM "transaction"'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
})->with(['Custom', 'PayPalEmail'])->with([false, true]);

test('verified PayPal renewal receipts remain credit during deletion recovery, including retries', function (string $txnType, bool $crash, string $gross): void {
    $c = deletionPaymentContext();
    $c['invoice']->setStatus(Invoice::STATUS_PAID);
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('held-subscription')->setRelType('invoice')->setRelId($c['invoice']->getId());
    $transaction = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing')->setTxnId('held-paypal-receipt')->setTxnStatus('Completed')->setType($txnType)->setAmount($gross)->setCurrency('USD');
    $c['em']->persist($subscription);
    $c['em']->persist($transaction);
    $c['em']->flush();
    DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    $payload = [
        'get' => ['invoice_id' => $c['invoice']->getId(), 'sig' => FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId())],
        'post' => ['txn_type' => $txnType, 'payment_status' => 'Completed', 'txn_id' => 'held-paypal-receipt', 'subscr_id' => 'held-subscription', 'mc_gross' => $gross, 'mc_currency' => 'USD'],
    ];
    $api = deletionPayPalApi($c);
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapter->setDi($c['di']);
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
        $api = deletionPayPalApi($c);
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
        ->and($c['backend']->calls)->toBe(0);
})->with(['subscr_payment', 'recurring_payment'])->with([false, true])->with(['10.00', '3.00']);

test('metadata aliases cannot alter or forge deletion checkpoints under database collation', function (bool $existingHold, string $alias): void {
    $c = deletionRecoveryContext();
    // NOCASE models a case-insensitive metadata column independently of the
    // connection default, as existing MySQL installs commonly have.
    $c['connection']->executeStatement('DROP TABLE client_order_meta');
    $c['connection']->executeStatement('CREATE TABLE client_order_meta (id INTEGER PRIMARY KEY AUTOINCREMENT, client_order_id BIGINT, name TEXT COLLATE NOCASE, value TEXT, created_at DATETIME, updated_at DATETIME)');
    if ($existingHold) {
        DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    }
    $c['service']->updateOrderMeta($c['order'], ['ordinary' => 'original']);
    expect(fn () => $c['service']->updateOrderMeta($c['order'], ['ordinary' => 'changed', $alias => DeletionRecovery::SERVICE_DELETED]))
        ->toThrow(InformationException::class, 'cannot be edited');
    expect(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBe($existingHold ? DeletionRecovery::STARTED : null)
        ->and($c['connection']->fetchOne("SELECT value FROM client_order_meta WHERE name = 'ordinary'"))->toBe('original')
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(1)
        ->and($c['backend']->calls)->toBe(0);
})->with([false, true])->with(['DELETION_STATE', 'Deletion_State']);

test('MySQL checkpoint aliases respect the metadata column collation and roll back the entire edit', function (bool $existingHold, string $alias): void {
    $connection = DriverManager::getConnection([
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('DELETION_HOLD_MYSQL_PORT'),
        'user' => 'root', 'password' => getenv('DELETION_HOLD_MYSQL_PASSWORD'), 'dbname' => 'hold_regression',
    ]);

    try {
        // Deliberately give the connection a different comparison from the
        // column: comparing two bound parameters would miss these aliases.
        $connection->executeStatement('SET NAMES utf8mb4 COLLATE utf8mb4_bin');
        $connection->executeStatement('CREATE TABLE client_order (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
        $connection->executeStatement('CREATE TABLE client_order_meta (id BIGINT AUTO_INCREMENT PRIMARY KEY, client_order_id BIGINT, name VARCHAR(255) COLLATE utf8mb4_unicode_ci, value TEXT, created_at DATETIME, updated_at DATETIME) ENGINE=InnoDB');
        $connection->insert('client_order', ['id' => 1]);
        if ($existingHold) {
            DeletionRecovery::setState($connection, 1, DeletionRecovery::STARTED);
        }
        $connection->insert('client_order_meta', ['client_order_id' => 1, 'name' => 'ordinary', 'value' => 'original']);
        $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('DeletionRecoveryTestProxies');
        $em = new EntityManager($connection, $config);
        $di = container();
        $di['em'] = $em;
        $service = new Service();
        $service->setDi($di);
        expect(fn () => $service->updateOrderMeta($em->getReference(Order::class, 1), ['ordinary' => 'changed', $alias => DeletionRecovery::SERVICE_DELETED]))
            ->toThrow(InformationException::class, 'cannot be edited');
        expect(DeletionRecovery::getState($connection, 1))->toBe($existingHold ? DeletionRecovery::STARTED : null)
            ->and($connection->fetchOne("SELECT value FROM client_order_meta WHERE name = 'ordinary'"))->toBe('original');
    } finally {
        $connection->executeStatement('DROP TABLE IF EXISTS client_order_meta');
        $connection->executeStatement('DROP TABLE IF EXISTS client_order');
        $connection->close();
    }
})->with([false, true])->with(['DELETION_STATE', 'deletion_state ', 'déletion_state'])
    ->skip(fn (): bool => getenv('DELETION_HOLD_MYSQL_PORT') === false, 'Requires an isolated MySQL hold_regression database');

test('deferred PayPal renewal credits reject invalid amounts and currencies before adding funds', function (string $amount, string $currency): void {
    $c = deletionPaymentContext();
    $c['invoice']->setStatus(Invoice::STATUS_PAID);
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('held-subscription')->setRelType('invoice')->setRelId($c['invoice']->getId());
    $transaction = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing')->setTxnId('invalid-held-receipt')->setTxnStatus('Completed')->setType('subscr_payment')->setAmount($amount)->setCurrency($currency);
    $c['em']->persist($subscription);
    $c['em']->persist($transaction);
    $c['em']->flush();
    DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    $payload = [
        'get' => ['invoice_id' => $c['invoice']->getId(), 'sig' => FOSSBilling\Core\Security\Credential::signCallbackParams($c['gateway']->getId(), $c['invoice']->getId())],
        'post' => ['txn_type' => 'subscr_payment', 'payment_status' => 'Completed', 'txn_id' => 'invalid-held-receipt', 'subscr_id' => 'held-subscription', 'mc_gross' => $amount, 'mc_currency' => $currency],
    ];
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapter->setDi($c['di']);
    $api = deletionPayPalApi($c);
    expect(fn () => $adapter->processTransaction($api, $transaction->getId(), $payload, $c['gateway']->getId()))->toThrow(Exception::class);
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0)
        ->and($c['connection']->fetchOne('SELECT status FROM "transaction"'))->toBe('processing');
})->with([
    'negative' => ['-1', 'USD'],
    'not numeric' => ['invalid', 'USD'],
    'nonfinite' => ['1e999', 'USD'],
    'wrong currency' => ['10', 'EUR'],
]);

function deferredPayPalReceiptContext(?string $databasePath = null, bool $earlierAttempt = false): array
{
    $c = deletionPaymentContext($databasePath);
    $c['invoice']->setStatus(Invoice::STATUS_PAID);
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setSid('held-subscription')->setRelType('invoice')->setRelId($c['invoice']->getId());
    $receipt = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing')->setTxnId('PAYPAL-DEFERRED')->setTxnStatus('Completed')->setType('subscr_payment')->setAmount('3')->setCurrency('USD');
    if ($earlierAttempt) {
        $uncredited = (clone $receipt)->setAmount(null)->setType(null)->setTxnStatus(null);
        $c['em']->persist($uncredited);
        $c['em']->flush();
    }
    $c['em']->persist($subscription);
    $c['em']->persist($receipt);
    $c['em']->flush();
    DeletionRecovery::setState($c['connection'], $c['order']->getId(), DeletionRecovery::STARTED);
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com']);
    $adapter->setDi($c['di']);
    $api = deletionPayPalApi($c);
    $payload = [
        'get' => ['invoice_id' => $c['invoice']->getId()],
        'post' => ['txn_type' => 'subscr_payment', 'payment_status' => 'Completed', 'txn_id' => 'PAYPAL-DEFERRED', 'subscr_id' => 'held-subscription', 'mc_gross' => '3', 'mc_currency' => 'USD'],
    ];

    return $c + compact('receipt', 'adapter', 'api', 'payload');
}

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

function deferredPayPalRefund(array $c, string $externalId, string $amount, string $currency = 'USD'): array
{
    $refund = (new Box\Mod\Invoice\Entity\Transaction())->setInvoice($c['invoice'])->setGateway($c['gateway'])->setStatus('processing');
    $c['em']->persist($refund);
    $c['em']->flush();
    $payload = $c['payload'];
    $payload['post'] = ['txn_type' => 'subscr_payment', 'payment_status' => 'Refunded', 'txn_id' => $externalId, 'parent_txn_id' => 'PAYPAL-DEFERRED', 'subscr_id' => 'held-subscription', 'mc_gross' => $amount, 'mc_currency' => $currency];

    return [$refund, $payload];
}

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
    $c['api'] = deletionPayPalApi($c);
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
