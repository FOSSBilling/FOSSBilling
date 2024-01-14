<?php

namespace Box\Mod\Hook\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Hook\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['SqlString', []]);

        $paginatorMock = $this->getMockBuilder('\Box_Pagination')->disableOriginalConstructor()->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['pager'] = $paginatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testcall()
    {
        $data['event'] = 'testEvent';

        $configMock = ['debug_and_monitoring' => ['debug' => true]];

        $logMock = $this->getMockBuilder('\Box_log')->getMock();

        $eventManager = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventManager->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(1);

        $di = new \Pimple\Container();
        $di['config'] = $configMock;
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $eventManager;

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

    public function testbatchConnect()
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Hook\Service::class)->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('batchConnect')
            ->willReturn(1);

        $di = new \Pimple\Container();

        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->batch_connect([]);
        $this->assertNotEmpty($result);
    }
}
