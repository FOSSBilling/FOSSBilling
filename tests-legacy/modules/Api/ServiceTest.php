<?php

namespace Box\Mod\Api;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetRequestCount(): void
    {
        $since = 674_690_401; // timestamp == '1991-05-20 00:00:01';
        $ip = '1.2.3.4';

        $requestNumber = 11;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn($requestNumber);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getRequestCount($since, $ip);

        $this->assertIsInt($result);
        $this->assertEquals($requestNumber, $result);
    }
}
