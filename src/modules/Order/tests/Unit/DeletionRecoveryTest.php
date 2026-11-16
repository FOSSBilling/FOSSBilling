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
use Doctrine\DBAL\Exception\RetryableException;
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

test('MySQL and MariaDB snapshot conflicts roll back billing and preserve deletion holds on transaction retry', function (): void {
    $params = [
        'driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) getenv('DELETION_HOLD_MYSQL_PORT'),
        'user' => 'root', 'password' => getenv('DELETION_HOLD_MYSQL_PASSWORD'), 'dbname' => 'hold_regression',
    ];
    $deletion = DriverManager::getConnection($params);
    $billing = DriverManager::getConnection($params);

    try {
        $deletion->executeStatement('CREATE TABLE client_order (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
        $deletion->executeStatement('CREATE TABLE client_order_meta (id BIGINT AUTO_INCREMENT PRIMARY KEY, client_order_id BIGINT, name VARCHAR(255), value TEXT, INDEX (client_order_id)) ENGINE=InnoDB');
        $deletion->executeStatement('CREATE TABLE billing_snapshot_probe (id BIGINT PRIMARY KEY) ENGINE=InnoDB');
        $deletion->insert('client_order', ['id' => 1]);
        $billing->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $strictSnapshot = str_contains((string) $billing->fetchOne('SELECT VERSION()'), 'MariaDB')
            && (bool) $billing->fetchOne('SELECT @@innodb_snapshot_isolation'); // cspell:ignore innodb
        $failure = null;

        try {
            $billing->transactional(function () use ($billing, $deletion): void {
                // Start a snapshot before another worker commits the hold.
                expect($billing->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
                DeletionRecovery::setState($deletion, 1, DeletionRecovery::STARTED);
                expect(DeletionRecovery::getState($billing, 1))->toBeNull();
                $billing->insert('billing_snapshot_probe', ['id' => 1]);
                DeletionRecovery::assertNotHeld($billing, 1, true);
            });
        } catch (InformationException|RetryableException $e) {
            $failure = $e;
        }

        // Strict snapshot isolation rejects the locking read before the
        // application can inspect the hold. Both outcomes must abort all writes.
        expect($failure)->toBeInstanceOf($strictSnapshot ? RetryableException::class : InformationException::class);
        if ($failure instanceof InformationException) {
            expect($failure->getMessage())->toContain('awaiting recovery');
        }
        expect($billing->isTransactionActive())->toBeFalse()
            ->and($deletion->fetchOne('SELECT COUNT(*) FROM billing_snapshot_probe'))->toBe(0)
            ->and(DeletionRecovery::getState($deletion, 1))->toBe(DeletionRecovery::STARTED);

        // Retry the entire transaction with a new snapshot, not just the read
        // that failed. The committed hold must still prevent billing.
        expect(fn () => $billing->transactional(function () use ($billing): void {
            expect(DeletionRecovery::getState($billing, 1))->toBe(DeletionRecovery::STARTED);
            $billing->insert('billing_snapshot_probe', ['id' => 2]);
            DeletionRecovery::assertNotHeld($billing, 1, true);
        }))->toThrow(InformationException::class, 'awaiting recovery');
        expect($billing->isTransactionActive())->toBeFalse()
            ->and($deletion->fetchOne('SELECT COUNT(*) FROM billing_snapshot_probe'))->toBe(0)
            ->and(DeletionRecovery::getState($deletion, 1))->toBe(DeletionRecovery::STARTED);
    } finally {
        if ($billing->isTransactionActive()) {
            $billing->rollBack();
        }
        $billing->close();
        $deletion->executeStatement('DROP TABLE IF EXISTS billing_snapshot_probe');
        $deletion->executeStatement('DROP TABLE IF EXISTS client_order_meta');
        $deletion->executeStatement('DROP TABLE IF EXISTS client_order');
        $deletion->close();
    }
})->skip(fn (): bool => getenv('DELETION_HOLD_MYSQL_PORT') === false, 'Requires an isolated MySQL hold_regression database');

test('manual payment rejects deletion holds without changing gateway or recording money', function (string $gatewayName, bool $lateHold): void {
    $c = heldOrderPaymentContext();
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
})->with([false, true])->with(['DELETION_STATE', 'deletion_state ', 'déletion_state' /* cspell:ignore déletion */])
    ->skip(fn (): bool => getenv('DELETION_HOLD_MYSQL_PORT') === false, 'Requires an isolated MySQL hold_regression database');

function creationRecoveryBackend(array $c): object
{
    $backend = new class extends Box\Mod\Servicelicense\Service {
        public int $created = 0;

        public function action_create(Order $order): ServiceLicense
        {
            ++$this->created;
            $license = (new ServiceLicense())->setClientId($order->getClientId());
            $this->di['em']->persist($license);
            $this->di['em']->flush();

            return $license;
        }

        public function action_activate(Order $order): bool
        {
            return true;
        }
    };
    $backend->setDi($c['di']);
    $module = Mockery::mock();
    $module->shouldReceive('getService')->andReturn($backend);
    $c['di']['mod'] = $c['di']->protect(fn (): object => $module);

    return $backend;
}

test('creation keeps deletion and client removal locked through service linkage and activation', function (string $mode, bool $batch): void {
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $path = $filesystem->tempnam(sys_get_temp_dir(), 'creation-lock-');
    $c = deletionRecoveryContext($path);
    $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
    $c['em']->persist($client);
    $c['em']->remove($c['em']->find(ServiceLicense::class, $c['order']->getServiceId()));
    $c['order']->setServiceId(null)->setPeriod(null)->setClientId($client->getId());
    $c['em']->flush();
    // The client id is assigned by flush, after the order was first persisted.
    $c['order']->setClientId($client->getId());
    $c['em']->flush();
    $backend = creationRecoveryBackend($c);
    $worker = new Symfony\Component\Process\Process([PHP_BINARY, PATH_TESTS . '/Fixtures/order-create-delete-worker.php', $path, PATH_DATA, (string) $c['order']->getId(), $mode]);
    $service = new class extends Service {
        public ?Closure $afterCreate = null;

        protected function _callOnService(Order $order, $action, mixed ...$arguments)
        {
            $result = parent::_callOnService($order, $action, ...$arguments);
            if ($action === Order::ACTION_CREATE) {
                ($this->afterCreate)();
            }

            return $result;
        }
    };
    $service->setDi($c['di']);
    $startWorker = function () use ($worker): void {
        $worker->start();
        expect($worker->waitUntil(fn ($type, $output): bool => str_contains($output, 'locked')))->toBeTrue();
        expect($worker->getOutput())->toStartWith("locked\n");
    };
    $service->afterCreate = $batch ? static function (): void {} : $startWorker;
    $items = Mockery::mock(Box\Mod\Invoice\ServiceInvoiceItem::class);
    $c['di']['mod_service'] = $c['di']->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $service, 'servicelicense' => $backend, 'invoice' => $items,
    });

    try {
        if ($batch) {
            $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID);
            $item = (new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_ORDER)->setRelId((string) $c['order']->getId())->setTask(InvoiceItem::TASK_ACTIVATE)->setStatus(InvoiceItem::STATUS_PENDING_SETUP);
            $c['em']->persist($invoice);
            $c['em']->persist($item);
            $c['em']->flush();
            $items->shouldReceive('getAllNotExecutePaidItems')->once()->andReturn([['id' => $item->getId()]]);
            $items->shouldReceive('executeTask')->once()->andReturnUsing(function () use ($c, $startWorker, $item): bool {
                // Production resolves a fresh service inside the batch transaction.
                $inner = new Service();
                $inner->setDi($c['di']);
                $inner->createFromOrder($c['order']);
                $startWorker();
                $item->setStatus(InvoiceItem::STATUS_EXECUTED);
                $c['em']->flush();

                return true;
            });
            $billing = new Box\Mod\Invoice\Service();
            $billing->setDi($c['di']);
            $billing->doBatchPaidInvoiceActivation();
        } else {
            $service->createFromOrder($c['order']);
        }
        $worker->wait();
        $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
        expect($backend->created)->toBe(1);
        if ($mode === 'client') {
            expect($worker->getOutput())->toContain('Client cannot be deleted while they have service records')
                ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(1)
                ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client'))->toBe(1);
        } else {
            expect($worker->getOutput())->toContain('Gateway unavailable')
                ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(0)
                ->and(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBe(DeletionRecovery::SERVICE_DELETED);
        }
    } finally {
        $worker->stop();
        $c['connection']->close();
        $filesystem->remove([$path, $path . '-wal', $path . '-shm']);
    }
})->with(['order', 'client'])->with([false, true]);

