<?php

class HandlerTest extends BBDbApiTestCase
{
    public static function api_roles()
    {
        return [
            ['api_guest'],
            ['api_admin'],
            ['api_client'],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('api_roles')]
    public function testInstances($apiName): void
    {
        $api = $this->di[$apiName];
        $this->assertInstanceOf('Api_Handler', $api);
    }

    public function testCall(): void
    {
        $api = $this->di['api_guest'];

        $version = $api->system_version();
        $this->assertEquals('0.0.1', $version);
    }

    public function testException(): void
    {
        $api = $this->di['api_guest'];
        $method = 'methodWithoutUnderscore';
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(710);
        $api->$method();
    }

    public function testInvalidModuleNameException(): void
    {
        $api = $this->di['api_guest'];
        $moduleName = '__';
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(714);
        $version = $api->$moduleName();
    }

    public function testModuleNotActiveException(): void
    {
        $api = $this->di['api_guest'];
        $moduleName = 'notActiveModule_version';
        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionCode(715);
        $version = $api->$moduleName();
    }
}
