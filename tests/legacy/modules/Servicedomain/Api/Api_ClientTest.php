<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Servicedomain\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_ClientTest extends \BBTestCase
{
    protected ?\Box\Mod\Servicedomain\Api\Client $clientApi;

    public function setUp(): void
    {
        $this->clientApi = new \Box\Mod\Servicedomain\Api\Client();
    }

    public function testUpdateNameservers(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['updateNameservers'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateNameservers')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $clientApiMock->setDi($di);

        $data = [];
        $result = $clientApiMock->update_nameservers($data);

        $this->assertTrue($result);
    }

    public function testUpdateContacts(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['updateContacts'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('updateContacts')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->update_contacts($data);

        $this->assertTrue($result);
    }

    public function testEnablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['enablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('enablePrivacyProtection')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->enable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testDisablePrivacyProtection(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['disablePrivacyProtection'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('disablePrivacyProtection')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->disable_privacy_protection($data);

        $this->assertTrue($result);
    }

    public function testGetTransferCode(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['getTransferCode'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getTransferCode')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->get_transfer_code($data);

        $this->assertTrue($result);
    }

    public function testLock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['lock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->lock($data);

        $this->assertTrue($result);
    }

    public function testUnlock(): void
    {
        $model = new \Model_ServiceDomain();
        $model->loadBean(new \DummyBean());

        $clientApiMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Api\Client::class)
            ->onlyMethods(['_getService'])->getMock();
        $clientApiMock->expects($this->atLeastOnce())->method('_getService')
            ->willReturn($model);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Servicedomain\Service::class)
            ->onlyMethods(['unlock'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('unlock')
            ->willReturn(true);

        $clientApiMock->setService($serviceMock);

        $data = [];
        $result = $clientApiMock->unlock($data);

        $this->assertTrue($result);
    }

    public function testGetService(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicedomain\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('lock')
            ->willReturn(true);

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderIdMissingException(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicedomain\Service::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->never())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotFoundException(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicedomain\Service::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(null);
        $orderService->expects($this->never())
            ->method('getOrderService')
            ->willReturn(new \Model_ServiceDomain());

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivatedException(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Servicedomain\Service::class);
        $serviceMock->expects($this->never())->method('lock')
            ->willReturn(true);

        $this->clientApi->setService($serviceMock);

        $orderService = $this->getMockBuilder(\Box\Mod\Order\Service::class)->onlyMethods(['getOrderService', 'findForClientById'])->getMock();
        $orderService->expects($this->atLeastOnce())
            ->method('findForClientById')
            ->willReturn(new \Model_ClientOrder());
        $orderService->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn(null);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderService);
        $this->clientApi->setDi($di);

        $this->clientApi->setIdentity(new \Model_Client());

        $data = [
            'order_id' => 1,
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->clientApi->lock($data);

        $this->assertTrue($result);
    }
}