test('creation refreshes existing service links and refuses an order removed before it acquires the lock', function (bool $deleted): void {
    $c = deletionRecoveryContext();
    $backend = creationRecoveryBackend($c);
    $c['order']->setPeriod(null);
    $c['em']->flush();
    $c['order']->setServiceId(null);
    $c['di']['mod_service'] = $c['di']->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $c['service'], 'servicelicense' => $backend,
    });
    if ($deleted) {
        $c['connection']->delete('client_order', ['id' => $c['order']->getId()]);
        expect(fn () => $c['service']->createFromOrder($c['order']))->toThrow(InformationException::class, 'not found');
    } else {
        $c['service']->createFromOrder($c['order']);
        expect($c['order']->getServiceId())->not->toBeNull();
    }
    expect($backend->created)->toBe(0)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(1);
})->with([false, true]);

test('client removal preserves deletion holds after backend service rows are gone', function (string $state): void {
    $c = deletionRecoveryContext();
    $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
    $c['em']->persist($client);
    $c['em']->flush();
    $c['order']->setClientId($client->getId());
    $c['em']->flush();
    failDeletionAtGateway($c);
    DeletionRecovery::setState($c['connection'], $c['order']->getId(), $state);
    $service = new Box\Mod\Client\Service();
    $service->setDi($c['di']);
    expect(fn () => $service->remove($client))->toThrow(InformationException::class, 'awaiting recovery');
    expect($c['connection']->isTransactionActive())->toBeFalse()
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order'))->toBe(1)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM service_license'))->toBe(0)
        ->and(DeletionRecovery::getState($c['connection'], $c['order']->getId()))->toBe($state);
})->with([DeletionRecovery::STARTED, DeletionRecovery::SERVICE_DELETED]);

