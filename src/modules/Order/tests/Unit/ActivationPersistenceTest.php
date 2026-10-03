<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

use function Tests\Helpers\container;

test('activateOrder skips an order already activated through RedBean', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $config = ORMSetup::createAttributeMetadataConfig(
        paths: [PATH_MODS . '/Order/Entity'],
        isDevMode: true,
        cache: new ArrayAdapter(),
    );
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('Tests\\OrderActivation\\Proxies');
    $em = new EntityManager($connection, $config);
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Order::class)]);

    try {
        $order = new Order();
        $order->setStatus(Order::STATUS_PENDING_SETUP);
        $em->persist($order);
        $em->flush();

        // GH-4456: simulate the invoice task's RedBean write while checkout
        // still holds the pending Doctrine entity.
        $connection->update('client_order', [
            'status' => Order::STATUS_ACTIVE,
            'service_id' => 123,
        ], ['id' => $order->getId()]);

        expect($em->getRepository(Order::class)->find($order->getId()))->toBe($order)
            ->and($order->getStatus())->toBe(Order::STATUS_PENDING_SETUP)
            ->and($order->getServiceId())->toBeNull();

        $events = Mockery::mock(Box_EventManager::class);
        $events->shouldReceive('fire')->never();
        $service = Mockery::mock(Service::class)->makePartial();
        $service->shouldReceive('createFromOrder')->never();
        $service->shouldReceive('activateOrderAddons')->never();

        $di = container();
        $di['em'] = $em;
        $di['events_manager'] = $events;
        $service->setDi($di);

        expect($service->activateOrder($order))->toBeTrue()
            ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE)
            ->and($order->getServiceId())->toBe(123);
    } finally {
        $connection->close();
    }
});
