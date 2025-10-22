<?php

namespace Box\Mod\Theme;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetTheme(): void
    {
        $result = $this->service->getTheme('huraga');
        $this->assertInstanceOf('\\' . Model\Theme::class, $result);
    }

    public function testgetCurrentThemePreset(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['setCurrentThemePreset'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn([]);

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getCurrentPreset')
            ->willReturn('CurrentPresetString');

        $di = new \Pimple\Container();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getCurrentThemePreset($themeMock);
        $this->assertIsString($result);
    }

    public function testsetCurrentThemePreset(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = new \Pimple\Container();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->setCurrentThemePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdeletePreset(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = new \Pimple\Container();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->deletePreset($themeMock, 'dark_blue');
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetThemePresets(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['updateSettings'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateSettings');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $corePresets = [
            'default' => [],
            'red_black' => [],
        ];
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->willReturn($corePresets);

        $di = new \Pimple\Container();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['db'] = $dbMock;

        $serviceMock->setDi($di);
        $result = $serviceMock->getThemePresets($themeMock, 'dark_blue');
        $this->assertIsArray($result);

        $expected = [
            'default' => 'default',
            'red_black' => 'red_black',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetThemePresetsThemeDoNotHaveSettingsDataFile(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetsFromSettingsDataFile')
            ->willReturn([]);

        $di = new \Pimple\Container();

        $di['theme'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $themeMock);
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getThemePresets($themeMock);
        $this->assertIsArray($result);

        $expected = [
            'Default' => 'Default',
        ];
        $this->assertEquals($expected, $result);
    }

    public function testgetThemeSettings(): void
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \DummyBean());
        $extensionMetaModel->meta_value = '{}';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($extensionMetaModel);

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = new \Pimple\Container();

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getThemeSettings($themeMock, 'default');
        $this->assertIsArray($result);
    }

    public function testgetThemeSettingsWithEmptyPresets(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getCurrentThemePreset'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');
        $themeMock->expects($this->atLeastOnce())
            ->method('getPresetFromSettingsDataFile')
            ->willReturn([]);

        $di = new \Pimple\Container();

        $di['db'] = $dbMock;
        $serviceMock->setDi($di);

        $result = $serviceMock->getThemeSettings($themeMock);
        $this->assertIsArray($result);
        $this->assertEquals([], $result);
    }

    public function testupdateSettings(): void
    {
        $extensionMetaModel = new \Model_ExtensionMeta();
        $extensionMetaModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(null);
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($extensionMetaModel);
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('default');

        $di = new \Pimple\Container();

        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $params = [];
        $result = $this->service->updateSettings($themeMock, 'default', $params);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testregenerateThemeSettingsDataFile(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getThemePresets', 'getThemeSettings', 'getCurrentThemePreset'])
            ->getMock();

        $presets = [
            'default' => 'Defaults',
            'red_black' => 'Red Black',
        ];
        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemePresets')
            ->willReturn($presets);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getThemeSettings')
            ->willReturn([]);

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentThemePreset')
            ->willReturn('default');

        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathSettingsDataFile')
            ->willReturn('location/Of/Assets/file');

        $di = new \Pimple\Container();

        $serviceMock->setDi($di);
        $result = $serviceMock->regenerateThemeSettingsDataFile($themeMock);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testregenerateThemeCssAndJsFilesEmptyFiles(): void
    {
        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('getPathAssets')
            ->willReturn('location/Of/');

        $di = new \Pimple\Container();
        $this->service->setDi($di);

        $result = $this->service->regenerateThemeCssAndJsFiles($themeMock, 'default', new \Model_Admin());
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetCurrentAdminAreaTheme(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn('');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);

        $result = $this->service->getCurrentAdminAreaTheme();
        $this->assertIsArray($result);
    }

    public function testgetCurrentClientAreaTheme(): void
    {
        $themeMock = $this->getMockBuilder('\\' . Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getCurrentClientAreaThemeCode', 'getTheme'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getCurrentClientAreaThemeCode');

        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $result = $serviceMock->getCurrentClientAreaTheme();
        $this->assertInstanceOf('\\' . Model\Theme::class, $result);
    }

    public function testgetCurrentClientAreaThemeCode(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn('');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getCurrentClientAreaThemeCode();
        $this->assertIsString($result);
        $this->assertEquals('huraga', $result);
    }
}
