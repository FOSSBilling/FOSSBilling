<?php


namespace Box\Mod\Api;


class ServiceTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Api\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Api\Service();
    }

    public function testgetDi()
    {
        $di = new \Box_Di();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testlogRequest()
    {
        $affectedRows = 1;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->will($this->returnValue($affectedRows));

        $requestMock = $this->getMockBuilder('\Box_Request')->getMock();
        $requestMock->expects($this->atLeastOnce())
            ->method('getClientAddress')
            ->will($this->returnValue('1.1.1.1'));
        $requestMock->expects($this->atLeastOnce())
            ->method('getUri')
            ->will($this->returnValue('index.html'));

        $di = new \Box_Di();
        $di['request'] = $requestMock;
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->logRequest();
        $this->assertIsInt($result);
        $this->assertEquals($affectedRows, $result);
    }

    public function testgetRequestCount()
    {
        $since = 674690401; // timestamp == '1991-05-20 00:00:01';
        $ip = '1.2.3.4';

        $requestNumber = 11;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue($requestNumber));

        $di = new \Box_Di();
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $result = $this->service->getRequestCount($since, $ip);

        $this->assertIsInt($result);
        $this->assertEquals($requestNumber, $result);

    }
}
 