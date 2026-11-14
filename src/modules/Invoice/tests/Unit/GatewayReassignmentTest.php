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
