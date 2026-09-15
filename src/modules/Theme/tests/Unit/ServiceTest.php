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
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\container;
use function Tests\Helpers\injectMockFilesystem;
use function Tests\Helpers\moduleService;

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
    $result = $service->getTheme('default/client');
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
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchOne')->once()
        ->with(Mockery::type('string'), ['param' => 'admin_theme'])
        ->andReturn(false);

    $di = container();
    $di['dbal'] = $dbalMock;

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
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchOne')->once()
        ->with("SELECT value FROM setting WHERE param = 'theme' ")
        ->andReturn('default/client');

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->getCurrentClientAreaThemeCode();
    expect($result)->toBeString();
    expect($result)->toBe('default/client');
});

test('getCurrentClientAreaThemeCode falls back when the setting is missing', function (): void {
    Service::clearThemeCache();
    $service = new Service();
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchOne')->once()
        ->with("SELECT value FROM setting WHERE param = 'theme' ")
        ->andReturn(false);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    expect($service->getCurrentClientAreaThemeCode())->toBe('default/client');
});

test('getPackageSharedHtmlPath resolves the shared/html sibling for a package-shaped code', function (): void {
    $service = new Service();

    $result = $service->getPackageSharedHtmlPath('default/admin');
    expect($result)->toBe(Path::join(PATH_THEMES, 'default', 'shared', 'html'));

    $result = $service->getPackageSharedHtmlPath('default/client');
    expect($result)->toBe(Path::join(PATH_THEMES, 'default', 'shared', 'html'));
});

test('getPackageSharedHtmlPath returns null for a flat, non-package code', function (): void {
    $service = new Service();

    expect($service->getPackageSharedHtmlPath('some-flat-theme'))->toBeNull();
});

// $layout: top-level dir name => list of relative subpaths to create as
// directories, e.g. ['html'] for a flat theme, ['admin/html', 'client/html']
// for a package. getThemes() enumerates a real filesystem path, so this
// builds a temp root independent of the production `default` package.
function makeFixtureThemesRoot(array $layout): string
{
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $root = Path::join(sys_get_temp_dir(), 'fossbilling-get-themes-test-' . bin2hex(random_bytes(8)));

    foreach ($layout as $name => $subpaths) {
        foreach ($subpaths as $subpath) {
            $filesystem->mkdir(Path::join($root, $name, $subpath));
        }
    }

    return $root;
}

// buildThemeConfig() (called per theme getThemes() lists) needs 'extension'
// mod_service to return a real array from getCoreAndActiveModules(), or
// array_unique() there would be handed null.
function themesRootDi(): Pimple\Container
{
    $extensionServiceMock = Mockery::mock()->shouldIgnoreMissing();
    $extensionServiceMock->shouldReceive('getCoreAndActiveModules')->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService(['extension' => $extensionServiceMock]));

    return $di;
}

test('getThemes lists a package with only an admin area in the admin bucket only', function (): void {
    $root = makeFixtureThemesRoot(['mypackage' => ['admin/html']]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getThemesPath')->andReturn($root);
    $serviceMock->setDi(themesRootDi());

    expect(array_column($serviceMock->getThemes(false), 'code'))->toBe(['mypackage/admin']);
    expect($serviceMock->getThemes(true))->toBe([]);

    (new Symfony\Component\Filesystem\Filesystem())->remove($root);
});

test('getThemes lists a package with only a client area in the client bucket only', function (): void {
    $root = makeFixtureThemesRoot(['mypackage' => ['client/html']]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getThemesPath')->andReturn($root);
    $serviceMock->setDi(themesRootDi());

    expect(array_column($serviceMock->getThemes(true), 'code'))->toBe(['mypackage/client']);
    expect($serviceMock->getThemes(false))->toBe([]);

    (new Symfony\Component\Filesystem\Filesystem())->remove($root);
});

test('getThemes lists a package with both areas in both buckets', function (): void {
    $root = makeFixtureThemesRoot(['mypackage' => ['admin/html', 'client/html']]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getThemesPath')->andReturn($root);
    $serviceMock->setDi(themesRootDi());

    expect(array_column($serviceMock->getThemes(false), 'code'))->toBe(['mypackage/admin']);
    expect(array_column($serviceMock->getThemes(true), 'code'))->toBe(['mypackage/client']);

    (new Symfony\Component\Filesystem\Filesystem())->remove($root);
});

test('getThemes still classifies a flat theme by its name, unaffected by the package shape', function (): void {
    $root = makeFixtureThemesRoot([
        'my-admin-theme' => ['html'],
        'my-client-theme' => ['html'],
    ]);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getThemesPath')->andReturn($root);
    $serviceMock->setDi(themesRootDi());

    expect(array_column($serviceMock->getThemes(false), 'code'))->toBe(['my-admin-theme']);
    expect(array_column($serviceMock->getThemes(true), 'code'))->toBe(['my-client-theme']);

    (new Symfony\Component\Filesystem\Filesystem())->remove($root);
});
