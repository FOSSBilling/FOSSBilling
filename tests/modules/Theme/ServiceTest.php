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
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetTheme()
    {
        $result = $this->service->getTheme('huraga');
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

        $di = new \Pimple\Container();

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

        $di = new \Pimple\Container();

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

        $di = new \Pimple\Container();

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

        $di = new \Pimple\Container();

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

        $di = new \Pimple\Container();

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
        $extensionMetaModel->loadBean(new \DummyBean());
        $extensionMetaModel->meta_value = '{}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($extensionMetaModel));

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('default'));

        $di = new \Pimple\Container();

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

        $di = new \Pimple\Container();

        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getThemeSettings($themeMock);
        $this->assertIsArray($result);
        $this->assertEquals(array(), $result);
    }

    public function testupdateSettings()
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \DummyBean());

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

        $di = new \Pimple\Container();

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

        $toolsMock = $this->getMockBuilder('\FOSSBilling\Tools')->getMock();
        $toolsMock->expects($this->atLeastOnce())
            ->method('file_put_contents');

        $di = new \Pimple\Container();
        $di['tools'] = $toolsMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);

    }

    public function testregenerateThemeCssAndJsFiles_EmptyFiles()
    {
        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets')
            ->will($this->returnValue('location/Of/'));

        $di = new \Pimple\Container();
        $di['tools'] = $toolsMock;
        $this->service->setDi($di);

        $result = $this->service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetCurrentAdminAreaTheme()
    {
        $configMock = array(
            'url' => 'fossbilling.org/'
        );
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(''));

        $di = new \Pimple\Container();
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

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getCurrentClientAreaThemeCode();
        $this->assertIsString($result);
        $this->assertEquals('huraga', $result);
    }


}
