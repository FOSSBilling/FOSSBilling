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

beforeEach(function (): void {
    $this->service = new Service();
});

test('getDi returns the dependency injection container', function (): void {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('getTheme returns a Theme model instance', function (): void {
    $result = $this->service->getTheme('huraga');
    expect($result)->toBeInstanceOf(Model\Theme::class);
});

test('getCurrentThemePreset sets current theme preset when empty', function (): void {
    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['setCurrentThemePreset'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('setCurrentThemePreset');

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getCell')
        ->willReturn([]);

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getCurrentPreset')
        ->willReturn('CurrentPresetString');

    $di = container();

    $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
    $di['db'] = $dbMock;

    $serviceMock->setDi($di);
    $result = $serviceMock->getCurrentThemePreset($themeMock);
    expect($result)->toBeString();
});

test('setCurrentThemePreset updates theme preset', function (): void {
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('exec');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $di = container();

    $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->setCurrentThemePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('deletePreset removes a theme preset', function (): void {
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('exec');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $di = container();

    $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->deletePreset($themeMock, 'dark_blue');
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getThemePresets returns available presets', function (): void {
    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['updateSettings'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('updateSettings');

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getAssoc');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $corePresets = [
        'default' => [],
        'red_black' => [],
    ];
    $themeMock->expects($this->atLeastOnce())
        ->method('getPresetsFromSettingsDataFile')
        ->willReturn($corePresets);

    $di = container();

    $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
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
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getAssoc');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $themeMock->expects($this->atLeastOnce())
        ->method('getPresetsFromSettingsDataFile')
        ->willReturn([]);

    $di = container();

    $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getThemePresets($themeMock);
    expect($result)->toBeArray();

    $expected = [
        'Default' => 'Default',
    ];
    expect($result)->toBe($expected);
});

test('getThemeSettings returns theme settings', function (): void {
    $extensionMetaModel = new \Model_ExtensionMeta();
    $extensionMetaModel->loadBean(new \Tests\Helpers\DummyBean());
    $extensionMetaModel->meta_value = '{}';

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('findOne')
        ->willReturn($extensionMetaModel);

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $di = container();

    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getThemeSettings($themeMock, 'default');
    expect($result)->toBeArray();
});

test('getThemeSettings with empty presets returns empty array', function (): void {
    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getCurrentThemePreset'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('getCurrentThemePreset')
        ->willReturn('default');

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('findOne')
        ->willReturn(null);

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');
    $themeMock->expects($this->atLeastOnce())
        ->method('getPresetFromSettingsDataFile')
        ->willReturn([]);

    $di = container();

    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $result = $serviceMock->getThemeSettings($themeMock);
    expect($result)->toBeArray();
    expect($result)->toBe([]);
});

test('updateSettings updates theme settings', function (): void {
    $extensionMetaModel = new \Model_ExtensionMeta();
    $extensionMetaModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('findOne')
        ->willReturn(null);
    $dbMock->expects($this->atLeastOnce())
        ->method('dispense')
        ->willReturn($extensionMetaModel);
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $themeMock->expects($this->atLeastOnce())
        ->method('getName')
        ->willReturn('default');

    $di = container();

    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $params = [];
    $result = $this->service->updateSettings($themeMock, 'default', $params);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('regenerateThemeSettingsDataFile regenerates settings file', function (): void {
    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getThemePresets', 'getThemeSettings', 'getCurrentThemePreset'])
        ->getMock();

    $presets = [
        'default' => 'Defaults',
        'red_black' => 'Red Black',
    ];
    $serviceMock->expects($this->atLeastOnce())
        ->method('getThemePresets')
        ->willReturn($presets);

    $serviceMock->expects($this->atLeastOnce())
        ->method('getThemeSettings')
        ->willReturn([]);

    $serviceMock->expects($this->atLeastOnce())
        ->method('getCurrentThemePreset')
        ->willReturn('default');

    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();
    $tmpDir = sys_get_temp_dir() . '/fb_test_' . uniqid();
    mkdir($tmpDir, 0o755, true);
    $testFile = $tmpDir . '/test_settings.json';

    $themeMock->expects($this->atLeastOnce())
        ->method('getPathSettingsDataFile')
        ->willReturn($testFile);

    $di = container();

    $serviceMock->setDi($di);
    $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);

    // Clean up temp file
    if (file_exists($testFile)) {
        unlink($testFile);
    }
    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('regenerateThemeCssAndJsFiles handles empty files', function (): void {
    $themeMock = $this->getMockBuilder(Model\Theme::class)->disableOriginalConstructor()->getMock();

    $tmpDir = sys_get_temp_dir() . '/fb_test_assets_' . uniqid();
    mkdir($tmpDir, 0o755, true);

    $themeMock->expects($this->atLeastOnce())
        ->method('getPathAssets')
        ->willReturn($tmpDir . '/');

    $di = container();
    $this->service->setDi($di);

    $result = $this->service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());

    // Clean up temp directory
    if (is_dir($tmpDir)) {
        rmdir($tmpDir);
    }

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('getCurrentAdminAreaTheme returns theme configuration', function (): void {
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getCell')
        ->willReturn('');

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);

    $result = $this->service->getCurrentAdminAreaTheme();
    expect($result)->toBeArray();
});

test('getCurrentClientAreaTheme returns Theme model', function (): void {
    $themeStub = $this->createStub(Model\Theme::class);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getCurrentClientAreaThemeCode', 'getTheme'])
        ->getMock();

    $serviceMock->expects($this->atLeastOnce())
        ->method('getCurrentClientAreaThemeCode');

    $serviceMock->expects($this->atLeastOnce())
        ->method('getTheme')
        ->willReturn($themeStub);

    $result = $serviceMock->getCurrentClientAreaTheme();
    expect($result)->toBeInstanceOf(Model\Theme::class);
});

test('getCurrentClientAreaThemeCode returns theme code', function (): void {
    $dbStub = $this->createStub('\Box_Database');

    $di = container();
    $di['db'] = $dbStub;
    $this->service->setDi($di);

    $result = $this->service->getCurrentClientAreaThemeCode();
    expect($result)->toBeString();
    expect($result)->toBe('huraga');
});
