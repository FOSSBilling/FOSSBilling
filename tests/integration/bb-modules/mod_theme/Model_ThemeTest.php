<?php
/**
 * @group Core
 */
class Mode_ThemeTest extends PHPUnit_Framework_TestCase
{
    private $model;

    public function setup()
    {
        $this->model = new \Box\Mod\Theme\Model\Theme('boxbilling');
    }

    public function testTypes()
    {
        $theme1 = new \Box\Mod\Theme\Model\Theme('boxbilling');
        $this->assertFalse($theme1->isAdminAreaTheme());

        $theme2 = new \Box\Mod\Theme\Model\Theme('admin_default');
        $this->assertTrue($theme2->isAdminAreaTheme());
    }

    public function testFolder()
    {
        $this->model->isAssetsPathWritable();
        $this->model->getUrl();
        $this->model->getPath();
        $this->model->getPathConfig();
        $this->model->getPathAssets();

        $page = $this->model->getSettingsPageHtml();
        $this->assertContains('input', $page);

        $files = $this->model->getUploadedAssets();
    }
}