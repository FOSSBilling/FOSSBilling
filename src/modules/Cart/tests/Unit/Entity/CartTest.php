<?php

declare(strict_types=1);

use Box\Mod\Cart\Entity\Cart;
use Box\Mod\Cart\Entity\CartProduct;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

test('maps the existing cart tables without changing their columns', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $cart = $entityManager->getClassMetadata(Cart::class);
    $cartProduct = $entityManager->getClassMetadata(CartProduct::class);

    expect($cart->getTableName())->toBe('cart')
        ->and($cart->getColumnNames())->toBe(['id', 'session_id', 'currency_id', 'promo_id', 'created_at', 'updated_at'])
        ->and($cart->getFieldMapping('sessionId')['nullable'])->toBeTrue()
        ->and($cartProduct->getTableName())->toBe('cart_product')
        ->and($cartProduct->getColumnNames())->toBe(['id', 'cart_id', 'product_id', 'config'])
        ->and($cartProduct->getFieldMapping('config')['nullable'])->toBeTrue();
});

test('preserves explicitly supplied cart timestamps on persist', function (): void {
    $createdAt = new DateTime('2026-01-01 12:00:00');
    $updatedAt = new DateTime('2026-01-02 12:00:00');
    $cart = new Cart();
    $cart->setCreatedAt($createdAt);
    $cart->setUpdatedAt($updatedAt);

    $cart->onPrePersist();

    expect($cart->getCreatedAt())->toBe($createdAt)
        ->and($cart->getUpdatedAt())->toBe($updatedAt);
});
