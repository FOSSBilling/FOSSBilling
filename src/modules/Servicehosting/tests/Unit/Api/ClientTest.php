<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicehosting\Api\Client;
use Box\Mod\Servicehosting\Entity\ServiceHosting;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('testGetDi', function (): void {
    $api = apiEndpoint(new Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangeUsername', function (): void {
    $api = apiEndpoint(new Client());
    $getServiceReturnValue = [createEntity(\Box\Mod\Order\Entity\Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $getServiceReturnValue = [createEntity(\Box\Mod\Order\Entity\Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $getServiceReturnValue = [createEntity(\Box\Mod\Order\Entity\Order::class), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpPairs')
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

    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class, ['status' => Model_ClientOrder::STATUS_ACTIVE]);
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = new ServiceHosting();
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);
    $result = $api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Box\Mod\Order\Entity\Order::class);
    expect($result[1])->toBeInstanceOf(ServiceHosting::class);
});

test('testGetServiceOrderNotActivated', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = createEntity(\Box\Mod\Order\Entity\Order::class);
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $this->expectException(FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('Order is not activated');
    $api->_getService($data);
});

test('testGetServiceOrderNotFound', function (): void {
    $api = apiEndpoint(new Client());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = null;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);

    $clientModel = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $api->setIdentity($clientModel);

    $this->expectException(FOSSBilling\InformationException::class);
    $this->expectExceptionMessage('Order not found');
    $api->_getService($data);
});

test('testGetServiceMissingOrderId', function (): void {
    $api = apiEndpoint(new Client());
    $data = [];

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order ID is required');
    $api->_getService($data);
});
