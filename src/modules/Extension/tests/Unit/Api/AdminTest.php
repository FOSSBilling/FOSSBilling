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

test('getDi returns the dependency injection container', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('activate activates an extension', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activateExistingExtension')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->activate([]);
    expect($result)->toBeArray();
});

test('configGet gets extension config', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn(['key' => 'value']);

    $api->setService($serviceMock);

    $result = $api->config_get([]);
    expect($result)->toBeArray();
    expect($result)->toBe(['key' => 'value']);
});

test('configSave saves extension config', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setConfig')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->config_save([]);
    expect($result)->toBeTrue();
});

test('getList returns extensions list', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getExtensionsList')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('getNavigation returns admin navigation', function (): void {
    $api = new Box\Mod\Extension\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdminNavigation')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_navigation([]);
    expect($result)->toBeArray();
});
