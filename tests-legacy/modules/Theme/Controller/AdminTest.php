<?php

namespace Box\Mod\Theme\Controller;

class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new Admin();

        $di = new \Pimple\Container();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testregister(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(1))
            ->method('get');

        $controller = new Admin();
        $controller->register($boxAppMock);
    }

    public function testgetTheme(): void
    {
        $boxAppMock = $this->getMockBuilder('\FOSSBilling\App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_theme_preset')
            ->willReturn('Rendering ...');

        $themeMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getSettingsPageHtml')
            ->willReturn('');
        $themeMock->expects($this->atLeastOnce())
            ->method('getName');
        $themeMock->expects($this->atLeastOnce())
            ->method('getUploadedAssets');
        $themeMock->expects($this->atLeastOnce())
            ->method('getSnippets');
        $themeMock->expects($this->atLeastOnce())
            ->method('isAssetsPathWritable')
            ->willReturn(false);

        $themeServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getThemeSettings');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getThemePresets');

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getService')
            ->willReturn($themeServiceMock);

        $di = new \Pimple\Container();
        $di['mod'] = $di->protect(function ($name) use ($modMock) {
            if ($name == 'theme') {
                return $modMock;
            }
        });

        $di['is_admin_logged'] = true;

        $controller = new Admin();
        $controller->setDi($di);
        $controller->get_theme($boxAppMock, 'huraga');
    }
}
