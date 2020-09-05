<?php
class HandlerTest extends BBDbApiTestCase
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

    public function testException()
    {
        $api = $this->di['api_guest'];
        $method = 'methodWithoutUnderscore';
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(710);
        $api->$method();
    }

    public function testInvalidModuleNameException()
    {
        $api = $this->di['api_guest'];
        $moduleName = '__';
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(714);
        $version = $api->$moduleName();
    }

    public function testModuleNotActiveException()
    {
        $api = $this->di['api_guest'];
        $moduleName = 'notActiveModule_version';
        $this->expectException(\Box_Exception::class);
        $this->expectExceptionCode(715);
        $version = $api->$moduleName();
    }
}