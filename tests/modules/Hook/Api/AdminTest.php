<?php


namespace Box\Mod\Hook\Api;


class AdminTest extends \BBTestCase {

    /**
     * @var \Box\Mod\Hook\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Hook\Api\Admin();
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
        $serviceMock = $this->getMockBuilder('\Box\Mod\Hook\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->will($this->returnValue(array('SqlString', array())));

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue(array()));

        $di = new \Box_Di();
        $di['pager'] = $paginatorMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get_list(array());
        $this->assertIsArray($result);
    }

    public function testcall()
    {
        $data['event'] = 'testEvent';

        $configMock = array('debug' => true);

        $logMock = $this->getMockBuilder('\Box_log')->getMock();

        $eventManager = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManager->expects($this->atLeastOnce())
            ->method('fire')
            ->will($this->returnValue(1));

        $di = new \Box_Di();
        $di['config'] = $configMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventManager;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });

        $this->api->setDi($di);
        $result = $this->api->call($data);
        $this->assertNotEmpty($result);
    }

    public function testcallMissingEventParam()
    {
        $data['event'] = null;

        $result = $this->api->call($data);
        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testbatch_connect()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Hook\Service')->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('batchConnect')
            ->will($this->returnValue(1));

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->batch_connect(array());
        $this->assertNotEmpty($result);
    }
}
 