<?php

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing order tables without changing their columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $order = $entityManager->getClassMetadata(Order::class);
    $meta = $entityManager->getClassMetadata(OrderMeta::class);
    $status = $entityManager->getClassMetadata(OrderStatus::class);

    expect($order->getTableName())->toBe('client_order')
        ->and($order->getColumnNames())->toBe([
            'id', 'client_id', 'product_id', 'form_id', 'promo_id', 'promo_recurring', 'promo_used',
            'group_id', 'group_master', 'invoice_option', 'title', 'currency', 'unpaid_invoice_id',
            'service_id', 'service_type', 'period', 'quantity', 'unit', 'price', 'discount', 'status',
            'reason', 'notes', 'config', 'referred_by', 'expires_at', 'activated_at', 'suspended_at',
            'unsuspended_at', 'canceled_at', 'created_at', 'updated_at',
        ])
        ->and($order->getFieldMapping('groupMaster')['nullable'])->toBeTrue()
        ->and($order->getFieldMapping('quantity')['nullable'])->toBeTrue()
        ->and($order->getFieldMapping('quantity')['options']['default'])->toBe(1)
        ->and($meta->getTableName())->toBe('client_order_meta')
        ->and($meta->getColumnNames())->toBe(['id', 'client_order_id', 'name', 'value', 'created_at', 'updated_at'])
        ->and($status->getTableName())->toBe('client_order_status')
        ->and($status->getColumnNames())->toBe(['id', 'client_order_id', 'status', 'notes', 'created_at', 'updated_at']);
});

test('preserves explicitly supplied order timestamps on persist', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $updatedAt = new DateTime('2026-01-02 12:00:00');
    $order = new Order();
    $order->setCreatedAt($createdAt);
    $order->setUpdatedAt($updatedAt);

    $order->onPrePersist();

    expect($order->getCreatedAt())->toBe($createdAt)
        ->and($order->getUpdatedAt())->toBe($updatedAt)
        ->and(Order::getValidStatuses())->toContain(Order::STATUS_PENDING_SETUP);
});
