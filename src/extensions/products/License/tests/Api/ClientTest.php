<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\License\Tests\Api;

use FOSSBilling\ProductType\License\Api;
use FOSSBilling\ProductType\License\Entity\License;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ClientTest extends \BBTestCase
{
    protected ?Api $api;

    public function setUp(): void
    {
        $this->api = new Api();
        $this->api->setIdentity(new \Model_Client());
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

        $apiMock = $this->getMockBuilder(Api::class)
            ->onlyMethods(['getServiceModelForClient'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('getServiceModelForClient')
            ->willReturn(new License(1));

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->willReturn(true);

        $apiMock->setService($serviceMock);
        $apiMock->setIdentity(new \Model_Client());
        $result = $apiMock->client_reset($data);

        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $data['order_id'] = 1;

        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new License(1));

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->with('ClientOrder')
            ->willReturn(new \Model_ClientOrder());

        $licenseServiceMock = $this->createMock(\FOSSBilling\ProductType\License\LicenseHandler::class);
        $licenseServiceMock->expects($this->atLeastOnce())
            ->method('reset')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($licenseServiceMock);

        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \DummyBean());
        $this->api->setIdentity($clientModel);

        $result = $this->api->client_reset($data);
        $this->assertTrue($result);
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
        $this->api->client_reset($data);
    }
}
