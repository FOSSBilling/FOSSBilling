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

test('findOneByGatewayAndClient finds the cached customer for that gateway, client, and mode', function (): void {
    $em = payGatewayCustomerEntityManager();

    $customer = new PayGatewayCustomer();
    $customer->setPayGatewayId(1);
    $customer->setClientId(7);
    $customer->setTestMode(false);
    $customer->setExternalCustomerId('cus_abc123');
    $em->persist($customer);
    $em->flush();
    $em->clear();

    $found = $em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7, false);

    expect($found?->getExternalCustomerId())->toBe('cus_abc123');
});

test('findOneByGatewayAndClient returns null when no row matches', function (): void {
    $em = payGatewayCustomerEntityManager();

    expect($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 999, false))->toBeNull();
});

test('findOneByGatewayAndClient does not cross gateways for the same client', function (): void {
    $em = payGatewayCustomerEntityManager();

    $customer = new PayGatewayCustomer();
    $customer->setPayGatewayId(1);
    $customer->setClientId(7);
    $customer->setTestMode(false);
    $customer->setExternalCustomerId('cus_live');
    $em->persist($customer);
    $em->flush();
    $em->clear();

    expect($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7, false)?->getExternalCustomerId())->toBe('cus_live')
        ->and($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(2, 7, false))->toBeNull();
});

test('findOneByGatewayAndClient does not cross test/live mode for the same gateway and client', function (): void {
    // A gateway's test and live Stripe API keys are two entirely separate customer
    // namespaces, even though test_mode is just a config toggle on the same PayGateway
    // row - a customer cached while testing must never be looked up (and charged) once
    // the gateway is switched to live, or vice versa.
    $em = payGatewayCustomerEntityManager();

    $liveCustomer = new PayGatewayCustomer();
    $liveCustomer->setPayGatewayId(1);
    $liveCustomer->setClientId(7);
    $liveCustomer->setTestMode(false);
    $liveCustomer->setExternalCustomerId('cus_live');
    $em->persist($liveCustomer);
    $em->flush();
    $em->clear();

    expect($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7, false)?->getExternalCustomerId())->toBe('cus_live')
        ->and($em->getRepository(PayGatewayCustomer::class)->findOneByGatewayAndClient(1, 7, true))->toBeNull();
});

test('a second customer row for the same gateway, client, and mode violates the unique constraint', function (): void {
    // This is the DB-level backstop Payment_Adapter_Stripe::cacheGatewayCustomer() relies on:
    // if two requests race and both try to cache a customer for the same (gateway, client,
    // mode), the database refuses to end up with two different Stripe customers cached for it.
    $em = payGatewayCustomerEntityManager();

    $first = new PayGatewayCustomer();
    $first->setPayGatewayId(1);
    $first->setClientId(7);
    $first->setTestMode(false);
    $first->setExternalCustomerId('cus_first');
    $em->persist($first);
    $em->flush();

    $second = new PayGatewayCustomer();
    $second->setPayGatewayId(1);
    $second->setClientId(7);
    $second->setTestMode(false);
    $second->setExternalCustomerId('cus_second');
    $em->persist($second);

    expect(fn () => $em->flush())->toThrow(UniqueConstraintViolationException::class);
});

test('a second customer row for the same gateway and client but different mode does not violate the unique constraint', function (): void {
    $em = payGatewayCustomerEntityManager();

    $live = new PayGatewayCustomer();
    $live->setPayGatewayId(1);
    $live->setClientId(7);
    $live->setTestMode(false);
    $live->setExternalCustomerId('cus_live');
    $em->persist($live);
    $em->flush();

    $test = new PayGatewayCustomer();
    $test->setPayGatewayId(1);
    $test->setClientId(7);
    $test->setTestMode(true);
    $test->setExternalCustomerId('cus_test');
    $em->persist($test);

    expect(fn () => $em->flush())->not->toThrow(UniqueConstraintViolationException::class);
});
