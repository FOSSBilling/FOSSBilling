<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

$existingTheme = 'huraga';

test('get name', function () use ($existingTheme) {
    $themeModel = new \Box\Mod\Theme\Model\Theme($existingTheme);
    expect($themeModel->getName())->toEqual($existingTheme);
});

test('not existing theme', function () {
    $themeName = 'not existing theme';
    expect(function () use ($themeName) {
        new \Box\Mod\Theme\Model\Theme($themeName);
    })->toThrow(\FOSSBilling\Exception::class, "Theme '{$themeName}' does not exist.");
});

test('is admin area theme', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->isAdminAreaTheme();
    expect($result)->toBeBool();
});

test('is assets path writable', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->isAssetsPathWritable();
    expect($result)->toBeBool();
});

test('get uploaded assets', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getUploadedAssets();
    expect($result)->toBeArray();
});

test('get settings page html', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getSettingsPageHtml();
    expect($result)->toBeString();
});

test('get presets from settings data file', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPresetsFromSettingsDataFile();
    expect($result)->toBeArray();
});

test('get current preset', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getCurrentPreset();
    expect($result)->toBeString();
});

test('get preset from settings data file', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPresetFromSettingsDataFile('default');
    expect($result)->toBeArray();
});

test('get url', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getUrl();
    expect($result)->toBeString();
});

test('get path config', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathConfig();
    expect($result)->toBeString();
    expect(str_contains($result, 'config'))->toBeTrue();
});

test('get path assets', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathAssets();
    expect($result)->toBeString();
    expect(str_contains($result, 'assets'))->toBeTrue();
});

test('get path html', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathHtml();
    expect($result)->toBeString();
    expect(str_contains($result, 'html'))->toBeTrue();
});

test('get path settings data file', function () use ($existingTheme) {
    $theme = new \Box\Mod\Theme\Model\Theme($existingTheme);
    $result = $theme->getPathSettingsDataFile();
    expect($result)->toBeString();
    expect(str_contains($result, 'settings_data.json'))->toBeTrue();
});
