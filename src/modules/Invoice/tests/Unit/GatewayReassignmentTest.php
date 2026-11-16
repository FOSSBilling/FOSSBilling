<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Service;
use Box\Mod\Invoice\ServiceGatewayFees;
use Box\Mod\Invoice\ServiceInvoiceItem;
use Box\Mod\Invoice\ServicePayGateway;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

use function Tests\Helpers\container;

function gatewayReassignmentContext(?string $concurrentChange = null, ?array $databaseParams = null): array
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 4)], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection($databaseParams ?? ['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Invoice::class, InvoiceItem::class, PayGateway::class]));
    $source = (new PayGateway())->setGateway('Stripe')->setEnabled(true);
    $replacement = (new PayGateway())->setGateway('PayPalEmail')->setEnabled(true);
    $other = (new PayGateway())->setGateway('Custom')->setEnabled(true);
    foreach ([$source, $replacement, $other] as $gateway) {
        $em->persist($gateway);
    }
    $em->flush();
    $invoice = (new Invoice())->setStatus(Invoice::STATUS_UNPAID)->setGateway($source);
    $em->persist($invoice);
    $em->flush();
    $ids = [$source->getId(), $replacement->getId(), $other->getId(), $invoice->getId()];
    $em->clear();
    $writer = $databaseParams === null ? null : DriverManager::getConnection($databaseParams);
    if ($databaseParams !== null) {
        $em->getConnection()->setTransactionIsolation(Doctrine\DBAL\TransactionIsolationLevel::REPEATABLE_READ);
    }
    if ($concurrentChange !== null) {
        // The candidate was selected as unpaid, but another worker commits
        // before migration acquires its invoice lock.
        $listener = new class($concurrentChange, $ids[2], $writer) {
            private bool $changed = false;

            public function __construct(private readonly string $change, private readonly int $otherGateway, private readonly ?Doctrine\DBAL\Connection $writer)
            {
            }

            public function postLoad(Doctrine\ORM\Event\PostLoadEventArgs $event): void
            {
                $invoice = $event->getObject();
                if (!$invoice instanceof Invoice || $this->changed) {
                    return;
                }
                $this->changed = true;
                $connection = $this->writer ?? $event->getObjectManager()->getConnection();
                if ($this->change === 'deleted') {
                    $connection->delete('invoice', ['id' => $invoice->getId()]);
                } else {
                    $connection->update('invoice', $this->change === 'gateway' ? ['gateway_id' => $this->otherGateway] : ['status' => $this->change], ['id' => $invoice->getId()]);
                }
            }
        };
        $em->getEventManager()->addEventListener(['postLoad'], $listener);
    }

    return [gatewayReassignmentService($em), $em, $ids];
}

function gatewayReassignmentService(EntityManager $em): ServicePayGateway
{
    $invoices = new Service();
    $items = new ServiceInvoiceItem();
    $fees = new ServiceGatewayFees();
    $gateways = new ServicePayGateway();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($sub) {
        'GatewayFees' => $fees,
        'InvoiceItem' => $items,
        default => $invoices,
    });
    foreach ([$invoices, $items, $fees, $gateways] as $service) {
        $service->setDi($di);
    }

    return $gateways;
}

test('gateway migration preserves concurrently settled or reassigned invoices', function (string $change): void {
    [$gateways, $em, [$source, $replacement, $other, $id]] = gatewayReassignmentContext($change);
    $gateways->moveUnpaidInvoices($source, $replacement);
    $row = $em->getConnection()->fetchAssociative('SELECT status, gateway_id FROM invoice WHERE id = ?', [$id]);
    if ($change === 'deleted') {
        expect($row)->toBeFalse();
    } else {
        expect($row['status'])->toBe($change === 'gateway' ? 'unpaid' : $change)
            ->and((int) $row['gateway_id'])->toBe($change === 'gateway' ? $other : $source);
    }
})->with(['paid', 'canceled', 'refunded', 'gateway', 'deleted']);

test('gateway migration still moves an unchanged unpaid invoice', function (): void {
    [$gateways, $em, [$source, $replacement, , $id]] = gatewayReassignmentContext();
    $gateways->moveUnpaidInvoices($source, $replacement);
    expect((int) $em->getConnection()->fetchOne('SELECT gateway_id FROM invoice WHERE id = ?', [$id]))->toBe($replacement)
        ->and($em->getConnection()->fetchOne('SELECT status FROM invoice WHERE id = ?', [$id]))->toBe('unpaid');
});

test('gateway reassignment rolls back if fee repricing fails', function (): void {
    [$gateways, $em, [$source, $replacement, , $id]] = gatewayReassignmentContext();
    $fees = Mockery::mock(ServiceGatewayFees::class);
    $fees->shouldReceive('apply')->once()->andThrow(new RuntimeException('repricing interrupted'));
    $di = $gateways->getDi();
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (): object => $fees);
    expect(fn () => $gateways->moveUnpaidInvoices($source, $replacement))->toThrow(RuntimeException::class, 'repricing interrupted');
    expect((int) $em->getConnection()->fetchOne('SELECT gateway_id FROM invoice WHERE id = ?', [$id]))->toBe($source);
});

