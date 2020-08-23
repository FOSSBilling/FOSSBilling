<?php


namespace Box\Mod\Extension\Api;


class GuestTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Extension\Service
     */
    protected $service = null;

    /**
     * @var \Box\Mod\Extension\Api\Guest
     */
    protected $api = null;


    public function setup(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
        $this->api = new \Box\Mod\Extension\Api\Guest();
    }


    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function is_onProvider()
    {
        return array(
            array(
                array('mod' => 'testModule'),
                array(),
            ),
            array(
                array(
                    'id' => 'extensionId',
                    'type' => 'extensionType',
                ),
                array()
            ),
        );
    }

    /**
     * @dataProvider is_onProvider
     */
    public function testis_on($data, $expected)
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $result = $this->api->is_on($data);
        $this->assertEquals($expected, $result);
    }

    public function testis_onInvalidDataArray()
    {
        $data = array();
        $result = $this->api->is_on($data);
        $this->assertTrue($result);
    }

    public function testtheme()
    {
        $themeServiceMock = $this->getMockBuilder('\Box\Mod\Theme\Service')->getMock();
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getThemeConfig')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use($themeServiceMock) { return $themeServiceMock;});

        $this->api->setDi($di);
        $result = $this->api->theme();
        $this->assertIsArray($result);
    }

    public function testsettings()
    {
        $data['ext'] = 'testExtension';

        $serviceMock = $this->getMockBuilder('\Box\Mod\Extension\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);
        $result = $this->api->settings($data);
        $this->assertIsArray($result);
    }

    public function testsettingsExtExtension()
    {
        $data = array();

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Parameter ext is missing');
        $this->api->settings($data);

    }

    public function testlanguages()
    {
        $systemServiceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getLanguages')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($name) use($systemServiceMock) { return $systemServiceMock;});

        $this->api->setDi($di);
        $result = $this->api->languages();
        $this->assertIsArray($result);
    }
}
 