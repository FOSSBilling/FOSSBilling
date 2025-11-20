<?php

namespace Box\Mod\Servicehosting\Api;

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

    public function testchangeUsername(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Client::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_username([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchangeDomain(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Client::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_domain([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchangePassword(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Client::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_password([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testhpGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->hp_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testGetService(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $model = new \Model_ServiceHosting();
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);
        $result = $this->api->_getService($data);
        $this->assertIsArray($result);
        $this->assertInstanceOf('\Model_ClientOrder', $result[0]);
        $this->assertInstanceOf('\Model_ServiceHosting', $result[1]);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $model = null;
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }

    public function testGetServiceOrderNotFound(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = null;
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->_getService($data);
    }

    public function testGetServiceMissingOrderId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        $this->api->_getService($data);
    }
}
