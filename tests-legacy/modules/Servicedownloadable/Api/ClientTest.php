<?php

namespace Box\Mod\Servicedownloadable\Api;

class ClientTest extends \BBTestCase
{
    /**
     * @var Client
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Client();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testsendFileMissingOrderId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        $this->api->send_file($data);
    }

    public function testsendFileOrderNotFound(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setIdentity($modelClient);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->send_file($data);
    }

    public function testsendFileOrderNotActivated(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_ClientOrder());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setIdentity($modelClient);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->send_file($data);
    }

    public function testsendFile(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedownloadable\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendFile')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDownloadable());

        $mockOrder = new \Model_ClientOrder();
        $mockOrder->loadBean(new \DummyBean());
        $mockOrder->status = 'active';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($mockOrder);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setIdentity($modelClient);
        $this->api->setService($serviceMock);

        $result = $this->api->send_file($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
