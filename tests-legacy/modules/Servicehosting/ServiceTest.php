<?php

namespace Box\Mod\Servicehosting;

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

    public static function validateOrdertDataProvider(): array
    {
        return [
            ['server_id', 'Hosting product is not configured completely. Configure server for hosting product.', 701],
            ['hosting_plan_id', 'Hosting product is not configured completely. Configure hosting plan for hosting product.', 702],
            ['sld', 'Domain name is invalid.', 703],
            ['tld', 'Domain extension is invalid.', 704],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validateOrdertDataProvider')]
    public function testvalidateOrderData(string $field, string $exceptionMessage, int $excCode): void
    {
        $data = [
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com',
        ];

        unset($data[$field]);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage($exceptionMessage);
        $this->service->validateOrderData($data);
    }

    public function testactionCreate(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $confArr = [
            'server_id' => 1,
            'hosting_plan_id' => 2,
            'sld' => 'great',
            'tld' => 'com',
        ];
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn($confArr);

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingPlansModel = new \Model_ServiceHostingHp();
        $hostingPlansModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($hostingServerModel, $hostingPlansModel);

        $servhostingModel = new \Model_ServiceHosting();
        $servhostingModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($servhostingModel);

        $newserviceHostingId = 4;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newserviceHostingId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->service->action_create($orderModel);
    }

    //    public function testaction_activate()
    //    {
    //        $orderModel = new \Model_ClientOrder();
    //        $orderModel->loadBean(new \DummyBean());
    //
    //        $confArr = array(
    //            'server_id' => 1,
    //            'hosting_plan_id' => 2,
    //            'sld' => 'great',
    //            'tld' => 'com',
    //            'username' => 'username',
    //            'password' => 'password'
    //        );
    //
    //        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
    //        $orderServiceMock->expects($this->atLeastOnce())
    //            ->method('getConfig')
    //            ->will($this->returnValue($confArr));
    //
    //        $servhostingModel = new \Model_ServiceHosting();
    //        $servhostingModel->loadBean(new \DummyBean());
    //        $orderServiceMock->expects($this->atLeastOnce())
    //            ->method('getOrderService')
    //            ->will($this->returnValue($servhostingModel));
    //
    //
    //        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
    //        $toolsMock->expects($this->atLeastOnce())
    //            ->method('generatePassword')
    //            ->will($this->returnValue('generatePassword'));
    //
    //        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
    //        $dbMock->expects($this->atLeastOnce())
    //            ->method('store');
    //
    //        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)
    //            ->onlyMethods(array('_getAM'))
    //            ->getMock();
    //
    //        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    //        $serverManagerMock->expects($this->atLeastOnce())
    //            ->method('createAccount');
    //
    //        $AMresultArray = array($serverManagerMock, new \Server_Account());
    //        $serviceMock->expects($this->atLeastOnce())
    //            ->method('_getAM')
    //            ->will($this->returnValue($AMresultArray));
    //
    //        $di = new \Pimple\Container();
    //        $di['db'] = $dbMock;
    //        $di['tools'] = $toolsMock;
    //        $di['mod_service'] = $di->protect(fn() => $orderServiceMock);
    //
    //        $serviceMock->setDi($di);
    //        $orderModel->config = $confArr;
    //        $result = $serviceMock->action_activate($orderModel);
    //        $this->assertIsArray($result);
    //        $this->assertNotEmpty($result['username']);
    //        $this->assertNotEmpty($result['password']);
    //    }

    public function testactionRenew(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $result = $this->service->action_renew($orderModel);
        $this->assertTrue($result);
    }

    public function testactionRenewOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_renew($orderModel);
    }

    public function testactionSuspend(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('suspendAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->action_suspend($orderModel);
        $this->assertTrue($result);
    }

    public function testactionSuspendOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_suspend($orderModel);
    }

    public function testactionUnsuspend(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('unsuspendAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->action_unsuspend($orderModel);
        $this->assertTrue($result);
    }

    public function testactionUnsuspendOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_unsuspend($orderModel);
    }

    public function testactionCancel(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('cancelAccount');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $serviceMock->setDi($di);
        $result = $serviceMock->action_cancel($orderModel);
        $this->assertTrue($result);
    }

    public function testactionCancelOrderWithoutActiveService(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->id = 1;

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
        $this->service->action_cancel($orderModel);
    }

    //    public function testaction_uncancel()
    //    {
    //        $orderModel = new \Model_ClientOrder();
    //        $orderModel->loadBean(new \DummyBean());
    //        $confArr = array(
    //            'server_id' => 1,
    //            'hosting_plan_id' => 2,
    //            'sld' => 'great',
    //            'tld' => 'com'
    //        );
    //        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
    //        $orderServiceMock->expects($this->atLeastOnce())
    //            ->method('getConfig')
    //            ->will($this->returnValue($confArr));
    //
    //        $model = new \Model_ServiceHosting();
    //        $model->loadBean(new \DummyBean());
    //        $orderServiceMock->expects($this->atLeastOnce())
    //            ->method('getOrderService')
    //            ->will($this->returnValue($model));
    //
    //        $hostingServerModel = new \Model_ServiceHostingServer();
    //        $hostingServerModel->loadBean(new \DummyBean());
    //        $hostingPlansModel = new \Model_ServiceHostingHp();
    //        $hostingPlansModel->loadBean(new \DummyBean());
    //        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
    //        $dbMock->expects($this->atLeastOnce())
    //            ->method('getExistingModelById')
    //            ->will($this->onConsecutiveCalls($hostingServerModel, $hostingPlansModel));
    //
    //        $servhostingModel = new \Model_ServiceHosting();
    //        $servhostingModel->loadBean(new \DummyBean());
    //        $dbMock->expects($this->atLeastOnce())
    //            ->method('dispense')
    //            ->will($this->returnValue($servhostingModel));
    //
    //        $newserviceHostingId = 4;
    //        $dbMock->expects($this->atLeastOnce())
    //            ->method('store')
    //            ->will($this->returnValue($newserviceHostingId));
    //
    //        $di = new \Pimple\Container();
    //        $di['db'] = $dbMock;
    //        $di['mod_service'] = $di->protect(fn() => $orderServiceMock);
    //
    //
    //
    //        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)
    //            ->onlyMethods(array('_getAM'))
    //            ->getMock();
    //
    //        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    //        $serverManagerMock->expects($this->atLeastOnce())
    //            ->method('createAccount');
    //        $AMresultArray = array($serverManagerMock, new \Server_Account());
    //        $serviceMock->expects($this->atLeastOnce())
    //            ->method('_getAM')
    //            ->will($this->returnValue($AMresultArray));
    //
    //
    //        $serviceMock->setDi($di);
    //        $serviceMock->action_uncancel($orderModel);
    //    }

    public function testactionDelete(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->status = 'active';

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['action_cancel'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('action_cancel');

        $serviceMock->setDi($di);
        $serviceMock->action_delete($orderModel);
    }

    public function testchangeAccountPlan(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $modelHp = new \Model_ServiceHostingHp();
        $modelHp->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM', 'getServerPackage'])
            ->getMock();
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPackage');
        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerPackage')
            ->willReturn(new \Server_Package());

        $serviceMock->setDi($di);
        $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
        $this->assertTrue($result);
    }

    public function testchangeAccountUsername(): void
    {
        $data = [
            'username' => 'u123456',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountUsernameMissingUsername(): void
    {
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account username is missing or is invalid');
        $this->service->changeAccountUsername($orderModel, $model, $data);
    }

    public function testchangeAccountIp(): void
    {
        $data = [
            'ip' => '1.1.1.1',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountIp');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountIpMissingIp(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account IP address is missing or is invalid');
        $this->service->changeAccountIp($orderModel, $model, $data);
    }

    public function testchangeAccountDomain(): void
    {
        $data = [
            'tld' => 'com',
            'sld' => 'testingSld',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountDomainMissingParams(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Domain SLD or TLD is missing');
        $this->service->changeAccountDomain($orderModel, $model, $data);
    }

    public function testchangeAccountPassword(): void
    {
        $data = [
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword');

        $AMresultArray = [$serverManagerMock, new \Server_Account()];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testchangeAccountPasswordMissingParams(): void
    {
        $data = [];
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Account password is missing or is invalid');
        $this->service->changeAccountPassword($orderModel, $model, $data);
    }

    public function testsync(): void
    {
        $data = [
            'password' => 'topsecret',
            'password_confirm' => 'topsecret',
        ];

        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());

        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['_getAM'])
            ->getMock();

        $accountObj = new \Server_Account();
        $accountObj->setUsername('testUser1');
        $accountObj->setIp('1.1.1.1');

        $accountObj2 = new \Server_Account();
        $accountObj2->setUsername('testUser2');
        $accountObj2->setIp('2.2.2.2');

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('synchronizeAccount')
            ->willReturn($accountObj2);

        $AMresultArray = [$serverManagerMock, $accountObj];
        $serviceMock->expects($this->atLeastOnce())
            ->method('_getAM')
            ->willReturn($AMresultArray);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $serviceMock->setDi($di);

        $result = $serviceMock->sync($orderModel, $model, $data);
        $this->assertTrue($result);
    }

    public function testtoApiArray(): void
    {
        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $hostingServer = new \Model_ServiceHostingServer();
        $hostingServer->loadBean(new \DummyBean());
        $hostingServer->manager = 'Custom';
        $hostingHp = new \Model_ServiceHostingHp();
        $hostingHp->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturnOnConsecutiveCalls($hostingServer, $hostingHp);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getServiceOrder');

        $serverManagerCustomMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['server_manager'] = $di->protect(fn ($manager, $config): \PHPUnit\Framework\MockObject\MockObject => $serverManagerCustomMock);

        $this->service->setDi($di);

        $result = $this->service->toApiArray($model, false, new \Model_Admin());
        $this->assertIsArray($result);
    }

    public function testupdate(): void
    {
        $data = [
            'username' => 'testUser',
            'ip' => '1.1.1.1',
        ];
        $model = new \Model_ServiceHosting();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->update($model, $data);
        $this->assertTrue($result);
    }

    public function testgetServerManagers(): void
    {
        $result = $this->service->getServerManagers();
        $this->assertIsArray($result);
    }

    public function testgetServerManagerConfig(): void
    {
        $manager = 'Custom';

        $expected = [
            'label' => 'Custom Server Manager',
        ];

        $result = $this->service->getServerManagerConfig($manager);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetServerPairs(): void
    {
        $expected = [
            '1' => 'name',
            '2' => 'ding',
        ];

        $queryResult = [
            [
                'id' => 1,
                'name' => 'name',
            ], [
                'id' => 2,
                'name' => 'ding',
            ],
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getServerPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetServerSearchQuery(): void
    {
        $result = $this->service->getServersSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals([], $result[1]);
    }

    public function testcreateServer(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($hostingServerModel);

        $newId = 1;
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn($newId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $name = 'newSuperFastServer';
        $ip = '1.1.1.1';
        $manager = 'Custom';
        $data = [];
        $result = $this->service->createServer($name, $ip, $manager, $data);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testdeleteServer(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteServer($hostingServerModel);
        $this->assertTrue($result);
    }

    public function testupdateServer(): void
    {
        $data = [
            'name' => 'newName',
            'ip' => '1.1.1.1',
            'hostname' => 'unknownStar',
            'active' => 1,
            'status_url' => 'na',
            'ns1' => 'ns1.testserver.eu',
            'ns2' => 'ns2.testserver.eu',
            'ns3' => 'ns3.testserver.eu',
            'ns4' => 'ns4.testserver.eu',
            'manager' => 'Custom',
            'username' => 'testingJohn',
            'password' => 'hardToGuess',
            'accesshash' => 'secret',
            'port' => '23',
            'secure' => 0,
        ];

        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateServer($hostingServerModel, $data);
        $this->assertTrue($result);
    }

    public function testgetServerManager(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingServerModel->manager = 'Custom';

        $serverManagerCustom = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();

        $di = new \Pimple\Container();
        $di['server_manager'] = $di->protect(fn ($manager, $config): \PHPUnit\Framework\MockObject\MockObject => $serverManagerCustom);
        $this->service->setDi($di);

        $result = $this->service->getServerManager($hostingServerModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testgetServerManagerManagerNotDefined(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(654);
        $this->expectExceptionMessage('Invalid server manager. Server was not configured properly');
        $this->service->getServerManager($hostingServerModel);
    }

    public function testgetServerManagerServerManagerInvalid(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingServerModel->manager = 'Custom';

        $di = new \Pimple\Container();
        $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
        $this->service->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage("Server manager {$hostingServerModel->manager} is invalid.");
        $this->service->getServerManager($hostingServerModel);
    }

    public function testtestConnection(): void
    {
        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('testConnection')
            ->willReturn(true);

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();

        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $hostingServerModel = new \Model_ServiceHostingServer();
        $result = $serviceMock->testConnection($hostingServerModel);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgetHpPairs(): void
    {
        $expected = [
            '1' => 'free',
            '2' => 'paid',
        ];

        $queryResult = [
            [
                'id' => 1,
                'name' => 'free',
            ], [
                'id' => 2,
                'name' => 'paid',
            ],
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($queryResult);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getHpPairs();
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testgetHpSearchQuery(): void
    {
        $result = $this->service->getServersSearchQuery([]);
        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertEquals([], $result[1]);
    }

    public function testdeleteHp(): void
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $this->service->setDi($di);

        $result = $this->service->deleteHp($model);
        $this->assertTrue($result);
    }

    public function testtoHostingHpApiArray(): void
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());

        $result = $this->service->toHostingHpApiArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdateHp(): void
    {
        $data = [
            'name' => 'firstPlan',
            'bandwidth' => '100000',
            'quota' => '1000',
            'max_addon' => '0',
            'max_ft' => '1',
            'max_sql' => '2',
            'max_pop' => '1',
            'max_sub' => '2',
            'max_park' => '1',
        ];

        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->updateHp($model, $data);
        $this->assertTrue($result);
    }

    public function testcreateHp(): void
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());
        $newId = 1;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn($newId);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->service->setDi($di);

        $result = $this->service->createHp('Free Plan', []);
        $this->assertIsInt($result);
        $this->assertEquals($newId, $result);
    }

    public function testgetServerPackage(): void
    {
        $model = new \Model_ServiceHostingHp();
        $model->loadBean(new \DummyBean());
        $model->config = '{}';

        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $result = $this->service->getServerPackage($model);
        $this->assertInstanceOf('\Server_Package', $result);
    }

    public function testgetServerManagerWithLog(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingServerModel->manager = 'Custom';

        $clientOrderModel = new \Model_ClientOrder();
        $clientOrderModel->loadBean(new \DummyBean());

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getLogger')
            ->willReturn(new \Box_Log());

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $serviceMock->setDi($di);
        $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
        $this->assertInstanceOf('\Server_Manager_Custom', $result);
    }

    public function testgetManagerUrls(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingServerModel->manager = 'Custom';

        $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getLoginUrl')
            ->willReturn('/login');
        $serverManagerMock->expects($this->atLeastOnce())
            ->method('getResellerLoginUrl')
            ->willReturn('/admin/login');

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->willReturn($serverManagerMock);

        $result = $serviceMock->getManagerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }

    public function testgetManagerUrlsException(): void
    {
        $hostingServerModel = new \Model_ServiceHostingServer();
        $hostingServerModel->loadBean(new \DummyBean());
        $hostingServerModel->manager = 'Custom';

        $serviceMock = $this->getMockBuilder('\\' . Service::class)
            ->onlyMethods(['getServerManager'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManager')
            ->will($this->throwException(new \Exception('Controlled unit test exception')));

        $result = $serviceMock->getManagerUrls($hostingServerModel);
        $this->assertIsArray($result);
        $this->assertFalse($result[0]);
        $this->assertFalse($result[1]);
    }

    public function testgetFreeTldsFreeTldsAreNotSet(): void
    {
        $di = new \Pimple\Container();

        $tldArray = ['tld' => '.com'];
        $serviceDomainServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicedomain\Service::class)->getMock();
        $serviceDomainServiceMock->expects($this->atLeastOnce())
            ->method('tldToApiArray')
            ->willReturn($tldArray);
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceDomainServiceMock);

        $tldModel = new \Model_Tld();
        $tldModel->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn([$tldModel]);
        $di['db'] = $dbMock;

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);
    }

    public function testgetFreeTlds(): void
    {
        $config = [
            'free_tlds' => ['.com'],
        ];
        $di = new \Pimple\Container();

        $this->service->setDi($di);
        $model = new \Model_Product();
        $model->loadBean(new \DummyBean());
        $model->config = json_encode($config);

        $result = $this->service->getFreeTlds($model);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }
}
