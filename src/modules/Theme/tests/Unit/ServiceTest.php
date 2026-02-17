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
use Box\Mod\Theme\Model;
use Box\Mod\Theme\Service;

test('getDi returns the dependency injection container', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('getTheme returns a Theme model instance', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $result = $service->getTheme('huraga');
    expect($result)->toBeInstanceOf(Model\Theme::class);
});

test('getCurrentThemePreset sets current theme preset when empty', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setCurrentThemePreset')
        ->atLeast()
        ->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getCurrentPreset')
        ->atLeast()
        ->once()
        ->andReturn('CurrentPresetString');
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = container();

    $di['theme'] = $di->protect(fn (): \Mockery\MockInterface => $themeMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->getCurrentThemePreset($themeMock);
    expect($result)->toBeString();
});

test('setCurrentThemePreset updates theme preset', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = container();

    $di['theme'] = $di->protect(fn (): \Mockery\MockInterface => $themeMock);
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->setCurrentThemePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletePreset removes a theme preset', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('exec')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = container();

    $di['theme'] = $di->protect(fn (): \Mockery\MockInterface => $themeMock);
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->deletePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getThemePresets returns available presets', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('updateSettings')
        ->atLeast()
        ->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $corePresets = [
        'default' => [],
        'red_black' => [],
    ];
    $themeMock->shouldReceive('getPresetsFromSettingsDataFile')
        ->atLeast()
        ->once()
        ->andReturn($corePresets);

    $di = container();

    $di['theme'] = $di->protect(fn (): \Mockery\MockInterface => $themeMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->getThemePresets($themeMock, 'dark_blue');
    expect($result)->toBeArray();

    $expected = [
        'default' => 'default',
        'red_black' => 'red_black',
    ];
    expect($result)->toBe($expected);
});

test('getThemePresets returns default when theme has no settings data file', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $themeMock->shouldReceive('getPresetsFromSettingsDataFile')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();

    $di['theme'] = $di->protect(fn (): \Mockery\MockInterface => $themeMock);
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getThemePresets($themeMock);
    expect($result)->toBeArray();

    $expected = [
        'Default' => 'Default',
    ];
    expect($result)->toBe($expected);
});

test('getThemeSettings returns theme settings', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $extensionMetaModel = new \Model_ExtensionMeta();
    $extensionMetaModel->loadBean(new \Tests\Helpers\DummyBean());
    $extensionMetaModel->meta_value = '{}';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn($extensionMetaModel);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = container();

    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getThemeSettings($themeMock, 'default');
    expect($result)->toBeArray();
});

test('getThemeSettings with empty presets returns empty array', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCurrentThemePreset')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(null);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');
    $themeMock->shouldReceive('getPresetFromSettingsDataFile')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();

    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->getThemeSettings($themeMock);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('updateSettings updates theme settings', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $extensionMetaModel = new \Model_ExtensionMeta();
    $extensionMetaModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $dbMock->shouldReceive('dispense')
        ->atLeast()
        ->once()
        ->andReturn($extensionMetaModel);
    $dbMock->shouldReceive('store')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = container();

    $di['db'] = $dbMock;

    $service->setDi($di);
    $params = [];
    $result = $service->updateSettings($themeMock, 'default', $params);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('regenerateThemeSettingsDataFile regenerates settings file', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $tmpDir = sys_get_temp_dir() . '/fb_test_' . uniqid();
    mkdir($tmpDir, 0o755, true);
    $testFile = $tmpDir . '/test_settings.json';

    $presets = [
        'default' => 'Defaults',
        'red_black' => 'Red Black',
    ];

    // Create theme mock
    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->andReturn('test_theme');
    $themeMock->shouldReceive('getPresetsFromSettingsDataFile')
        ->andReturn($presets);
    $themeMock->shouldReceive('getPathSettingsDataFile')
        ->andReturn($testFile);

    // Create service with mock filesystem injected via constructor
    $filesystemMock = Mockery::mock(\Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('dumpFile')
        ->atLeast()
        ->once();

    $serviceMock = Mockery::mock(Service::class, [$filesystemMock])->makePartial();
    $serviceMock->shouldReceive('getThemePresets')
        ->with($themeMock)
        ->andReturn($presets);
    $serviceMock->shouldReceive('getThemeSettings')
        ->with($themeMock, Mockery::any())
        ->andReturn([]);
    $serviceMock->shouldReceive('getCurrentThemePreset')
        ->with($themeMock)
        ->andReturn('default');

    $di = container();
    $serviceMock->setDi($di);

    $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();

    // Cleanup
    if (file_exists($testFile)) {
        unlink($testFile);
    }
    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }
});

test('regenerateThemeCssAndJsFiles handles empty files', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $themeMock = Mockery::mock(Model\Theme::class);

    $tmpDir = sys_get_temp_dir() . '/fb_test_assets_' . uniqid();
    mkdir($tmpDir, 0o755, true);

    $themeMock->shouldReceive('getPathAssets')
        ->atLeast()
        ->once()
        ->andReturn($tmpDir . '/');

    $di = container();
    $service->setDi($di);

    $result = $service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());

    // Clean up temp directory
    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getCurrentAdminAreaTheme returns theme configuration', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn('');

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);

    $result = $service->getCurrentAdminAreaTheme();
    expect($result)->toBeArray();
});

test('getCurrentClientAreaTheme returns Theme model', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $themeMock = Mockery::mock(Model\Theme::class);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock->shouldReceive('getCurrentClientAreaThemeCode')
        ->atLeast()
        ->once();

    $serviceMock->shouldReceive('getTheme')
        ->atLeast()
        ->once()
        ->andReturn($themeMock);

    $result = $serviceMock->getCurrentClientAreaTheme();
    expect($result)->toBeInstanceOf(Model\Theme::class);
});

test('getCurrentClientAreaThemeCode returns theme code', function (): void {
    $service = new \Box\Mod\Theme\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn('huraga');

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getCurrentClientAreaThemeCode();
    expect($result)->toBeString();
    expect($result)->toBe('huraga');
});
