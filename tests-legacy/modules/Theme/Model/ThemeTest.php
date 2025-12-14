<?php

declare(strict_types=1);

namespace Box\Mod\Theme\Model;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class ThemeTest extends \BBTestCase
{
    private ?string $existingTheme = 'huraga';

    public function testGetName(): void
    {
        $themeModel = new Theme($this->existingTheme);
        $this->assertEquals($this->existingTheme, $themeModel->getName());
    }

    public function testNotExistingTheme(): void
    {
        $themeName = 'not existing theme';
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("Theme '{$themeName}' does not exist.");
        new Theme($themeName);
    }

    public function testIsAdminAreaTheme(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAdminAreaTheme();
        $this->assertIsBool($result);
    }

    public function testIsAssetsPathWritable(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAssetsPathWritable();
        $this->assertIsBool($result);
    }

    public function testGetUploadedAssets(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUploadedAssets();
        $this->assertIsArray($result);
    }

    public function testGetSettingsPageHtml(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getSettingsPageHtml();
        $this->assertIsString($result);
    }

    public function testGetPresetsFromSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetsFromSettingsDataFile();
        $this->assertIsArray($result);
    }

    public function testGetCurrentPreset(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getCurrentPreset();
        $this->assertIsString($result);
    }

    public function testGetPresetFromSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetFromSettingsDataFile('default');
        $this->assertIsArray($result);
    }

    public function testGetUrl(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUrl();
        $this->assertIsString($result);
    }

    public function testGetPathConfig(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathConfig();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'config'));
    }

    public function testGetPathAssets(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathAssets();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'assets'));
    }

    public function testGetPathHtml(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathHtml();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'html'));
    }

    public function testGetPathSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathSettingsDataFile();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'settings_data.json'));
    }
}
