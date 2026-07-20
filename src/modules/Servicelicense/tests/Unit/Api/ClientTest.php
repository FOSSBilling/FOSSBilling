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

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()
        ->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

    $dbMock = Mockery::mock('\Box_Database');
    $clientOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['status' => Model_ClientOrder::STATUS_ACTIVE]);

    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($clientOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);
    $api->setIdentity($clientModel);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);
});

test('getService throws exception when order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->atLeast()
        ->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->never()
        ->andReturn(null);

    $inactiveOrder = createEntity(\Box\Mod\Order\Entity\Order::class);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($inactiveOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class);
    $api->setIdentity($clientModel);

    $api->_getService($data);
})->throws(FOSSBilling\Exception::class, 'Order is not activated');

test('getService throws exception for expired order', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Client());
    $data['order_id'] = 1;

    $expiredOrder = new Model_ClientOrder();
    $expiredOrder->loadBean(new Tests\Helpers\DummyBean());
    $expiredOrder->status = Model_ClientOrder::STATUS_ACTIVE;
    $expiredOrder->expires_at = date('Y-m-d H:i:s', time() - 3600);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($expiredOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($expiredOrder)
        ->andThrow(new FOSSBilling\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = new Model_Client();
    $clientModel->loadBean(new Tests\Helpers\DummyBean());
    $api->setIdentity($clientModel);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Subscription expired');
});
