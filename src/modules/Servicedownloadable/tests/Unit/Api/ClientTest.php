<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use Symfony\Component\HttpFoundation\Response;

use function Tests\Helpers\container;
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
        'file_id' => 2,
    ];

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->once();

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity($modelClient);
    $api->setDi($di);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Order not found');
});

test('throws exception when sending file with order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')->once();
    $orderServiceMock->shouldReceive('getOrderService')->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->once()
        ->andReturn(new Model_ClientOrder());

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): bool => $api->send_file($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order is not activated');
});

test('does not send a file from outside the order service', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());
    $order->status = 'active';

    $db = Mockery::mock('\Box_Database');
    $db->shouldReceive('findOne')->once()->andReturn($order);
    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('assertOrderUsable')->once()->with($order);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturn(new ServiceDownloadable());

    $di = container();
    $di['db'] = $db;
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderService]));
    $api->setDi($di);
    $api->setIdentity($client);

    expect(fn () => $api->send_file(['order_id' => 1, 'file_id' => 99]))
        ->toThrow(FOSSBilling\InformationException::class, 'File not found');
});

test('sends file', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicedownloadable\Api\Client());
    $data = [
        'order_id' => 1,
        'file_id' => 2,
    ];

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

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

    $mockOrder = new Model_ClientOrder();
    $mockOrder->loadBean(new Tests\Helpers\DummyBean());
    $mockOrder->status = 'active';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->once()
        ->andReturn($mockOrder);

    $di = container();
    $di['db'] = $dbMock;
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

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());

    $expiredOrder = new Model_ClientOrder();
    $expiredOrder->loadBean(new Tests\Helpers\DummyBean());
    $expiredOrder->status = 'active';
    $expiredOrder->expires_at = date('Y-m-d H:i:s', time() - 3600);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($expiredOrder);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('assertOrderUsable')
        ->once()
        ->with($expiredOrder)
        ->andThrow(new FOSSBilling\InformationException('Subscription expired'));
    $orderServiceMock->shouldReceive('getOrderService')->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderServiceMock]));

    $api->setDi($di);
    $api->setIdentity($modelClient);

    expect(fn (): Response => $api->send_file($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Subscription expired');
});
