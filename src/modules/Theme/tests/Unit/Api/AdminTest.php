<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Theme\Api\Admin;

use function Tests\Helpers\container;

test('testGetDi', function (): void {
    $api = apiEndpoint(new Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testGetList', function (): void {
    $api = apiEndpoint(new Admin());
    $systemServiceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $systemServiceMock->shouldReceive('getThemes')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $api->setService($systemServiceMock);

    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('testGet', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'code' => 'themeCode',
    ];

    $systemServiceMock = Mockery::mock(Box\Mod\Theme\Service::class);
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

test('testSelectClientTheme', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'code' => 'default/client',
        'client' => true,
    ];

    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);

    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('setParamValue')
        ->once()
        ->with('theme', 'default/client');
    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->atLeast()
        ->once()
        ->with('theme', 'manage', Mockery::any(), Mockery::any());

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => strtolower($name) === 'staff' ? $staffServiceMock : $systemServiceMock);
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->select($data);
    expect($result)->toBeTrue();
});

test('testSelectAdminTheme', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'code' => 'default/admin',
        'client' => false,
    ];

    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(Box\Mod\Theme\Service::class);
    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);

    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('setParamValue')
        ->once()
        ->with('admin_theme', 'default/admin');
    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')
        ->atLeast()
        ->once()
        ->with('theme', 'manage', Mockery::any(), Mockery::any());

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => strtolower($name) === 'staff' ? $staffServiceMock : $systemServiceMock);
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->select($data);
    expect($result)->toBeTrue();
});

test('testSelectRequiresClientParameter', function (): void {
    $api = apiEndpoint(new Admin());
    $data = ['code' => 'default/admin'];

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => $staffServiceMock);
    $api->setDi($di);
    $api->setService(Mockery::mock(Box\Mod\Theme\Service::class));

    expect(fn (): bool => $api->select($data))
        ->toThrow(FOSSBilling\InformationException::class, 'The "client" parameter is required.');
});

test('testSelectRejectsInvalidClientParameter', function (): void {
    $api = apiEndpoint(new Admin());
    $data = ['code' => 'default/admin', 'client' => 'not-a-boolean'];

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class)->shouldIgnoreMissing();
    $staffServiceMock->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name = ''): Mockery\MockInterface => $staffServiceMock);
    $api->setDi($di);
    $api->setService(Mockery::mock(Box\Mod\Theme\Service::class));

    expect(fn (): bool => $api->select($data))
        ->toThrow(FOSSBilling\InformationException::class, 'Invalid "client" parameter.');
});

test('testPresetDelete', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(Box\Mod\Theme\Service::class);
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
    $api = apiEndpoint(new Admin());
    $data = [
        'code' => 'themeCode',
        'preset' => 'themePreset',
    ];

    $themeMock = Mockery::mock(Box\Mod\Theme\Model\Theme::class);

    $serviceMock = Mockery::mock(Box\Mod\Theme\Service::class);
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
