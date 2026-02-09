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
use Box\Mod\Theme\Api\Admin;

beforeEach(function (): void {
    $this->api = new Admin();
});

test('testGetDi', function (): void {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('testGetList', function (): void {
    $systemServiceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->shouldReceive('getThemes')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $this->api->setService($systemServiceMock);

    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('testGet', function (): void {
    $data = [
        'code' => 'themeCode',
    ];

    $systemServiceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->shouldReceive('loadTheme')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($systemServiceMock);

    $result = $this->api->get($data);
    expect($result)->toBeArray();
});

test('testSelectNotAdminTheme', function (): void {
    $data = [
        'code' => 'pjw',
    ];

    $themeMock = Mockery::mock(\Box\Mod\Theme\Model\Theme::class);
    $themeMock->shouldReceive('isAdminAreaTheme')
        ->atLeast()
        ->once()
        ->andReturn(false);

    $serviceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('setParamValue')
        ->with('theme', Mockery::any());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->select($data);
    expect($result)->toBeTrue();
});

test('testSelectAdminTheme', function (): void {
    $data = [
        'code' => 'pjw',
    ];

    $themeMock = Mockery::mock(\Box\Mod\Theme\Model\Theme::class);
    $themeMock->shouldReceive('isAdminAreaTheme')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $serviceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('setParamValue')
        ->with('admin_theme', Mockery::any());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $systemServiceMock);
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->select($data);
    expect($result)->toBeTrue();
});

test('testPresetDelete', function (): void {
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeMock = Mockery::mock(\Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);
    $serviceMock->shouldReceive('deletePreset')
        ->atLeast()
        ->once();

    $di = container();
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->preset_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testPresetSelect', function (): void {
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeMock = Mockery::mock(\Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);
    $serviceMock->shouldReceive('setCurrentThemePreset')
        ->atLeast()
        ->once();

    $di = container();
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->preset_select($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
