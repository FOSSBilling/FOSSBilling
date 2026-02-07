<?php

declare(strict_types=1);

namespace Box\Mod\Servicelicense\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ClientTest extends \BBTestCase
{
    protected ?Client $api;

    public function setUp(): void
    {
        $this->api = new Client();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testReset(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $apiMock = $this->getMockBuilder(Client::class)
            ->onlyMethods(['_getService'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn(new \Model_ServiceLicense());

        $serviceMock = $this->createMock(\Box\Mod\Servicelicense\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $result = $apiMock->reset($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceLicense());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn(new \Model_ClientOrder());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $this->api->setIdentity($clientModel);

        $result = $this->api->_getService($data);
        $this->assertInstanceOf('\Model_ServiceLicense', $result);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn(new \Model_ClientOrder());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $this->api->setIdentity($clientModel);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }
}
