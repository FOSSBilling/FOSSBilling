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
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Servicehosting\Api\Client;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

afterEach(function (): void {
    Mockery::close();
});

test('testGetDi', function (): void {
    $api = apiEndpoint(new Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangeUsername', function (): void {
    $api = apiEndpoint(new Client());
    $getServiceReturnValue = [createEntity(Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('changeAccountUsername')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_username([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangeDomain', function (): void {
    $api = apiEndpoint(new Client());
    $getServiceReturnValue = [createEntity(Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('changeAccountDomain')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_domain([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePassword', function (): void {
    $api = apiEndpoint(new Client());
    $getServiceReturnValue = [createEntity(Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('changeAccountPassword')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_password([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpGetPairs', function (): void {
    $api = apiEndpoint(new Client());
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('getOrderableHpPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->hp_get_pairs([]);
    expect($result)->toBeArray();
});

test('testGetService', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn($clientOrderModel);

    $model = new ServiceHosting();
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock
    ->shouldReceive('assertOrderUsable')
    ->atLeast()->once();
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);
    $result = $api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(Order::class);
    expect($result[1])->toBeInstanceOf(ServiceHosting::class);
});

test('testGetServiceOrderNotActivated', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock
    ->shouldReceive('assertOrderUsable')
    ->atLeast()->once();
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $this->expectException(FOSSBilling\Core\Exception\InformationException::class);
    $this->expectExceptionMessage('Order is not activated');
    $api->_getService($data);
});

test('testGetServiceOrderNotFound', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = null;
    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn($clientOrderModel);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $this->expectException(FOSSBilling\Core\Exception\InformationException::class);
    $this->expectExceptionMessage('Order not found');
    $api->_getService($data);
});

test('testGetServiceMissingOrderId', function (): void {
    $api = apiEndpoint(new Client());
    $data = [];

    $this->expectException(FOSSBilling\Core\Exception\BaseException::class);
    $this->expectExceptionMessage('Order ID is required');
    $api->_getService($data);
});

test('testGetServiceThrowsForExpiredOrder', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')->atLeast()->once()->andReturn($clientOrderModel);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($clientOrderModel)
        ->andThrow(new FOSSBilling\Core\Exception\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Subscription expired');
});
