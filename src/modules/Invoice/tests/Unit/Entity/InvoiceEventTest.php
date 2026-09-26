<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\InvoiceEvent;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps invoice_event table without changing columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $meta = $entityManager->getClassMetadata(InvoiceEvent::class);

    expect($meta->getTableName())->toBe('invoice_event')
        ->and($meta->getColumnNames())->toBe([
            'id', 'invoice_id', 'type', 'admin_id', 'client_id', 'snapshot', 'created_at',
        ])
        ->and($meta->getFieldMapping('type')->type)->toBe('string')
        ->and($meta->getFieldMapping('invoiceId')->nullable)->toBeTrue()
        ->and($meta->getFieldMapping('snapshot')->nullable)->toBeTrue();
});

test('invoice event exposes the journal type constants', function (): void {
    expect(InvoiceEvent::TYPE_CREATED)->toBe('created')
        ->and(InvoiceEvent::TYPE_ISSUED)->toBe('issued')
        ->and(InvoiceEvent::TYPE_UPDATED)->toBe('updated')
        ->and(InvoiceEvent::TYPE_PAID)->toBe('paid')
        ->and(InvoiceEvent::TYPE_REFUNDED)->toBe('refunded')
        ->and(InvoiceEvent::TYPE_DEBITED)->toBe('debited')
        ->and(InvoiceEvent::TYPE_CANCELED)->toBe('canceled')
        ->and(InvoiceEvent::TYPE_REISSUED)->toBe('reissued')
        ->and(InvoiceEvent::TYPE_ORDER_ATTACHED)->toBe('order_attached')
        ->and(InvoiceEvent::TYPE_REMINDER)->toBe('reminder');
});

test('invoice event getters and setters round-trip values', function (): void {
    $entity = new InvoiceEvent();

    expect($entity->getType())->toBe(InvoiceEvent::TYPE_UPDATED)
        ->and($entity->getInvoiceId())->toBeNull()
        ->and($entity->getSnapshot())->toBeNull();

    $entity->setInvoiceId(7)
        ->setType(InvoiceEvent::TYPE_PAID)
        ->setAdminId(3)
        ->setClientId(5)
        ->setSnapshot(['total' => '10.00']);

    expect($entity->getInvoiceId())->toBe(7)
        ->and($entity->getType())->toBe(InvoiceEvent::TYPE_PAID)
        ->and($entity->getAdminId())->toBe(3)
        ->and($entity->getClientId())->toBe(5)
        ->and($entity->getSnapshot())->toBe(['total' => '10.00']);
});
