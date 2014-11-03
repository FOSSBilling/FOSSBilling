<?php


namespace Box\Mod\Theme\Api;


class AdminTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var \Box\Mod\Theme\Api\Admin
     */
    protected $api = null;

    public function setup()
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
        $this->assertInternalType('array', $result);
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

        $this->api->setService($systemServiceMock);

        $result = $this->api->get($data);
        $this->assertInternalType('array', $result);
    }
    public function testgetMissingCodeParam()
    {
        $data = array();

        $this->setExpectedException('\Box_Exception', 'Theme code is missing');
        $this->api->get($data);
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
            ->method('updateParam')
            ->with($this->equalTo('theme'));

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($systemServiceMock) { return $systemServiceMock;});
        $di['logger'] = $loggerMock;

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
            ->method('updateParam')
            ->with($this->equalTo('admin_theme'));

        $loggerMock = $this->getMockBuilder('\Box_Log')->getMock();

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function () use($systemServiceMock) { return $systemServiceMock;});
        $di['logger'] = $loggerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);

    }

    public function testselectMissingCodeParam()
    {
        $data = array();

        $this->setExpectedException('\Box_Exception', 'Theme code is missing');
        $this->api->select($data);
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

        $this->api->setService($serviceMock);

        $result = $this->api->preset_delete($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function presetTestData()
    {
        return array(
            array('code', 'Theme code is missing'),
            array('preset', 'Theme preset name is missing'),
        );
    }

    /**
     * @dataProvider presetTestData
     */
    public function testpreset_deleteMissingParams($field, $exceptionMessage)
    {
        $data = array(
            'code' => 'themeCode',
            'preset' => 'themePreset',
        );
        unset ($data[ $field ]);

        $this->setExpectedException('\Box_Exception', $exceptionMessage);
        $this->api->preset_delete($data);
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

        $this->api->setService($serviceMock);

        $result = $this->api->preset_select($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    /**
     * @dataProvider presetTestData
     */
    public function testpreset_selectMissingParams($field, $exceptionMessage)
    {
        $data = array(
            'code' => 'themeCode',
            'preset' => 'themePreset',
        );
        unset ($data[ $field ]);

        $this->setExpectedException('\Box_Exception', $exceptionMessage);
        $this->api->preset_select($data);
    }


}
 