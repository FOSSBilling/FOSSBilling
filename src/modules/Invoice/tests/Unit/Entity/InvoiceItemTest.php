<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

use function Tests\Helpers\setEntityId;

test('maps invoice_item table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(InvoiceItem::class);

    expect($meta->getTableName())->toBe('invoice_item')
        ->and($meta->getColumnNames())->toBe([
            'id', 'type', 'rel_id', 'task', 'status', 'title',
            'period', 'quantity', 'unit', 'price', 'charged', 'taxed', 'attempts',
            'created_at', 'updated_at',
        ])
        ->and($meta->getFieldMapping('relId')->type)->toBe('text')
        ->and($meta->getFieldMapping('price')->type)->toBe('decimal')
        ->and($meta->getFieldMapping('price')->precision)->toBe(18)
        ->and($meta->getFieldMapping('price')->scale)->toBe(2)
        ->and($meta->getFieldMapping('charged')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('taxed')->nullable)->toBeTrue();
});

test('invoice item exposes the legacy type, task and status constants', function (): void {
    expect(InvoiceItem::TYPE_DEPOSIT)->toBe('deposit')
        ->and(InvoiceItem::TYPE_CUSTOM)->toBe('custom')
        ->and(InvoiceItem::TYPE_ORDER)->toBe('order')
        ->and(InvoiceItem::TASK_VOID)->toBe('void')
        ->and(InvoiceItem::TASK_ACTIVATE)->toBe('activate')
        ->and(InvoiceItem::TASK_RENEW)->toBe('renew')
        ->and(InvoiceItem::STATUS_PENDING_PAYMENT)->toBe('pending_payment')
        ->and(InvoiceItem::STATUS_PENDING_SETUP)->toBe('pending_setup')
        ->and(InvoiceItem::STATUS_EXECUTED)->toBe('executed')
        ->and(InvoiceItem::STATUS_FAILED)->toBe('failed');
});

test('invoice item getters and setters round-trip values', function (): void {
    $entity = new InvoiceItem();
    $invoice = new Invoice();
    setEntityId($invoice, 42);

    $entity->setInvoice($invoice);
    $entity->setType(InvoiceItem::TYPE_ORDER);
    $entity->setRelId('77');
    $entity->setTask(InvoiceItem::TASK_RENEW);
    $entity->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT);
    $entity->setTitle('Hosting');
    $entity->setPeriod('1M');
    $entity->setQuantity(3);
    $entity->setUnit('month');
    $entity->setPrice(12.5);
    $entity->setCharged(true);
    $entity->setTaxed(false);
    $entity->setAttempts(3);

    expect($entity->getInvoice())->toBe($invoice)
        ->and($entity->getType())->toBe(InvoiceItem::TYPE_ORDER)
        ->and($entity->getRelId())->toBe('77')
        ->and($entity->getTask())->toBe(InvoiceItem::TASK_RENEW)
        ->and($entity->getStatus())->toBe(InvoiceItem::STATUS_PENDING_PAYMENT)
        ->and($entity->getTitle())->toBe('Hosting')
        ->and($entity->getPeriod())->toBe('1M')
        ->and($entity->getQuantity())->toBe(3)
        ->and($entity->getUnit())->toBe('month')
        ->and($entity->getPrice())->toBe('12.5')
        ->and($entity->getCharged())->toBeTrue()
        ->and($entity->getTaxed())->toBeFalse()
        ->and($entity->getAttempts())->toBe(3)
        ->and($entity->getId())->toBeNull();
});

test('invoice item toApiArray matches the legacy toArray keys', function (): void {
    $entity = new InvoiceItem();
    setEntityId($entity, 5);
    $invoice = new Invoice();
    setEntityId($invoice, 9);
    $entity->setInvoice($invoice);
    $entity->setType(InvoiceItem::TYPE_CUSTOM);
    $entity->setRelId('1');
    $entity->setTask(InvoiceItem::TASK_VOID);
    $entity->setStatus(InvoiceItem::STATUS_EXECUTED);
    $entity->setTitle('Setup');
    $entity->setPeriod(null);
    $entity->setQuantity(1);
    $entity->setUnit(null);
    $entity->setPrice(2.0);
    $entity->setCharged(true);
    $entity->setTaxed(true);
    $entity->setAttempts(2);
    $entity->setCreatedAt(new DateTime('2026-01-02 03:04:05'));
    $entity->setUpdatedAt(new DateTime('2026-01-02 03:04:05'));

    expect($entity->toApiArray())->toBe([
        'id' => 5,
        'invoice_id' => 9,
        'type' => InvoiceItem::TYPE_CUSTOM,
        'rel_id' => '1',
        'task' => InvoiceItem::TASK_VOID,
        'status' => InvoiceItem::STATUS_EXECUTED,
        'title' => 'Setup',
        'period' => null,
        'quantity' => 1,
        'unit' => null,
        'price' => 2.0,
        'charged' => 1,
        'taxed' => 1,
        'attempts' => 2,
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-01-02 03:04:05',
    ]);
});
