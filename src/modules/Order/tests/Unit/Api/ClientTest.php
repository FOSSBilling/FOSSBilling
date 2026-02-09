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
use Box\Mod\Order\Api\Client;
use Box\Mod\Order\Service;

beforeEach(function () {
    $this->api = new Client();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('gets order list', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $resultSet = [
        'list' => [
            0 => ['id' => 1],
        ],
    ];
    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn($resultSet);

    $clientOrderMock = new Model_ClientOrder();
    $clientOrderMock->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($clientOrderMock);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['db'] = $dbMock;

    $this->api->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $this->api->setIdentity($client);
    $this->api->setService($serviceMock);

    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('gets expiring order list', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')
        ->atLeast()->once()
        ->andReturn(['query', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $paginatorMock->shouldReceive('getDefaultPerPage')
        ->atLeast()->once()
        ->andReturn(25);
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $this->api->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $this->api->setIdentity($client);
    $this->api->setService($serviceMock);

    $data = ['expiring' => true];
    $result = $this->api->get_list($data);
    expect($result)->toBeArray();
});

test('gets an order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

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

test('gets order addons', function () {
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderAddonsList')
        ->atLeast()->once()
        ->andReturn([new Model_ClientOrder()]);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

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

test('gets order service data', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderServiceData')
        ->atLeast()->once()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock->setService($serviceMock);
    $apiMock->setIdentity($client);

    $data = ['id' => 1];
    $result = $apiMock->service($data);
    expect($result)->toBeArray();
});

test('gets upgradable products', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $apiMock = Mockery::mock(Client::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')
        ->atLeast()->once()
        ->andReturn($order);

    $productServiceMock = Mockery::mock(\Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('getUpgradablePairs')
        ->atLeast()->once()
        ->andReturn([]);

    $product = new Model_Product();
    $product->loadBean(new \RedBeanPHP\OODBBean());

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

test('deletes a pending order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());
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

test('throws exception when deleting non-pending order', function () {
    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

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
        ->toThrow(\FOSSBilling\Exception::class);
});

test('gets order for client', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');

    $order = new Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn($order);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);
    $this->api->setIdentity($client);

    $data = ['id' => 1];
    $this->api->get($data);
});

test('throws exception when order not found for client', function () {
    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('checkRequiredParamsForArray');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findForClientById')
        ->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('toApiArray')
        ->never()
        ->andReturn([]);

    $client = new Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['validator'] = $validatorMock;
    $this->api->setDi($di);

    $this->api->setService($serviceMock);
    $this->api->setIdentity($client);

    $data = ['id' => 1];

    expect(fn () => $this->api->get($data))
        ->toThrow(\FOSSBilling\Exception::class);
});
