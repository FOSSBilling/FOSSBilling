<?php


namespace Box\Mod\Theme\Controller;


class AdminTest extends \BBTestCase {

    public function testDi()
    {
        $controller = new \Box\Mod\Theme\Controller\Admin();

        $di = new \Box_Di();
        $db = $this->getMockBuilder('Box_Database')->getMock();

        $di['db'] = $db;
        $controller->setDi($di);
        $result = $controller->getDi();
        $this->assertEquals($di, $result);
    }

    public function testregister()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->exactly(1))
            ->method('get');

        $controller = new \Box\Mod\Theme\Controller\Admin();
        $controller->register($boxAppMock);
    }

    public function testget_theme()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('render')
            ->with('mod_theme_preset')
            ->willReturn('Rendering ...');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
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


        $themeServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
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

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock){
            if ($name == 'theme')
            {
                return $modMock;
            }
        });
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['is_admin_logged']  = true;

        $controller = new \Box\Mod\Theme\Controller\Admin();
        $controller->setDi($di);
        $controller->get_theme($boxAppMock, 'huraga');
    }

    public function testsave_theme_settings()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('redirect');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAssetsPathWritable')
            ->willReturn(true);

        $themeServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('updateSettings');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('uploadAssets');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('regenerateThemeCssAndJsFiles');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('regenerateThemeSettingsDataFile');


        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getService')
            ->willReturn($themeServiceMock);

	    $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
	    $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock){
            if ($name == 'theme')
            {
                return $modMock;
            }
        });
	    $di['events_manager'] = $eventMock;
        $adminModel = new \Model_Client();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());
        $di['api_admin'] = new \Api_Handler($adminModel);

        $controller = new \Box\Mod\Theme\Controller\Admin();
        $controller->setDi($di);

        $_POST['save-current-setting-preset'] = '{}';
        $_POST['save-current-setting'] = true;
        $controller->save_theme_settings($boxAppMock, 'huraga');
    }


    public function testsave_theme_settings_PathIsNotWritable()
    {
        $boxAppMock = $this->getMockBuilder('\Box_App')->disableOriginalConstructor()->getMock();
        $boxAppMock->expects($this->atLeastOnce())
            ->method('redirect');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAssetsPathWritable')
            ->willReturn(false);
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('PHPUnit Controlled exception');

        $themeServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('regenerateThemeSettingsDataFile')
            ->willThrowException(new \Exception('PHPUnit controoled exception'));


        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getService')
            ->willReturn($themeServiceMock);

	    $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
	    $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = new \Box_Di();
        $di['mod'] = $di->protect(function ($name) use($modMock){
            if ($name == 'theme')
            {
                return $modMock;
            }
        });
	    $di['events_manager'] = $eventMock;
        $adminModel = new \Model_Client();
        $adminModel->loadBean(new \RedBeanPHP\OODBBean());
        $di['api_admin'] = new \Api_Handler($adminModel);

        $controller = new \Box\Mod\Theme\Controller\Admin();
        $controller->setDi($di);

        $_POST['save-current-setting-preset'] = '{}';
        $_POST['save-current-setting'] = true;
        $controller->save_theme_settings($boxAppMock, 'huraga');
    }

}
 