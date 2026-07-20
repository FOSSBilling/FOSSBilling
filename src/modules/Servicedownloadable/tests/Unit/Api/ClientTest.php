<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

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
        ->toThrow(FOSSBilling\Exception::class, 'Order ID is required');
});

test('throws exception when sending file with order not found', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
    ];

    $modelClient = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);

    $api->setIdentity($modelClient);
    $api->setDi($di);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Order not found');
});

test('throws exception when sending file with order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
    ];

    $modelClient = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $mockOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 1, 'status' => 'pending_setup', 'clientId' => 1]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($mockOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order is not activated');
});

test('sends file', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
    ];

    $modelClient = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $serviceMock = Mockery::mock(Box\Mod\Servicedownloadable\Service::class);
    $response = new Response('download');
    $serviceMock->shouldReceive('sendFile')
        ->atLeast()
        ->once()
        ->andReturn($response);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(createEntity(\Box\Mod\Servicedownloadable\Entity\ServiceDownloadable::class));

    $mockOrder = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 1, 'status' => 'active', 'clientId' => 1]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($mockOrder);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
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
    ];

    $modelClient = createEntity(\Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $expiredOrder = createEntity(\Box\Mod\Order\Entity\Order::class, [
        'id' => 1,
        'clientId' => 1,
        'status' => 'active',
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('findOneBy')
        ->once()
        ->with(['id' => 1, 'clientId' => 1])
        ->andReturn($expiredOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class)->shouldIgnoreMissing();
    $orderServiceMock->shouldReceive('getOrderService')
        ->once()
        ->andReturn(null);

    $di = container();
    $di['em']->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): Response => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order is not activated');
});
