<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

$existingTheme = 'huraga';

test('get name', function () use ($existingTheme): void {
    $themeModel = new Box\Mod\Theme\Model\Theme($existingTheme);
    expect($themeModel->getName())->toEqual($existingTheme);
});

test('not existing theme', function (): void {
    $themeName = 'not existing theme';
    expect(function () use ($themeName): void {
        new Box\Mod\Theme\Model\Theme($themeName);
    })->toThrow(FOSSBilling\Core\Exception\BaseException::class, "Theme '{$themeName}' does not exist.");
});

test('is admin area theme', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->isAdminAreaTheme();
    expect($result)->toBeBool();
});

test('is assets path writable', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->isAssetsPathWritable();
    expect($result)->toBeBool();
});

test('get uploaded assets', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getUploadedAssets();
    expect($result)->toBeArray();
});

test('get settings page html', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getSettingsPageHtml();
    expect($result)->toBeString();
});

test('get presets from settings data file', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPresetsFromSettingsDataFile();
    expect($result)->toBeArray();
});

test('get current preset', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getCurrentPreset();
    expect($result)->toBeString();
});

test('get preset from settings data file', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPresetFromSettingsDataFile('default');
    expect($result)->toBeArray();
});

test('preset settings fall back to the shipped .example template when settings_data.json is missing', function () use ($existingTheme): void {
    // A dev checkout can have a real settings_data.json (e.g. from previously saving theme
    // settings locally), so move it out of the way for the duration of this test rather than
    // assuming it's absent.
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $filesystem = new Symfony\Component\Filesystem\Filesystem();
    $realFile = Symfony\Component\Filesystem\Path::join($theme->getPathConfig(), 'settings_data.json');
    $backupFile = $realFile . '.bak-' . bin2hex(random_bytes(4));
    $hadRealFile = $filesystem->exists($realFile);

    if ($hadRealFile) {
        $filesystem->rename($realFile, $backupFile);
    }

    try {
        $presets = $theme->getPresetsFromSettingsDataFile();
        expect($presets)->not->toBeEmpty()
            ->and($presets)->toHaveKey('Default');

        $default = $theme->getPresetFromSettingsDataFile('Default');
        expect($default)->toHaveKey('side_menu_dashboard');
    } finally {
        if ($hadRealFile) {
            $filesystem->rename($backupFile, $realFile);
        }
    }
});

test('get url', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getUrl();
    expect($result)->toBeString();
});

test('get path config', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathConfig();
    expect($result)->toBeString();
    expect(str_contains($result, 'config'))->toBeTrue();
});

test('get path assets', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathAssets();
    expect($result)->toBeString();
    expect(str_contains($result, 'assets'))->toBeTrue();
});

test('get path html', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathHtml();
    expect($result)->toBeString();
    expect(str_contains($result, 'html'))->toBeTrue();
});

test('get path settings data file', function () use ($existingTheme): void {
    $theme = new Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathSettingsDataFile();
    expect($result)->toBeString();
    expect(str_contains($result, 'settings_data.json'))->toBeTrue();
});
