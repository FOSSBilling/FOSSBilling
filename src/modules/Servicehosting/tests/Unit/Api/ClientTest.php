<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Servicehosting\Api\Client;

test('testGetDi', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangeUsername', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Client::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
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
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Client::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
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
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Client::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
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
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->hp_get_pairs([]);
    expect($result)->toBeArray();
});

test('testGetService', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = new \Model_ServiceHosting();
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->id = 1;
    $api->setIdentity($clientModel);
    $result = $api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('\Model_ClientOrder');
    expect($result[1])->toBeInstanceOf('\Model_ServiceHosting');
});

test('testGetServiceOrderNotActivated', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->id = 1;
    $api->setIdentity($clientModel);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order is not activated');
    $api->_getService($data);
});

test('testGetServiceOrderNotFound', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
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

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $clientModel->id = 1;
    $api->setIdentity($clientModel);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order not found');
    $api->_getService($data);
});

test('testGetServiceMissingOrderId', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Client();
    $data = [];

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order ID is required');
    $api->_getService($data);
});
