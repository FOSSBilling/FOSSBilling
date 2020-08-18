<?php


namespace Box\Mod\Theme;


class ServiceTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Theme\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Theme\Service();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetTheme()
    {
        $result = $this->service->getTheme('boxbilling');
        $this->assertInstanceOf('\Box\Mod\Theme\Model\Theme', $result);
    }

    public function testgetCurrentThemePreset()
    {

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('setCurrentThemePreset'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(array()));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getCurrentPreset')
            ->will($this->returnValue('CurrentPresetString'));

        $di = new \Box_Di();

        $di['theme'] = $di->protect(function () use($themeMock) { return $themeMock; });
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getCurrentThemePreset($themeMock);
        $this->assertIsString($result);
    }

    public function testsetCurrentThemePreset()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $di = new \Box_Di();

        $di['theme'] = $di->protect(function () use($themeMock) { return $themeMock; });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->setCurrentThemePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdeletePreset()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $di = new \Box_Di();

        $di['theme'] = $di->protect(function () use($themeMock) { return $themeMock; });
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->deletePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetThemePresets()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('updateSettings'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateSettings');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $corePresets = array(
            'default' => array(),
            'red_black' => array(),
        );
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->will($this->returnValue($corePresets));

        $di = new \Box_Di();

        $di['theme'] = $di->protect(function () use($themeMock) { return $themeMock; });
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getThemePresets($themeMock, 'dark_blue');
        $this->assertIsArray($result);

        $expected= array(
            'default' => 'default',
            'red_black' => 'red_black',
        );
        $this->assertEquals($expected, $result);
    }

    public function testgetThemePresets_themeDoNotHaveSettingsDataFile()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();

        $di['theme'] = $di->protect(function () use($themeMock) { return $themeMock; });
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getThemePresets($themeMock, 'dark_blue');
        $this->assertIsArray($result);

        $expected= array(
            'Default' => 'Default',
        );
        $this->assertEquals($expected, $result);
    }

    public function testgetThemeSettings()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \RedBeanPHP\OODBBean());
        $extensionMetaModel->meta_value = '{}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($extensionMetaModel));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $di = new \Box_Di();

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getThemeSettings($themeMock, 'default');
        $this->assertIsArray($result);
    }

    public function testgetThemeSettingsWithEmptyPresets()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('getCurrentThemePreset'))
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->will($this->returnValue('default'));

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetFromSettingsDataFile')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();

        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getThemeSettings($themeMock);
        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }

    public function testupdateSettings()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \RedBeanPHP\OODBBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(null));
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($extensionMetaModel));
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $di = new \Box_Di();

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $params = array();
        $result = $this->service->updateSettings($themeMock, 'default', $params);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testregenerateThemeSettingsDataFile()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('getThemePresets', 'getThemeSettings', 'getCurrentThemePreset'))
            ->getMock();

        $presets = array(
            'default' => 'Defaults',
            'red_black' => 'Red Black',
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemePresets')
            ->will($this->returnValue($presets));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemeSettings')
            ->will($this->returnValue(array()));

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->will($this->returnValue('default'));


        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathSettingsDataFile')
            ->will($this->returnValue('location/Of/Assets/file'));

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_put_contents');

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);

    }

    public function testregenerateThemeCssAndJsFiles_EmptyFiles()
    {
        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('glob')
            ->will($this->returnValue(array()));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets')
            ->will($this->returnValue('location/Of/'));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testregenerateThemeCssAndJsFiles()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('getThemeSettings'))
            ->getMock();

        $presets = array(
            'default' => 'Defaults',
            'red_black' => 'Red Black',
        );
        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemeSettings')
            ->will($this->returnValue($presets));

        $toolsMock = $this->getMockBuilder('\Box_Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('glob')
            ->will($this->onConsecutiveCalls(array('css.css'), array('js.js')));
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_put_contents');

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('renderString')
            ->will($this->returnValue('renderedString'));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets')
            ->will($this->returnValue('location/Of/'));

        $di = new \Box_Di();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(function() use($systemServiceMock) {return $systemServiceMock;});
        $serviceMock->setDi($di);

        $result = $serviceMock->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetCurrentAdminAreaTheme()
    {
        $configMock = array(
            'url' => 'boxbilling.com/'
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(''));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $di['config'] = $configMock;

        $this->service->setDi($di);

        $result =$this->service->getCurrentAdminAreaTheme();
        $this->assertIsArray($result);
    }

    public function testgetCurrentClientAreaTheme()
    {
        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')
            ->setMethods(array('getCurrentClientAreaThemeCode', 'getTheme'))
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode');

        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->will($this->returnValue($themeMock));

        $result = $serviceMock->getCurrentClientAreaTheme();
        $this->assertInstanceOf('\Box\Mod\Theme\Model\Theme', $result);
    }

    public function testgetCurrentClientAreaThemeCode()
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getCurrentClientAreaThemeCode();
        $this->assertIsString($result);
        $this->assertEquals('boxbilling', $result);
    }

    public function testuploadAssets()
    {
        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets');
        $files = array(
            'test2' => array(
                'error' => UPLOAD_ERR_NO_FILE
                ),
            'test1' => array(
                    'error' => UPLOAD_ERR_OK,
                    'tmp_name' => 'tempName',
                ),
        );
        $this->service->uploadAssets($themeMock, $files);
    }

    public function testuploadAssets_Exception()
    {
        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets');
        $files = array(
            'test0' => array(
                'error' => UPLOAD_ERR_CANT_WRITE
            ),
        );
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage(sprintf("Error uploading file %s Error code: %d", 'test0', UPLOAD_ERR_CANT_WRITE));
        $this->service->uploadAssets($themeMock, $files);
    }

}
 