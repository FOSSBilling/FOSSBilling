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
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use Symfony\Component\HttpFoundation\Response;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

test('gets dependency injection container', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('throws exception when sending file with missing order id', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [];

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception\BaseException::class, 'Order ID is required');
});

test('throws exception when sending file with order not found', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')->once()->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);

    $api->setIdentity($modelClient);
    $api->setDi($di);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Order not found');
});

test('throws exception when sending file with order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')->once();
    $orderServiceMock->shouldReceive('getOrderService')->once();

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->andReturn(createEntity(Order::class));

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception\BaseException::class, 'Order is not activated');
});

test('does not send a file from outside the order service', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $client = createEntity(Box\Mod\Client\Entity\Client::class);

    $order = createEntity(Order::class, ['status' => 'active']);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')->once()->andReturn($order);
    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('assertOrderUsable')->once()->with($order);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturn(new ServiceDownloadable());

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderService]));
    $api->setDi($di);
    $api->setIdentity($client);

    expect(fn () => $api->send_file(['order_id' => 1, 'file_id' => 99]))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'File not found');
});

test('sends file', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $serviceMock = Mockery::mock(Box\Mod\Servicedownloadable\Service::class);
    $response = new Response('download');
    $serviceMock->shouldReceive('sendFile')
        ->once()
        ->with(Mockery::type(ServiceDownloadableFile::class))
        ->andReturn($response);

    $file = new ServiceDownloadableFile(str_repeat('a', 32), 'file.zip', str_repeat('b', 64));
    (new ReflectionProperty($file, 'id'))->setValue($file, 2);
    $downloadable = new ServiceDownloadable();
    $downloadable->addFile($file);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once();
    $orderServiceMock->shouldReceive('getOrderService')
        ->once()
        ->andReturn($downloadable);

    $mockOrder = createEntity(Order::class, ['status' => 'active']);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->andReturn($mockOrder);

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);
    $api->setService($serviceMock);

    $result = $api->send_file($data);
    expect($result)->toBe($response);
});

test('throws exception when sending file for expired order', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class);

    $expiredOrder = createEntity(Order::class, [
        'status' => 'active',
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->atLeast()
        ->once()
        ->andReturn($expiredOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($expiredOrder)
        ->andThrow(new FOSSBilling\Exception\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): Response => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'Subscription expired');
});
