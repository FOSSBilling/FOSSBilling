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

beforeEach(function () {
    $this->api = new \Box\Mod\Servicedownloadable\Api\Client();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('throws exception when sending file with missing order id', function () {
    $data = [];

    expect(fn () => $this->api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order ID is required');
});

test('throws exception when sending file with order not found', function () {
    $data = [
        'order_id' => 1,
    ];

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $this->api->setIdentity($modelClient);
    $this->api->setDi($di);

    expect(fn () => $this->api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order not found');
});

test('throws exception when sending file with order not activated', function () {
    $data = [
        'order_id' => 1,
    ];

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ClientOrder());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $this->api->setDi($di);
    $this->api->setIdentity($modelClient);

    expect(fn () => $this->api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order is not activated');
});

test('sends file', function () {
    $data = [
        'order_id' => 1,
    ];

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Servicedownloadable\Service::class);
    $serviceMock->shouldReceive('sendFile')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ServiceDownloadable());

    $mockOrder = new \Model_ClientOrder();
    $mockOrder->loadBean(new \Tests\Helpers\DummyBean());
    $mockOrder->status = 'active';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($mockOrder);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $this->api->setDi($di);
    $this->api->setIdentity($modelClient);
    $this->api->setService($serviceMock);

    $result = $this->api->send_file($data);
    expect($result)->toBeBool()
        ->and($result)->toBeTrue();
});
