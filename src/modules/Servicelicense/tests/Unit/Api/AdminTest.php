<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicelicense\Entity\ServiceLicense;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

use function Tests\Helpers\container;

test('gets dependency injection container', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('gets plugin pairs', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $licensePluginArray[]['filename'] = 'plugin1';
    $licensePluginArray[]['filename'] = 'plugin2';
    $licensePluginArray[]['filename'] = 'plugin3';

    $expected = [
        'plugin1' => 'plugin1',
        'plugin2' => 'plugin2',
        'plugin3' => 'plugin3',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('getLicensePlugins')
        ->atLeast()
        ->once()
        ->andReturn($licensePluginArray);

    $api->setService($serviceMock);

    $result = $api->plugin_get_pairs([]);
    expect($result)->toBeArray()
        ->and($result)->toBe($expected);
});

test('updates license', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $data = [
        'order_id' => 1,
    ];

    $apiMock = apiEndpoint(Mockery::mock(Box\Mod\Servicelicense\Api\Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getService')
        ->atLeast()
        ->once()
        ->andReturn(new ServiceLicense());

    $serviceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('update')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);
    $result = $apiMock->update($data);

    expect($result)->toBeBool()
        ->and($result)->toBeTrue();
});

test('resets license', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $data = [
        'order_id' => 1,
    ];

    $apiMock = apiEndpoint(Mockery::mock(Box\Mod\Servicelicense\Api\Admin::class)->makePartial());
    $apiMock->shouldAllowMockingProtectedMethods();
    $apiMock->shouldReceive('_getService')
        ->atLeast()
        ->once()
        ->andReturn(new ServiceLicense());

    $serviceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('reset')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $apiMock->setService($serviceMock);
    $result = $apiMock->reset($data);

    expect($result)->toBeBool()
        ->and($result)->toBeTrue();
});

test('gets service', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $data['order_id'] = 1;

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('find')
        ->atLeast()
        ->once()
        ->with(1)
        ->andReturn(new Box\Mod\Order\Entity\Order());

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->atLeast()
        ->once()
        ->andReturn($orderRepo);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(new ServiceLicense());

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(ServiceLicense::class);
});

test('throws exception when order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $data['order_id'] = 1;

    $orderRepo = Mockery::mock(EntityRepository::class);
    $orderRepo->shouldReceive('find')
        ->atLeast()
        ->once()
        ->with(1)
        ->andReturn(new Box\Mod\Order\Entity\Order());

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')
        ->atLeast()
        ->once()
        ->andReturn($orderRepo);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Order is not activated');
});
