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

beforeEach(function () {
    $this->api = new \Box\Mod\Extension\Api\Admin();
});

test('getDi returns the dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('activate activates an extension', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('activateExistingExtension')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->activate([]);
    expect($result)->toBeArray();
});

test('configGet gets extension config', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getConfig')
        ->atLeast()
        ->once()
        ->andReturn(['key' => 'value']);

    $this->api->setService($serviceMock);

    $result = $this->api->config_get([]);
    expect($result)->toBeArray();
    expect($result)->toBe(['key' => 'value']);
});

test('configSave saves extension config', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('setConfig')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $this->api->setService($serviceMock);

    $result = $this->api->config_save([]);
    expect($result)->toBeTrue();
});

test('getList returns extensions list', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getExtensionsList')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('getNavigation returns admin navigation', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Extension\Service::class)->makePartial();
    $serviceMock->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getAdminNavigation')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->get_navigation([]);
    expect($result)->toBeArray();
});
