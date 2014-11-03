<?php


namespace Box\Mod\Theme\Model;


class ThemeTest extends \PHPUnit_Framework_TestCase {

    private $existingTheme = 'huraga';

    public function testgetName()
    {
        $themeModel = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $this->assertEquals($this->existingTheme, $themeModel->getName());
    }

    public function testNotExistingTheme()
    {
        $themeName = 'not existing theme';
        $this->setExpectedException('\Box_Exception', sprintf('Theme "%s" does not exists', $themeName));
        new \Box\Mod\Theme\Model\Theme($themeName);
    }

    public function testisAdminAreaTheme()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->isAdminAreaTheme();
        $this->assertInternalType('bool', $result);
    }

    public function testisAssetsPathWritable()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->isAssetsPathWritable();
        $this->assertInternalType('bool', $result);
    }

    public function testgetSnippets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getSnippets();
        $this->assertInternalType('array', $result);
    }

    public function testgetUploadedAssets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getUploadedAssets();
        $this->assertInternalType('array', $result);
    }

    public function testgetSettingsPageHtml()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getSettingsPageHtml();
        $this->assertInternalType('string', $result);
    }

    public function testgetPresetsFromSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPresetsFromSettingsDataFile();
        $this->assertInternalType('array', $result);
    }

    public function testgetCurrentPreset()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getCurrentPreset();
        $this->assertInternalType('string', $result);
    }

    public function testgetPresetFromSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPresetFromSettingsDataFile('default');
        $this->assertInternalType('array', $result);
    }

    public function testgetUrl()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getUrl();
        $this->assertInternalType('string', $result);
    }

    public function testgetPathConfig()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathConfig();
        $this->assertInternalType('string', $result);
        $this->assertTrue(strpos($result, 'config') !== false);
    }

    public function testgetPathAssets()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathAssets();
        $this->assertInternalType('string', $result);
        $this->assertTrue(strpos($result, 'assets') !== false);
    }

    public function testgetPathHtml()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathHtml();
        $this->assertInternalType('string', $result);
        $this->assertTrue(strpos($result, 'html') !== false);
    }

    public function testgetPathSettingsDataFile()
    {
        $theme = new \Box\Mod\Theme\Model\Theme($this->existingTheme);
        $result = $theme->getPathSettingsDataFile();
        $this->assertInternalType('string', $result);
        $this->assertTrue(strpos($result, 'settings_data.json') !== false);
    }

}
 