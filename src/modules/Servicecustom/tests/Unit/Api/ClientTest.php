<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicecustom\Api\Client;
use Box\Mod\Servicecustom\Service;

test('calls custom service method', function (): void {
    $api = apiEndpoint(new Client());
    $identity = (object) ['id' => 1];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->with(1, 1)
        ->atLeast()->once()
        ->andReturn(new Model_ServiceCustom());
    $serviceMock->shouldReceive('customCall')
        ->with(Mockery::type(Model_ServiceCustom::class), 'delete', Mockery::type('array'))
        ->atLeast()->once()
        ->andReturn(null);

    $data = [
        'order_id' => 1,
        'method' => 'delete',
    ];

    $api->setService($serviceMock);
    $api->setIdentity($identity);
    $api->call($data);
});

test('throws exception when calling custom method without order_id', function (): void {
    $api = apiEndpoint(new Client());
    $identity = (object) ['id' => 1];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->never();
    $serviceMock->shouldReceive('customCall')
        ->never();

    $api->setService($serviceMock);
    $api->setIdentity($identity);

    expect(fn () => $api->call(['method' => 'delete']))
        ->toThrow(Exception::class);
});

test('throws exception when calling custom method without method', function (): void {
    $api = apiEndpoint(new Client());
    $identity = (object) ['id' => 1];
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('getServiceCustomByOrderId')
        ->never();
    $serviceMock->shouldReceive('customCall')
        ->never();

    $api->setService($serviceMock);
    $api->setIdentity($identity);

    expect(fn () => $api->call(['order_id' => 1]))
        ->toThrow(Exception::class);
});
