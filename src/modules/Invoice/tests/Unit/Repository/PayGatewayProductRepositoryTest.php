<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\PayGatewayProduct;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @return EntityManager a fresh in-memory EntityManager with the schema for
 *                       just PayGateway/PayGatewayProduct created
 */
function payGatewayProductEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [PayGateway::class, PayGatewayProduct::class],
    );
    (new SchemaTool($entityManager))->createSchema($metadata);

    return $entityManager;
}

test('findOneByGatewayAndCacheKey finds the cached product/price for that gateway and key', function (): void {
    $em = payGatewayProductEntityManager();

    $gateway = new PayGateway();
    $gateway->setName('Stripe');
    $em->persist($gateway);

    $product = new PayGatewayProduct();
    $product->setPayGateway($gateway);
    $product->setCacheKey(hash('sha256', 'Basic Hosting|usd|999|month|1'));
    $product->setName('Basic Hosting');
    $product->setExternalProductId('prod_abc');
    $product->setExternalPriceId('price_abc');
    $em->persist($product);
    $em->flush();
    $em->clear();

    $found = $em->getRepository(PayGatewayProduct::class)
        ->findOneByGatewayAndCacheKey($gateway->getId(), hash('sha256', 'Basic Hosting|usd|999|month|1'));

    expect($found?->getExternalProductId())->toBe('prod_abc')
        ->and($found?->getExternalPriceId())->toBe('price_abc');
});

test('findOneByGatewayAndCacheKey returns null when no row matches', function (): void {
    $em = payGatewayProductEntityManager();

    $gateway = new PayGateway();
    $gateway->setName('Stripe');
    $em->persist($gateway);
    $em->flush();

    expect($em->getRepository(PayGatewayProduct::class)->findOneByGatewayAndCacheKey($gateway->getId(), str_repeat('0', 64)))->toBeNull();
});

test('a second product row for the same gateway and cache key violates the unique constraint', function (): void {
    // DB-level backstop Payment_Adapter_Stripe::cacheGatewayProduct() relies on, mirroring
    // PayGatewayCustomerRepositoryTest's equivalent case for the customer cache.
    $em = payGatewayProductEntityManager();

    $gateway = new PayGateway();
    $gateway->setName('Stripe');
    $em->persist($gateway);

    $cacheKey = hash('sha256', 'Basic Hosting|usd|999|month|1');

    $first = new PayGatewayProduct();
    $first->setPayGateway($gateway);
    $first->setCacheKey($cacheKey);
    $first->setName('Basic Hosting');
    $first->setExternalProductId('prod_first');
    $first->setExternalPriceId('price_first');
    $em->persist($first);
    $em->flush();

    $second = new PayGatewayProduct();
    $second->setPayGateway($gateway);
    $second->setCacheKey($cacheKey);
    $second->setName('Basic Hosting');
    $second->setExternalProductId('prod_second');
    $second->setExternalPriceId('price_second');
    $em->persist($second);

    expect(fn () => $em->flush())->toThrow(UniqueConstraintViolationException::class);
});
