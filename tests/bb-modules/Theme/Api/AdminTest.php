<?php


namespace Box\Mod\Theme\Api;


class AdminTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Theme\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Theme\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testget_list()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getThemes')
            ->will($this->returnValue(array()));

        $this->api->setService($systemServiceMock);

        $result = $this->api->get_list(array());
        $this->assertIsArray($result);
    }

    public function testget()
    {
        $data = array(
            'code' => 'themeCode',
        );

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('loadTheme')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($systemServiceMock);

        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testselect_NotAdminTheme()
    {
        $data = array(
            'code' => 'pjw',
        );

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->will($this->returnValue(false));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->will($this->returnValue($themeMock));

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('theme'));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($systemServiceMock) { return $systemServiceMock;});
        $di['logger'] = $loggerMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);

    }

    public function testselect_AdminTheme()
    {
        $data = array(
            'code' => 'pjw',
        );

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->will($this->returnValue(true));

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->will($this->returnValue($themeMock));

        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('admin_theme'));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($systemServiceMock) { return $systemServiceMock;});
        $di['logger'] = $loggerMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);

    }

    public function testpreset_delete()
    {
        $data = array(
            'code' => 'themeCode',
            'preset' => 'themePreset',
        );

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->will($this->returnValue($themeMock));
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePreset');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->preset_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testpreset_select()
    {
        $data = array(
            'code' => 'themeCode',
            'preset' => 'themePreset',
        );

        $themeMock = $this->getMockBuilder('\Box\Mod\Theme\Model\Theme')->disableOriginalConstructor()->getMock();

        $serviceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->will($this->returnValue($themeMock));
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->preset_select($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
 