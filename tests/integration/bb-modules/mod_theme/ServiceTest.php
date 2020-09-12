<?php
/**
 * @group Core
 */
class Box_Mod_Theme_ServiceTest extends BBDbApiTestCase
{

    public function testgetCurrentThemePreset()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('boxbilling');
        $result = $service->getCurrentThemePreset($themeModel);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testgetThemePresets()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('boxbilling');
        $result = $service->getThemePresets($themeModel);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testgetThemeSettings()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('boxbilling');
        $result = $service->getThemeSettings($themeModel);
        $this->assertIsArray($result);
    }

    public function testuploadAssets()
    {
        $files = array(
            'file1' => array(
                    'error' => UPLOAD_ERR_NO_FILE,
                ),
            'file2' => array(
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => 'tmpName',
            ),

        );

        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('boxbilling');
        $service->uploadAssets($themeModel, $files);
    }

    public function testupdateSettings()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('boxbilling');

        $preset = 'phpUnit';
        $params = array();

        $result = $service->updateSettings($themeModel, $preset, $params);
        $this->assertTrue($result);
    }

    public function testregenerateThemeSettingsDataFile()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('boxbilling');

        $result = $service->regenerateThemeSettingsDataFile($themeModel);
        $this->assertTrue($result);
    }

    public function testregenerateThemeCssAndJsFiles()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('boxbilling');

        $preset = 'phpUnit';
        $result = $service->regenerateThemeCssAndJsFiles($themeModel, $preset, $this->di['api_admin']);
        $this->assertTrue($result);
    }

    public function testgetCurrentAdminAreaTheme()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $result = $service->getCurrentAdminAreaTheme();
        $this->assertIsArray($result);
        $this->assertEquals('admin_default', $result['code']);
        $this->assertEquals($this->di['config']['url'].'bb-themes/admin_default/', $result['url']);
    }

    public function testgetCurrentClientAreaTheme()
    {
        $service = new \Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $result = $service->getCurrentClientAreaTheme();
        $this->assertInstanceOf('\Box\Mod\Theme\Model\Theme', $result);
        $this->assertEquals('huraga', $result->getName());
    }
}