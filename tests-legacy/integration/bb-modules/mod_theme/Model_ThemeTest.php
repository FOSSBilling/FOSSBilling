<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Mode_ThemeTest extends PHPUnit\Framework\TestCase
{
    private FOSSBilling\Module\Theme\Model\Theme $model;

    public function setup(): void
    {
        $this->model = new FOSSBilling\Module\Theme\Model\Theme('boxbilling');
    }

    public function testTypes(): void
    {
        $theme1 = new FOSSBilling\Module\Theme\Model\Theme('boxbilling');
        $this->assertFalse($theme1->isAdminAreaTheme());

        $theme2 = new FOSSBilling\Module\Theme\Model\Theme('admin_default');
        $this->assertTrue($theme2->isAdminAreaTheme());
    }

    public function testFolder(): void
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
