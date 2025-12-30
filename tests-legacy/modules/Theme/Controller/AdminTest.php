<?php

declare(strict_types=1);

namespace Box\Mod\Theme\Controller;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testDi(): void
    {
        $controller = new Admin();

        $di = $this->getDi();
        $db = $this->createMock('Box_Database');

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testRegister(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(1))
            ->method('get');

        $controller = new Admin();
        $controller->register($boxAppMock);
    }

    public function testGetTheme(): void
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_theme_preset')
            ->willReturn('Rendering ...');

        $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getSettingsPageHtml')
            ->willReturn('');
        $themeMock->expects($this->atLeastOnce())
            ->method('getName');
        $themeMock->expects($this->atLeastOnce())
            ->method('getUploadedAssets');
        $themeMock->expects($this->atLeastOnce())
            ->method('isAssetsPathWritable')
            ->willReturn(false);

        $themeServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
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

        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getService')
            ->willReturn($themeServiceMock);

        $di = $this->getDi();
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
