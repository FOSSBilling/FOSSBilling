<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Domain\Tests\Api;

use FOSSBilling\ProductType\Domain\Api;
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

public function testUpdateNameservers(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['updateNameservers'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->exactly(2))
            ->method('fire');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['events_manager'] = $eventMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
            'ns1' => 'ns1.example.com',
            'ns2' => 'ns2.example.com',
        ];
        $result = $this->api->client_update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdateContacts(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['updateContacts'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
            'contact_email' => 'test@example.com',
        ];
        $result = $this->api->client_update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['enablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = ['order_id' => 1];
        $result = $this->api->client_enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['disablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = ['order_id' => 1];
        $result = $this->api->client_disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['getTransferCode'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->willReturn('ABC123');

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = ['order_id' => 1];
        $result = $this->api->client_get_transfer_code($data);

        $this->assertEquals('ABC123', $result);
    }

    public function testLock(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['lock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = ['order_id' => 1];
        $result = $this->api->client_lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $model = new \Model_ExtProductDomain();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder(\FOSSBilling\ProductType\Domain\DomainHandler::class)
            ->onlyMethods(['unlock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->willReturn(true);

        $orderServiceMock = $this->getMockBuilder(\Box\Mod\Order\Service::class)
            ->onlyMethods(['findForClientById', 'getOrderService'])->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Client());

        $data = ['order_id' => 1];
        $result = $this->api->client_unlock($data);

$this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ExtProductDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->api->setDi($di);

        $this->api->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];
        $result = $this->api->client_lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->never())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ExtProductDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->api->setDi($di);

        $this->api->setIdentity(new \Model_Client());

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->client_lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotFoundException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(null);
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ExtProductDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->api->setDi($di);

        $this->api->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->client_lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Domain\DomainHandler::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->api->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->api->setDi($di);

        $this->api->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->api->client_lock($data);

        $this->assertTrue($result);
    }
}
