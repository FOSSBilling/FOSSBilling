<?php

/**
 * @group Core
 */
class Box_Mod_Api_ServiceTest extends BBDbApiTestCase
{
    protected $_mod = 'api';
    protected $_initialSeedFile = 'mod_api.xml';

    public function testClasses()
    {
        $service = $this->di['mod_service']('api');

        $api = $service->getApiGuest();
        $this->assertInstanceOf('Api_Handler', $api);

        $api = $service->getApiAdmin();
        $this->assertInstanceOf('Api_Handler', $api);

        $api = $service->getApiClient(1);
        $this->assertInstanceOf('Api_Handler', $api);

        $result = $service->getRequestCount('2001-05-06', '123.124.125.126');
        $this->assertInternalType('integer', $result);

        $result = $service->logRequest();
        $this->assertInternalType('integer', $result);
    }

}