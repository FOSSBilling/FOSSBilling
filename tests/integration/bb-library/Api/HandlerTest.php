<?php
class Api_HandlerTest extends BBDbApiTestCase
{

    public function api_roles()
    {
        return array(
            array('api_guest'),
            array('api_admin'),
            array('api_client'),
        );
    }

    /**
     * @dataProvider api_roles
     */
    public function testInstances($apiName)
    {
        $api = $this->di[$apiName];
        $this->assertInstanceOf('Api_Handler', $api);
    }

    public function testCall()
    {
        $api = $this->di['api_guest'];

        $version = $api->system_version();
        $this->assertEquals('0.0.1', $version);
    }

    /**
     * @expectedException Box_Exception
     * @expectedExceptionCode 710
     */
    public function testException()
    {
        $api = $this->di['api_guest'];
        $method = 'methodWithoutUnderscore';
        $api->$method();
    }

    /**
     * @expectedException Box_Exception
     * @expectedExceptionCode 714
     */
    public function testInvalidModuleNameException()
    {
        $api = $this->di['api_guest'];
        $moduleName = '__';
        $version = $api->$moduleName();
    }

    /**
     * @expectedException Box_Exception
     * @expectedExceptionCode 715
     */
    public function testModuleNotActiveException()
    {
        $api = $this->di['api_guest'];
        $moduleName = 'notActiveModule_version';
        $version = $api->$moduleName();
    }
}