test('gateway disabling respects concurrent commits under MySQL repeatable read', function (string $change, bool $hasFee): void {
    $port = getenv('PAYMENT_TEST_MYSQL_PORT');
    if ($port === false || $port === '') {
        $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }
    $params = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'user' => 'root', 'password' => getenv('PAYMENT_TEST_MYSQL_PASSWORD') ?: ''];
    $admin = DriverManager::getConnection($params);
    $database = 'phase_a_gateway_test_' . bin2hex(random_bytes(6));
    $admin->executeStatement('CREATE DATABASE ' . $database);

    try {
        [$gateways, $em, [$source, $replacement, $other, $id]] = gatewayReassignmentContext($change, $params + ['dbname' => $database]);
        // Fee history must also remain intact if payment wins the race.
        if ($hasFee) {
            $em->getConnection()->insert('invoice_item', ['invoice_id' => $id, 'type' => 'custom', 'task' => 'gateway_fee', 'status' => 'pending_payment', 'price' => '3.25', 'quantity' => 1]);
        }
        $gateway = $em->find(PayGateway::class, $source);

        try {
            $gateways->update($gateway, ['enabled' => false, 'replacement_id' => $replacement]);
        } catch (Doctrine\DBAL\Exception\DriverException $e) {
            // Strict MariaDB snapshots reject the changed row: retry the whole
            // operation only after rollback, with a fresh entity manager.
            expect($em->getConnection()->getDatabasePlatform())->toBeInstanceOf(Doctrine\DBAL\Platforms\MariaDBPlatform::class);
            expect($e->getMessage())->toContain('1020')
                ->and($em->getConnection()->isTransactionActive())->toBeFalse()
                ->and((int) $admin->fetchOne('SELECT enabled FROM ' . $database . '.pay_gateway WHERE id = ?', [$source]))->toBe(1);
            $em = new EntityManager($em->getConnection(), $em->getConfiguration());
            $gateways = gatewayReassignmentService($em);
            $gateways->update($em->find(PayGateway::class, $source), ['enabled' => false, 'replacement_id' => $replacement]);
        }
        // Inspect through an independent current read, not A's old snapshot.
        $row = $admin->fetchAssociative('SELECT status, gateway_id FROM ' . $database . '.invoice WHERE id = ?', [$id]);
        expect($row['status'])->toBe($change === 'gateway' ? 'unpaid' : 'paid')
            ->and((int) $row['gateway_id'])->toBe($change === 'gateway' ? $other : $source)
            ->and((float) $admin->fetchOne('SELECT price FROM ' . $database . '.invoice_item WHERE invoice_id = ?', [$id]))->toBe($hasFee ? 3.25 : 0.0);
        $em->close();
        $em->getConnection()->close();
    } finally {
        $admin->executeStatement('DROP DATABASE ' . $database);
        $admin->close();
    }
})->with(['payment without fee' => ['paid', false], 'payment with fee' => ['paid', true], 'gateway reassignment' => ['gateway', true]]);

test('replacement gateway stays locked until reassignment commits', function (): void {
    $port = getenv('PAYMENT_TEST_MYSQL_PORT');
    if ($port === false || $port === '') {
        $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }
    $params = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'user' => 'root', 'password' => getenv('PAYMENT_TEST_MYSQL_PASSWORD') ?: ''];
    $admin = DriverManager::getConnection($params);
    $database = 'gateway_lock_test_' . bin2hex(random_bytes(6));
    $admin->executeStatement('CREATE DATABASE ' . $database);

    try {
        [$gateways, $em, [$source, $replacement, $other, $id]] = gatewayReassignmentContext(null, $params + ['dbname' => $database]);
        $writer = DriverManager::getConnection($params + ['dbname' => $database]);
        $probe = new class($writer, $replacement) {
            public bool $blocked = false;

            public function __construct(private readonly Doctrine\DBAL\Connection $writer, private readonly int $replacement)
            {
            }

            public function postLoad(Doctrine\ORM\Event\PostLoadEventArgs $event): void
            {
                if (!$event->getObject() instanceof Invoice || $this->blocked) {
                    return;
                }
                $this->writer->beginTransaction();

                try {
                    $this->writer->fetchOne('SELECT id FROM pay_gateway WHERE id = ? FOR UPDATE NOWAIT', [$this->replacement]);
                } catch (Doctrine\DBAL\Exception\DriverException $e) {
                    if (!in_array((int) $e->getPrevious()?->getCode(), [1205, 3572], true)) {
                        throw $e;
                    }
                    $this->blocked = true;
                } finally {
                    $this->writer->rollBack();
                }
            }
        };
        $em->getEventManager()->addEventListener(['postLoad'], $probe);
        $gateways->update($em->find(PayGateway::class, $source), ['enabled' => false, 'replacement_id' => $replacement]);
        expect($probe->blocked)->toBeTrue();
        // Once the first updater commits, the second sees those invoices and
        // moves them again rather than leaving them on its disabled gateway.
        $gateways->update($em->find(PayGateway::class, $replacement), ['enabled' => false, 'replacement_id' => $other]);
        expect((int) $em->getConnection()->fetchOne('SELECT gateway_id FROM invoice WHERE id = ?', [$id]))->toBe($other);
        $writer->close();
        $em->getConnection()->close();
    } finally {
        $admin->executeStatement('DROP DATABASE ' . $database);
        $admin->close();
    }
});

