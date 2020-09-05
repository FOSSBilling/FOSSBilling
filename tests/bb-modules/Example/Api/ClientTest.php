<?php


namespace Box\Mod\Example\Api;


class ClientTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Example\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Example\Api\Client();
    }

    public function testget_info()
    {
        $data = array('microsoft');

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \RedBeanPHP\OODBBean());

        $clientService = $this->getMockBuilder('\Box\Mod\Client\Service')->getMock();
        $clientService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->with($modelClient)
            ->willReturn(array());

        $systemService = $this->getMockBuilder('\Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('getVersion')
            ->willReturn(\Box_Version::VERSION);
        $systemService->expects($this->atLeastOnce())
            ->method('getMessages')
            ->willReturn(array());

        $di = new \Box_Di();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(function ($serviceName) use ($clientService, $systemService){
            if ($serviceName == 'Client'){
                return $clientService;
            }
            if ($serviceName == 'System'){
                return $systemService;
            }
            return -1;
        });
        $di['loggedin_client'] = $modelClient;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $result = $this->api->get_info($data);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('version', $result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertArrayHasKey('messages', $result);
    }
}
 