test('client removal refuses orders discovered after its lifecycle locks were collected', function (): void {
    $c = deletionRecoveryContext();
    $client = (new Box\Mod\Client\Entity\Client())->setCurrency('USD');
    $c['em']->persist($client);
    $c['em']->flush();
    $c['order']->setClientId($client->getId());
    $c['em']->flush();
    expect(fn () => $c['service']->rmByClient($client, []))->toThrow(InformationException::class, 'orders changed');
    expect($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order'))->toBe(1);
});

test('activation rechecks current status under the lifecycle lock and still supports explicit force', function (): void {
    $c = deletionRecoveryContext();
    $backend = creationRecoveryBackend($c);
    $c['order']->setPeriod(null)->setStatus(Order::STATUS_PENDING_SETUP);
    $c['em']->flush();
    // Another executor completed after activateOrder's caller loaded this entity.
    $c['connection']->update('client_order', ['status' => Order::STATUS_ACTIVE], ['id' => $c['order']->getId()]);
    $c['di']['mod_service'] = $c['di']->protect(fn (string $name): object => match (strtolower($name)) {
        'order' => $c['service'], 'servicelicense' => $backend,
    });
    expect($c['service']->activateOrder($c['order']))->toBeTrue()
        ->and($c['order']->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order_status'))->toBe(0);
    expect($c['service']->activateOrder($c['order'], ['force' => true]))->toBeTrue()
        ->and($c['connection']->fetchOne('SELECT COUNT(*) FROM client_order_status'))->toBe(1);
});
