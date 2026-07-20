<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

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
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

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
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

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

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(createEntity(\Box\Mod\Servicelicense\Entity\ServiceLicense::class));

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    $result = $api->_getService($data);
    expect($result)->toBeInstanceOf(\Box\Mod\Servicelicense\Entity\ServiceLicense::class);
});

test('throws exception when order not activated', function (): void {
    $api = apiEndpoint(new Box\Mod\Servicelicense\Api\Admin());
    $data['order_id'] = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $api->setDi($di);

    expect(fn () => $api->_getService($data))
        ->toThrow(FOSSBilling\Exception::class, 'Order is not activated');
});
