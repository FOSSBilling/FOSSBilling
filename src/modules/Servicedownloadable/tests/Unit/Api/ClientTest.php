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
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
});

test('gets dependency injection container', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('throws exception when sending file with missing order id', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
    $data = [];

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order ID is required');
});

test('throws exception when sending file with order not found', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
    $data = [
        'order_id' => 1,
    ];

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity($modelClient);
    $api->setDi($di);

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order not found');
});

test('throws exception when sending file with order not activated', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
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

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn () => $api->send_file($data))
        ->toThrow(\FOSSBilling\Exception::class, 'Order is not activated');
});

test('sends file', function (): void {
    $api = new \Box\Mod\Servicedownloadable\Api\Client();
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

    $api->setDi($di);
    $api->setIdentity($modelClient);
    $api->setService($serviceMock);

    $result = $api->send_file($data);
    expect($result)->toBeBool()
        ->and($result)->toBeTrue();
});
