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
use Box\Mod\Servicedomain\Api\Client;
use Box\Mod\Servicedomain\Service;
use Box\Mod\Order\Service as OrderService;

test('updates nameservers', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateNameservers')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $clientApiMock->setDi($di);

    $data = [];
    $result = $clientApiMock->update_nameservers($data);

    expect($result)->toBeTrue();
});

test('updates contacts', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateContacts')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->update_contacts($data);

    expect($result)->toBeTrue();
});

test('enables privacy protection', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('enablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->enable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('disables privacy protection', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('disablePrivacyProtection')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->disable_privacy_protection($data);

    expect($result)->toBeTrue();
});

test('gets transfer code', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getTransferCode')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->get_transfer_code($data);

    expect($result)->toBeTrue();
});

test('locks domain', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->lock($data);

    expect($result)->toBeTrue();
});

test('unlocks domain', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $model = new \Model_ServiceDomain();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $clientApiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $clientApiMock->shouldReceive('_getService')
        ->atLeast()->once()
        ->andReturn($model);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('unlock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApiMock->setService($serviceMock);

    $data = [];
    $result = $clientApiMock->unlock($data);

    expect($result)->toBeTrue();
});

test('gets service', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->atLeast()->once()
        ->andReturn(true);

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(new \Model_ClientOrder());
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(new \Model_ServiceDomain());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new \Model_Client());

    $data = [
        'order_id' => 1,
    ];
    $result = $clientApi->lock($data);

    expect($result)->toBeTrue();
});

test('throws exception when getting service without order_id', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->never();
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new \Model_Client());

    $data = [];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when getting service order not found', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(null);
    $orderServiceMock->shouldReceive('getOrderService')
        ->never();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new \Model_Client());

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when getting service order not activated', function (): void {
    $clientApi = new \Box\Mod\Servicedomain\Api\Client();
    $api = new \Box\Mod\Servicedomain\Api\Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('lock')
        ->never();

    $clientApi->setService($serviceMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(new \Model_ClientOrder());
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $clientApi->setDi($di);

    $clientApi->setIdentity(new \Model_Client());

    $data = [
        'order_id' => 1,
    ];

    expect(fn () => $clientApi->lock($data))
        ->toThrow(\FOSSBilling\Exception::class);
});
