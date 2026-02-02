<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Hosting\Tests\Api;

use FOSSBilling\ProductType\Hosting\Api;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ClientTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

public function testHpGetPairs(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->client_hp_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testGetService(): void
    {
        $data = [
            'order_id' => 1,
            'username' => 'newuser',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $model = new \Model_ExtProductHosting();
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername')
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $result = $this->api->client_change_username($data);
        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data = [
            'order_id' => 1,
            'username' => 'newuser',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $model = null;
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->client_change_username($data);
    }

    public function testGetServiceOrderNotFound(): void
    {
        $data = [
            'order_id' => 1,
            'username' => 'newuser',
        ];

        $clientOrderModel = null;
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn($clientOrderModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $clientModel->id = 1;
        $this->api->setIdentity($clientModel);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order not found');
        $this->api->client_change_username($data);
    }

    public function testGetServiceMissingOrderId(): void
    {
        $data = [
            'username' => 'newuser',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order ID is required');
        $this->api->client_change_username($data);
    }
}
