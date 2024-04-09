<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Theme_ServiceTest extends BBDbApiTestCase
{
    public function testgetCurrentThemePreset(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('huraga');
        $result = $service->getCurrentThemePreset($themeModel);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testgetThemePresets(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('huraga');
        $result = $service->getThemePresets($themeModel);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testgetThemeSettings(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);
        $themeModel = $service->getTheme('huraga');
        $result = $service->getThemeSettings($themeModel);
        $this->assertIsArray($result);
    }

    public function testupdateSettings(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('huraga');

        $preset = 'phpUnit';
        $params = [];

        $result = $service->updateSettings($themeModel, $preset, $params);
        $this->assertTrue($result);
    }

    public function testregenerateThemeSettingsDataFile(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('huraga');

        $result = $service->regenerateThemeSettingsDataFile($themeModel);
        $this->assertTrue($result);
    }

    public function testregenerateThemeCssAndJsFiles(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $themeModel = $service->getTheme('huraga');

        $preset = 'phpUnit';
        $result = $service->regenerateThemeCssAndJsFiles($themeModel, $preset, $this->di['api_admin']);
        $this->assertTrue($result);
    }

    public function testgetCurrentAdminAreaTheme(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $result = $service->getCurrentAdminAreaTheme();
        $this->assertIsArray($result);
        $this->assertEquals('admin_default', $result['code']);
        $this->assertEquals(SYSTEM_URL . 'themes/admin_default/', $result['url']);
    }

    public function testgetCurrentClientAreaTheme(): void
    {
        $service = new Box\Mod\Theme\Service();
        $service->setDi($this->di);

        $result = $service->getCurrentClientAreaTheme();
        $this->assertInstanceOf('\\' . Box\Mod\Theme\Model\Theme::class, $result);
        $this->assertEquals('huraga', $result->getName());
    }
}
