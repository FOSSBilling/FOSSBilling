<?php


namespace Box\Mod\System\Api;


class AdminTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var \Box\Mod\System\Api\Admin
     */
    protected $api = null;

    public function setup()
    {
        $this->api= new \Box\Mod\System\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testlicense_info()
    {
        $data = array(

        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getLicenseInfo')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->license_info($data);
        $this->assertInternalType('array', $result);
    }

    public function testparam()
    {
        $data = array(
            'key' => 'key_parameter',
        );
        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParamValue')
            ->will($this->returnValue('paramValue'));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di = new \Box_Di();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->param($data);
        $this->assertInternalType('string', $result);
    }

    public function testget_params()
    {
        $data = array(
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getParams')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->get_params($data);
        $this->assertInternalType('array', $result);
    }

    public function testupdate_params()
    {
        $data = array(
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateParams')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->update_params($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function testmessages()
    {
        $data = array(
        );

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getMessages')
            ->will($this->returnValue(array()));

        $this->api->setService($serviceMock);

        $result = $this->api->messages($data);
        $this->assertInternalType('array', $result);
    }

    public function testtemplate_exists()
    {
        $data = array(
            'file' => 'testing.txt',
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('templateExists')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->template_exists($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function teststring_render()
    {
        $data = array(
            '_tpl' => 'default'
        );

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('renderString')
            ->will($this->returnValue('returnStringType'));
        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->string_render($data);
        $this->assertInternalType('string', $result);
    }

    public function testenv()
    {
        $data = array();

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getEnv')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->env($data);
        $this->assertInternalType('array', $result);
    }

    public function testis_allowed()
    {
        $data = array(
            'mod' => 'extension',
        );

        $staffServiceMock = $this->getMockBuilder('\Box\Mod\Staff\Service')->getMock();
        $staffServiceMock->expects($this->atLeastOnce())
            ->method('hasPermission')
            ->will($this->returnValue(true));

        $validatorMock = $this->getMockBuilder('\Box_Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));

        $di                = new \Box_Di();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });
        $di['array_get']   = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);

        $result = $this->api->is_allowed($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }

    public function testclear_cache()
    {
        $data = array();

        $serviceMock = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('clearCache')
            ->will($this->returnValue(true));

        $this->api->setService($serviceMock);

        $result = $this->api->clear_cache($data);
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
    }





}
 