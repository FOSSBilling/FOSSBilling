<?php

namespace Box\Mod\Theme\Model;

class ThemeTest extends \BBTestCase
{
    private ?string $existingTheme = 'huraga';

    public function testgetName(): void
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

    public function testisAdminAreaTheme(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAdminAreaTheme();
        $this->assertIsBool($result);
    }

    public function testisAssetsPathWritable(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAssetsPathWritable();
        $this->assertIsBool($result);
    }

    public function testgetUploadedAssets(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUploadedAssets();
        $this->assertIsArray($result);
    }

    public function testgetSettingsPageHtml(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getSettingsPageHtml();
        $this->assertIsString($result);
    }

    public function testgetPresetsFromSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetsFromSettingsDataFile();
        $this->assertIsArray($result);
    }

    public function testgetCurrentPreset(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getCurrentPreset();
        $this->assertIsString($result);
    }

    public function testgetPresetFromSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetFromSettingsDataFile('default');
        $this->assertIsArray($result);
    }

    public function testgetUrl(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUrl();
        $this->assertIsString($result);
    }

    public function testgetPathConfig(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathConfig();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'config'));
    }

    public function testgetPathAssets(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathAssets();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'assets'));
    }

    public function testgetPathHtml(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathHtml();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'html'));
    }

    public function testgetPathSettingsDataFile(): void
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathSettingsDataFile();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'settings_data.json'));
    }
}
