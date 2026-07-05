<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Entity\ExtensionMeta;
use Box\Mod\Theme\Model;
use Box\Mod\Theme\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\injectMockFilesystem;

function themeContainerWithRepository(Mockery\MockInterface $repository, ?Mockery\MockInterface $em = null): Pimple\Container
{
    $di = container();
    $em ??= Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')
        ->byDefault()
        ->andReturn($repository);
    $di['em'] = $em;

    // Default to shouldIgnoreMissing() on the repository so that any method
    // call the production code makes on it that isn't explicitly stubbed
    // returns null instead of failing the test. This makes the test suite
    // more resilient to additions in the service layer and keeps the focus
    // on the behaviour each test is actually asserting.
    $repository->shouldIgnoreMissing();

    return $di;
}

test('getDi returns the dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    expect($service->getDi())->toBe($di);
});

test('getTheme returns a Theme model instance', function (): void {
    $service = new Service();
    $result = $service->getTheme('huraga');
    expect($result)->toBeInstanceOf(Model\Theme::class);
});

test('getCurrentThemePreset sets current theme preset when empty', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('setCurrentThemePreset')
        ->atLeast()
        ->once();

    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->with('mod_theme', 'default', 'preset', 'current')
        ->andReturn(null);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getCurrentPreset')
        ->atLeast()
        ->once()
        ->andReturn('CurrentPresetString');
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = themeContainerWithRepository($repositoryMock);
    $di['theme'] = $di->protect(fn (): Mockery\MockInterface => $themeMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->getCurrentThemePreset($themeMock);
    expect($result)->toBeString();
});

test('setCurrentThemePreset updates theme preset', function (): void {
    $service = new Service();
    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')
        ->atLeast()
        ->once()
        ->with(Mockery::type(ExtensionMeta::class));
    $emMock->shouldReceive('flush')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = themeContainerWithRepository($repositoryMock, $emMock);
    $di['theme'] = $di->protect(fn (): Mockery\MockInterface => $themeMock);

    $service->setDi($di);
    $result = $service->setCurrentThemePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletePreset removes a theme preset', function (): void {
    $service = new Service();
    $currentMetaMock = Mockery::mock(ExtensionMeta::class);
    $currentMetaMock->shouldReceive('getMetaValue')
        ->atLeast()
        ->once()
        ->andReturn('dark_blue');

    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn($currentMetaMock);
    $repositoryMock->shouldReceive('deleteByExtensionAndScope')
        ->twice()
        ->andReturn(1);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = themeContainerWithRepository($repositoryMock);
    $di['theme'] = $di->protect(fn (): Mockery\MockInterface => $themeMock);

    $service->setDi($di);
    $result = $service->deletePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getThemePresets returns available presets', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('updateSettings')
        ->atLeast()
        ->once();

    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn([]);

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

    $di = themeContainerWithRepository($repositoryMock);
    $di['theme'] = $di->protect(fn (): Mockery\MockInterface => $themeMock);

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
    $service = new Service();
    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $themeMock->shouldReceive('getPresetsFromSettingsDataFile')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = themeContainerWithRepository($repositoryMock);
    $di['theme'] = $di->protect(fn (): Mockery\MockInterface => $themeMock);
    $service->setDi($di);

    $result = $service->getThemePresets($themeMock);
    expect($result)->toBeArray();

    $expected = [
        'Default' => 'Default',
    ];
    expect($result)->toBe($expected);
});

test('getThemeSettings returns theme settings', function (): void {
    $service = new Service();
    $extensionMetaModel = (new ExtensionMeta())->setMetaValue('{}');

    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn($extensionMetaModel);

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = themeContainerWithRepository($repositoryMock);

    $service->setDi($di);
    $result = $service->getThemeSettings($themeMock, 'default');
    expect($result)->toBeArray();
});

test('getThemeSettings with empty presets returns empty array', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getCurrentThemePreset')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
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

    $di = themeContainerWithRepository($repositoryMock);
    $serviceMock->setDi($di);

    $result = $serviceMock->getThemeSettings($themeMock);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('updateSettings updates theme settings', function (): void {
    $service = new Service();
    $repositoryMock = Mockery::mock(Box\Mod\Extension\Repository\ExtensionMetaRepository::class);
    $repositoryMock->shouldReceive('findOneByExtensionAndScope')
        ->atLeast()
        ->once()
        ->andReturn(null);
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')
        ->atLeast()
        ->once()
        ->with(Mockery::type(ExtensionMeta::class));
    $emMock->shouldReceive('flush')
        ->atLeast()
        ->once();

    $themeMock = Mockery::mock(Model\Theme::class);
    $themeMock->shouldReceive('getName')
        ->atLeast()
        ->once()
        ->andReturn('default');

    $di = themeContainerWithRepository($repositoryMock, $emMock);

    $service->setDi($di);
    $params = [];
    $result = $service->updateSettings($themeMock, 'default', $params);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('regenerateThemeSettingsDataFile regenerates settings file', function (): void {
    $service = new Service();
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

    // Create service with mock filesystem injected into the readonly property.
    $filesystemMock = Mockery::mock(Symfony\Component\Filesystem\Filesystem::class);
    $filesystemMock->shouldReceive('dumpFile')
        ->atLeast()
        ->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
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
    injectMockFilesystem($serviceMock, $filesystemMock);

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
    $service = new Service();
    $themeMock = Mockery::mock(Model\Theme::class);

    $tmpDir = sys_get_temp_dir() . '/fb_test_assets_' . uniqid();
    mkdir($tmpDir, 0o755, true);

    $themeMock->shouldReceive('getPathAssets')
        ->atLeast()
        ->once()
        ->andReturn($tmpDir . '/');

    $di = container();
    $service->setDi($di);

    $result = $service->regenerateThemeCssAndJsFiles($themeMock, 'default');

    // Clean up temp directory
    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getCurrentAdminAreaTheme returns theme configuration', function (): void {
    Service::clearThemeCache();
    $service = new Service();
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
    $service = new Service();
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
    Service::clearThemeCache();
    $service = new Service();
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
