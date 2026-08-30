<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @return EntityManager a fresh in-memory EntityManager with the schema for
 *                       just PayGatewayCustomer created
 */
function payGatewayCustomerEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = [$entityManager->getClassMetadata(PayGatewayCustomer::class)];
    (new SchemaTool($entityManager))->createSchema($metadata);

    return $entityManager;
}

test('findOneByGatewayAndClient finds the cached customer for that gateway and client', function (): void {
    $em = payGatewayCustomerEntityManager();

    $customer = new PayGatewayCustomer();
    $customer->setPayGatewayId(1);
    $customer->setClientId(7);
    $customer->setExternalCustomerId('cus_abc123');
    $em->persist($customer);
    $em->flush();
    $em->clear();

    $found = $em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7);

    expect($found?->getExternalCustomerId())->toBe('cus_abc123');
});

test('findOneByGatewayAndClient returns null when no row matches', function (): void {
    $em = payGatewayCustomerEntityManager();

    expect($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 999))->toBeNull();
});

test('findOneByGatewayAndClient does not cross gateways for the same client', function (): void {
    $em = payGatewayCustomerEntityManager();

    $customer = new PayGatewayCustomer();
    $customer->setPayGatewayId(1);
    $customer->setClientId(7);
    $customer->setExternalCustomerId('cus_live');
    $em->persist($customer);
    $em->flush();
    $em->clear();

    expect($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7)?->getExternalCustomerId())->toBe('cus_live')
        ->and($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(2, 7))->toBeNull();
});

test('a second customer row for the same gateway and client violates the unique constraint', function (): void {
    // This is the DB-level backstop Payment_Adapter_Stripe::cacheGatewayCustomer() relies on:
    // if two requests race and both try to cache a customer for the same (gateway, client),
    // the database refuses to end up with two different Stripe customers cached for that pair.
    $em = payGatewayCustomerEntityManager();

    $first = new PayGatewayCustomer();
    $first->setPayGatewayId(1);
    $first->setClientId(7);
    $first->setExternalCustomerId('cus_first');
    $em->persist($first);
    $em->flush();

    $second = new PayGatewayCustomer();
    $second->setPayGatewayId(1);
    $second->setClientId(7);
    $second->setExternalCustomerId('cus_second');
    $em->persist($second);

    expect(fn () => $em->flush())->toThrow(UniqueConstraintViolationException::class);
});
