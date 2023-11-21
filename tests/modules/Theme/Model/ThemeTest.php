<?php


namespace Box\Mod\Theme\Model;


class ThemeTest extends \BBTestCase {

    private ?string $existingTheme = 'huraga';

    public function testgetName()
    {
        $themeModel = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $this->assertEquals($this->existingTheme, $themeModel->getName());
    }

    public function testNotExistingTheme()
    {
        $themeName = 'not existing theme';
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Theme "%s" does not exist', $themeName));
        new \Box\Mod\Theme\Model\Theme($themeName);
    }

    public function testisAdminAreaTheme()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->isAdminAreaTheme();
        $this->assertIsBool($result);
    }

    public function testisAssetsPathWritable()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->isAssetsPathWritable();
        $this->assertIsBool($result);
    }

    public function testgetSnippets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getSnippets();
        $this->assertIsArray($result);
    }

    public function testgetUploadedAssets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getUploadedAssets();
        $this->assertIsArray($result);
    }

    public function testgetSettingsPageHtml()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getSettingsPageHtml();
        $this->assertIsString($result);
    }

    public function testgetPresetsFromSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPresetsFromSettingsDataFile();
        $this->assertIsArray($result);
    }

    public function testgetCurrentPreset()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getCurrentPreset();
        $this->assertIsString($result);
    }

    public function testgetPresetFromSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPresetFromSettingsDataFile('default');
        $this->assertIsArray($result);
    }

    public function testgetUrl()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getUrl();
        $this->assertIsString($result);
    }

    public function testgetPathConfig()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathConfig();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'config'));
    }

    public function testgetPathAssets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathAssets();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'assets'));
    }

    public function testgetPathHtml()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathHtml();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'html'));
    }

    public function testgetPathSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathSettingsDataFile();
        $this->assertIsString($result);
        $this->assertTrue(str_contains($result, 'settings_data.json'));
    }

}
 