test('refreshing a gateway for its lock preserves concurrently changed optional fields', function (): void {
    [$gateways, $em, [$source]] = gatewayReassignmentContext();
    $model = $em->find(PayGateway::class, $source);
    // Simulate a second updater committing after this entity was loaded.
    $em->getConnection()->update('pay_gateway', ['custom_logo' => 'new-logo.svg', 'enabled' => 0], ['id' => $source]);
    expect($gateways->update($model, ['title' => 'Updated title']))->toBeTrue()
        ->and($model->getCustomLogo())->toBe('new-logo.svg')
        ->and($model->isEnabled())->toBeFalse();
});

test('gateway migration reprices current fee items and partial payments after candidate selection', function (): void {
    $port = getenv('PAYMENT_TEST_MYSQL_PORT');
    if (!$port) {
        $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }
    $params = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'user' => 'root', 'password' => getenv('PAYMENT_TEST_MYSQL_PASSWORD') ?: ''];
    $admin = DriverManager::getConnection($params);
    $database = 'gateway_fee_test_' . bin2hex(random_bytes(6));
    $admin->executeStatement('CREATE DATABASE ' . $database);

    try {
        [$gateways, $em, [$source, $replacement, , $id]] = gatewayReassignmentContext(null, $params + ['dbname' => $database]);
        (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Box\Mod\Client\Entity\Client::class, Box\Mod\Invoice\Entity\Transaction::class, Box\Mod\Invoice\Entity\InvoiceCredit::class]));
        $connection = $em->getConnection();
        $connection->update('invoice', ['currency' => 'USD'], ['id' => $id]);
        $connection->update('pay_gateway', ['config' => json_encode(['fee' => ['percent' => 10]])], ['id' => $replacement]);
        $connection->insert('invoice_item', ['invoice_id' => $id, 'type' => 'custom', 'task' => 'void', 'price' => 100, 'quantity' => 1]);
        $connection->insert('invoice_item', ['invoice_id' => $id, 'type' => 'custom', 'task' => 'gateway_fee', 'price' => 3, 'quantity' => 1]);
        $writer = DriverManager::getConnection($params + ['dbname' => $database]);
        $listener = new class($writer) {
            private bool $changed = false;

            public function __construct(private readonly Doctrine\DBAL\Connection $writer)
            {
            }

            public function postLoad(Doctrine\ORM\Event\PostLoadEventArgs $event): void
            {
                $invoice = $event->getObject();
                if (!$invoice instanceof Invoice || $this->changed) {
                    return;
                }
                $this->changed = true;
                $this->writer->transactional(function () use ($invoice): void {
                    $this->writer->fetchOne('SELECT id FROM invoice WHERE id = ? FOR UPDATE', [$invoice->getId()]);
                    $this->writer->executeStatement("DELETE FROM invoice_item WHERE invoice_id = ? AND task = 'gateway_fee'", [$invoice->getId()]);
                    $this->writer->insert('invoice_item', ['invoice_id' => $invoice->getId(), 'type' => 'custom', 'task' => 'gateway_fee', 'price' => 10, 'quantity' => 1]);
                    $this->writer->insert($this->writer->quoteSingleIdentifier('transaction'), ['invoice_id' => $invoice->getId(), 'status' => 'processed', 'amount' => 50, 'currency' => 'USD']);
                });
            }
        };
        $em->getEventManager()->addEventListener(['postLoad'], $listener);
        $gateways->update($em->find(PayGateway::class, $source), ['enabled' => false, 'replacement_id' => $replacement]);
        $fees = $writer->fetchFirstColumn("SELECT price FROM invoice_item WHERE invoice_id = ? AND task = 'gateway_fee'", [$id]);
        expect(array_map(floatval(...), $fees))->toBe([5.0])
            ->and($connection->getTransactionIsolation())->toBe(Doctrine\DBAL\TransactionIsolationLevel::REPEATABLE_READ);
        $writer->close();
        // An existing snapshot cannot be made safe by nesting a transaction.
        $connection->beginTransaction();
        expect(fn () => $gateways->moveUnpaidInvoices($source, $replacement))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'fresh READ COMMITTED');
        $connection->rollBack();
        $connection->close();
    } finally {
        $admin->executeStatement('DROP DATABASE ' . $database);
        $admin->close();
    }
});
