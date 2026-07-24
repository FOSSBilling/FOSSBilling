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
use function Tests\Helpers\createEntity;

test('gets list of orders', function (): void {
    $api = apiEndpoint(new Client());

    $orderArr = ['id' => 1];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSearchQuery')->atLeast()->once()->andReturn(['query', []]);
    $serviceMock->shouldReceive('getBatchForApi')->atLeast()->once()->andReturn([$orderArr]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getPaginatedResultSet')->atLeast()->once()->andReturn(['list' => [$orderArr]]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setIdentity($client);
    $api->setService($serviceMock);

    $result = $api->get_list([]);

    expect($result)->toBeArray();
});

test('gets list of expiring orders', function (): void {
    $api = apiEndpoint(new Client());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getSoonExpiringActiveOrdersQuery')->atLeast()->once()->andReturn(['query', []]);
    $serviceMock->shouldReceive('getBatchForApi')->atLeast()->once()->andReturn([]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getPaginatedResultSet')->atLeast()->once()->andReturn(['list' => []]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setIdentity($client);
    $api->setService($serviceMock);

    $result = $api->get_list(['expiring' => true]);

    expect($result)->toBeArray();
});

test('gets a single order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->get($data);

    expect($result)->toBeArray();
});

test('gets addons', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderAddonsList')->atLeast()->once()->andReturn([createEntity(Box\Mod\Order\Entity\Order::class)]);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $apiMock->setService($serviceMock);

    $data = ['status' => Box\Mod\Order\Entity\Order::STATUS_ACTIVE];
    $result = $apiMock->addons($data);

    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('gets order service', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class, ['status' => Box\Mod\Order\Entity\Order::STATUS_ACTIVE]);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getOrderServiceData')->atLeast()->once()->andReturn([]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class);

    $apiMock->setService($serviceMock);
    $apiMock->setIdentity($client);

    $data = ['id' => 1];
    $result = $apiMock->service($data);

    expect($result)->toBeArray();
});

test('gets upgradables', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class, ['product_id' => 5]);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $productServiceMock = Mockery::mock(Box\Mod\Product\Service::class);
    $productServiceMock->shouldReceive('getUpgradablePairsByProductId')
        ->atLeast()->once()
        ->with(5)
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): Mockery\MockInterface => match (strtolower($name)) {
        'product' => $productServiceMock,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });
    $apiMock->setDi($di);

    $data = [];
    $result = $apiMock->upgradables($data);

    expect($result)->toBeArray();
});

test('deletes a pending setup order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class, ['status' => Box\Mod\Order\Entity\Order::STATUS_PENDING_SETUP]);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')->atLeast()->once()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];
    $result = $apiMock->delete($data);

    expect($result)->toBeTrue();
});

test('throws exception when deleting non-pending order', function (): void {
    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $apiMock = apiEndpoint(Mockery::mock(Client::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getOrder')->atLeast()->once()->andReturn($order);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('deleteFromOrder')->never()->andReturn(true);

    $apiMock->setService($serviceMock);

    $data = ['id' => 1];

    expect(fn () => $apiMock->delete($data))->toThrow(FOSSBilling\Exception::class);
});

test('gets order via getOrder', function (): void {
    $api = apiEndpoint(new Client());

    $order = createEntity(Box\Mod\Order\Entity\Order::class);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findEntityForClientById')->atLeast()->once()->andReturn($order);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()->andReturn([]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity($client);

    $data = ['id' => 1];
    $api->get($data);
});

test('throws exception when order not found', function (): void {
    $api = apiEndpoint(new Client());

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('findEntityForClientById')->atLeast()->once()->andReturn(null);
    $serviceMock->shouldReceive('toApiArray')->never()->andReturn([]);

    $client = createEntity(Box\Mod\Client\Entity\Client::class);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity($client);

    $data = ['id' => 1];

    expect(fn () => $api->get($data))->toThrow(FOSSBilling\Exception::class);
});
