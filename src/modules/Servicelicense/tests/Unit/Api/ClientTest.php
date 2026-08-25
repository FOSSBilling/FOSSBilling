<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('getDi returns dependency injection container', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('reset returns true', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data = [
        'order_id' => 1,
    ];

    $apiMock = apiEndpoint(Mockery::mock(Box\Mod\Servicelicense\Api\Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getService')
        ->atLeast()
        ->once()
        ->andReturn(new ServiceLicense());

    $serviceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('reset')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);
    $result = $apiMock->reset($data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getService returns service license entity', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $order = new Order();
    $order->setStatus(Order::STATUS_ACTIVE);

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')
        ->atLeast()
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($order);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->atLeast()
        ->once()
        ->andReturn($orderRepo);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()
        ->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(new ServiceLicense());

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(ServiceLicense::class);
});

test('getService throws exception when order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $inactiveOrder = new Order();

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')
        ->atLeast()
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($inactiveOrder);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->atLeast()
        ->once()
        ->andReturn($orderRepo);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()
        ->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->never()
        ->andReturn(null);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $api->_getService($data);
})->throws(FOSSBilling\Exception\BaseException::class, 'Order is not activated');

test('getService throws exception for expired order', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $expiredOrder = new Order();
    $expiredOrder->setStatus(Order::STATUS_ACTIVE);

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('findOneBy')
        ->atLeast()
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($expiredOrder);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->atLeast()
        ->once()
        ->andReturn($orderRepo);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($expiredOrder)
        ->andThrow(new FOSSBilling\Exception\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Subscription expired');
});
