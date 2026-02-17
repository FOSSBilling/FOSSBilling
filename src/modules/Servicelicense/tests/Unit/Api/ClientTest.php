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
    $api = new \Box\Mod\Servicelicense\Api\Client();
});

test('getDi returns dependency injection container', function (): void {
    $api = new \Box\Mod\Servicelicense\Api\Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('reset returns true', function (): void {
    $api = new \Box\Mod\Servicelicense\Api\Client();
    $data = [
        'order_id' => 1,
    ];

    $apiMock = Mockery::mock(\Box\Mod\Servicelicense\Api\Client::class)->makePartial();
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getService')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ServiceLicense());

    $serviceMock = Mockery::mock(\Box\Mod\Servicelicense\Service::class);
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
    $api = new \Box\Mod\Servicelicense\Api\Client();
    $data['order_id'] = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ServiceLicense());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ClientOrder());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $api->setIdentity($clientModel);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(\Model_ServiceLicense::class);
});

test('getService throws exception when order not activated', function (): void {
    $api = new \Box\Mod\Servicelicense\Api\Client();
    $data['order_id'] = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(new \Model_ClientOrder());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $clientModel = new \Model_Client();
    $clientModel->loadBean(new \Tests\Helpers\DummyBean());
    $api->setIdentity($clientModel);

    $api->_getService($data);
})->throws(\FOSSBilling\Exception::class, 'Order is not activated');
