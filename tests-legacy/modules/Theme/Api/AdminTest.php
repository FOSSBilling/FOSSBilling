<?php

namespace Box\Mod\Theme\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList(): void
    {
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getThemes')
            ->willReturn([]);

        $this->api->setService($systemServiceMock);

        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testget(): void
    {
        $data = [
            'code' => 'themeCode',
        ];

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('loadTheme')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($systemServiceMock);

        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testselectNotAdminTheme(): void
    {
        $data = [
            'code' => 'pjw',
        ];

        $themeMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->willReturn(false);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('theme'));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = $loggerMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);
    }

    public function testselectAdminTheme(): void
    {
        $data = [
            'code' => 'pjw',
        ];

        $themeMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('admin_theme'));

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = $loggerMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);
    }

    public function testpresetDelete(): void
    {
        $data = [
            'code' => 'themeCode',
            'preset' => 'themePreset',
        ];

        $themeMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePreset');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->preset_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpresetSelect(): void
    {
        $data = [
            'code' => 'themeCode',
            'preset' => 'themePreset',
        ];

        $themeMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->preset_select($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
