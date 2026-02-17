<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicecustom\Api\Admin;
use Box\Mod\Servicecustom\Service;

test('updates configuration', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateConfig')
        ->atLeast()->once();

    $data = [
        'order_id' => 1,
        'config' => [
            'param1' => 'value1',
        ],
    ];

    $api->setService($serviceMock);
    $api->update($data);
});

test('throws exception when updating without order_id', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateConfig')
        ->never();

    $data = [
        'config' => [
            'param1' => 'value1',
        ],
    ];

    $api->setService($serviceMock);

    expect(fn () => $api->update($data))
        ->toThrow(\Exception::class);
});

test('returns true when updating without config', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateConfig')
        ->never();

    $data = [
        'order_id' => 1,
    ];

    $api->setService($serviceMock);
    $result = $api->update($data);

    expect($result)->toBeTrue();
});

test('returns true when config is not an array', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateConfig')
        ->never();

    $data = [
        'order_id' => 1,
        'config' => 'NotArray',
    ];

    $api->setService($serviceMock);
    $result = $api->update($data);

    expect($result)->toBeTrue();
});

test('calls magic method', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->atLeast()->once()
        ->andReturn(new \Model_ServiceCustom());
    $serviceMock->shouldReceive('customCall')
        ->atLeast()->once()
        ->andReturn(null);

    $arguments = [
        0 => [
            'order_id' => 1,
        ],
    ];

    $api->setService($serviceMock);
    $api->__call('delete', $arguments);
});

test('throws exception when calling magic method without arguments', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->never();
    $serviceMock->shouldReceive('customCall')
        ->never();

    $arguments = [];

    $api->setService($serviceMock);

    expect(fn () => $api->__call('delete', $arguments))
        ->toThrow(\Exception::class);
});

test('throws exception when calling magic method without order_id', function (): void {
    $api = new \Box\Mod\Servicecustom\Api\Admin();
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->never();
    $serviceMock->shouldReceive('customCall')
        ->never();

    $arguments = [
        0 => [],
    ];

    $api->setService($serviceMock);

    expect(fn () => $api->__call('delete', $arguments))
        ->toThrow(\Exception::class);
});
