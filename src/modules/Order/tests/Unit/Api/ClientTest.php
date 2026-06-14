<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Api\Client;
use Box\Mod\Order\Service;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = new Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets order list', function (): void {
    $api = new Client();
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
        ->once()
        ->andReturn(['query', []]);
    $serviceMock->shouldReceive('getBatchForApi')
        ->once()
        ->with([3, 2, 1], $client)
        ->andReturn([
            ['id' => 3, 'title' => 'Third order'],
            ['id' => 2, 'title' => 'Second order'],
            ['id' => 1, 'title' => 'First order'],
        ]);
    $serviceMock->shouldReceive('toApiArray')
        ->never();

    $resultSet = [
        'list' => [
            ['id' => 3],
            ['id' => 2],
            ['id' => 1],
        ],
        'total' => 3,
    ];
    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->once()
        ->andReturn($resultSet);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->never();

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['db'] = $dbMock;

    $api->setDi($di);

    $api->setIdentity($client);
    $api->setService($serviceMock);

    $result = $api->get_list([]);
    expect($result)
        ->toBeArray()
        ->and($result['list'])->toBe([
            ['id' => 3, 'title' => 'Third order'],
            ['id' => 2, 'title' => 'Second order'],
            ['id' => 1, 'title' => 'First order'],
        ])
        ->and($result['total'])->toBe(3);
});

test('gets expiring order list', function (): void {
    $api = new Client();
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')
        ->once()
        ->andReturn(['query', []]);
    $serviceMock->shouldReceive('getBatchForApi')
        ->once()
        ->with([1], $client)
        ->andReturn([
            ['id' => 1, 'title' => 'Expiring order'],
        ]);

    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->once()
        ->andReturn([
            'list' => [
                ['id' => 1],
            ],
        ]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $api->setDi($di);

    $api->setIdentity($client);
    $api->setService($serviceMock);

    $data = ['expiring' => true];
    $result = $api->get_list($data);
    expect($result)
        ->toBeArray()
        ->and($result['list'])->toBe([
            ['id' => 1, 'title' => 'Expiring order'],
        ]);
});

test('gets an order', function (): void {
    $api = new Client();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_ACTIVE;

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->get($data);
    expect($result)->toBeArray();
});

test('gets order addons', function (): void {
    $api = new Client();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([new Model_ClientOrder()]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $apiMock->setService($serviceMock);

    $data = ['status' => Model_ClientOrder::STATUS_ACTIVE];
    $result = $apiMock->addons($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets order service data', function (): void {
    $api = new Client();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_ACTIVE;

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $apiMock->setService($serviceMock);
    $apiMock->setIdentity($client);

    $data = ['id' => 1];
    $result = $apiMock->service($data);
    expect($result)->toBeArray();
});

test('gets upgradable products', function (): void {
    $api = new Client();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('getUpgradablePairs')
        ->atLeast()->once()
        ->andReturn([]);

    $product = new Model_Product();
    $product->loadBean(new RedBeanPHP\OODBBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($product);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn () => $productServiceMock);
    $apiMock->setDi($di);

    $result = $apiMock->upgradables([]);
    expect($result)->toBeArray();
});

test('deletes a pending order', function (): void {
    $api = new Client();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = Model_ClientOrder::STATUS_PENDING_SETUP;

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')
        ->atLeast()->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->delete($data);
    expect($result)->toBeTrue();
});

test('throws exception when deleting non-pending order', function (): void {
    $api = new Client();
    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')
        ->never()
        ->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];

    expect(fn () => $apiMock->delete($data))
        ->toThrow(FOSSBilling\Exception::class);
});

test('gets order for client', function (): void {
    $api = new Client();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn($order);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity($client);

    $data = ['id' => 1];
    $api->get($data);
});

test('throws exception when order not found for client', function (): void {
    $api = new Client();
    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('toApiArray')
        ->never()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $di = container();
    $di['validator'] = $validatorMock;
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity($client);

    $data = ['id' => 1];

    expect(fn () => $api->get($data))
        ->toThrow(FOSSBilling\Exception::class);
});
