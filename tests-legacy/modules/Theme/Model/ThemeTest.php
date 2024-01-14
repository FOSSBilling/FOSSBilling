<?php

namespace Box\Mod\Theme\Model;

class ThemeTest extends \BBTestCase
{
    private ?string $existingTheme = 'huraga';

    public function testgetName()
    {
        $themeModel = new Theme($this->existingTheme);
        $this->assertEquals($this->existingTheme, $themeModel->getName());
    }

    public function testNotExistingTheme()
    {
        $themeName = 'not existing theme';
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Theme "%s" does not exist', $themeName));
        new Theme($themeName);
    }

    public function testisAdminAreaTheme()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAdminAreaTheme();
        $this->assertIsBool($result);
    }

    public function testisAssetsPathWritable()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->isAssetsPathWritable();
        $this->assertIsBool($result);
    }

    public function testgetSnippets()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getSnippets();
        $this->assertIsArray($result);
    }

    public function testgetUploadedAssets()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUploadedAssets();
        $this->assertIsArray($result);
    }

    public function testgetSettingsPageHtml()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getSettingsPageHtml();
        $this->assertIsString($result);
    }

    public function testgetPresetsFromSettingsDataFile()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetsFromSettingsDataFile();
        $this->assertIsArray($result);
    }

    public function testgetCurrentPreset()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getCurrentPreset();
        $this->assertIsString($result);
    }

    public function testgetPresetFromSettingsDataFile()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPresetFromSettingsDataFile('default');
        $this->assertIsArray($result);
    }

    public function testgetUrl()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getUrl();
        $this->assertIsString($result);
    }

    public function testgetPathConfig()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathConfig();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'config'));
    }

    public function testgetPathAssets()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathAssets();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'assets'));
    }

    public function testgetPathHtml()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathHtml();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'html'));
    }

    public function testgetPathSettingsDataFile()
    {
        $theme = new Theme($this->existingTheme);
        $result = $theme->getPathSettingsDataFile();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'settings_data.json'));
    }
}
