<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

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
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

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

test('getService returns service license model', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 1, 'clientId' => 1, 'status' => \Box\Mod\Order\Entity\Order::STATUS_ACTIVE]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($clientOrder);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);
});

test('getService throws exception when order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $inactiveOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 1, 'clientId' => 1]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($inactiveOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $api->_getService($data);
})->throws(FOSSBilling\Exception::class, 'Order is not activated');

test('getService throws exception for expired order', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $expiredOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 1, 'clientId' => 1, 'status' => 'active', 'expires_at' => date('Y-m-d H:i:s', time() - 3600)]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($expiredOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);
    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order is not activated');
});
