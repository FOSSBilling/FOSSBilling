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
    $api = new Admin();
});

test('testGetDi', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testGetList', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
    $systemServiceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->shouldReceive('getThemes')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($systemServiceMock);

    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('testGet', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
    $data = [
        'code' => 'themeCode',
    ];

    $systemServiceMock = Mockery::mock(\Box\Mod\Theme\Service::class);
    $systemServiceMock->shouldReceive('loadTheme')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $api->setDi($di);
    $api->setService($systemServiceMock);

    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('testSelectNotAdminTheme', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
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
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->select($data);
    expect($result)->toBeTrue();
});

test('testSelectAdminTheme', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
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
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->select($data);
    expect($result)->toBeTrue();
});

test('testPresetDelete', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
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
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->preset_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testPresetSelect', function (): void {
    $api = new \Box\Mod\Theme\Api\Admin();
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
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->preset_select($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
