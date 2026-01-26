<?php

declare(strict_types=1);

namespace Box\Mod\Servicedownloadable\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
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

    public function testSendFileMissingOrderId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        $this->api->send_file($data);
    }

    public function testSendFileOrderNotFound(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setIdentity($modelClient);
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->send_file($data);
    }

    public function testSendFileOrderNotActivated(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_ClientOrder());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setIdentity($modelClient);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->send_file($data);
    }

    public function testSendFile(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Servicedownloadable\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('sendFile')
            ->willReturn(true);

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDownloadable());

        $mockOrder = new \Model_ClientOrder();
        $mockOrder->loadBean(new \DummyBean());
        $mockOrder->status = 'active';

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($mockOrder);

        $di = $this->getDi();
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
