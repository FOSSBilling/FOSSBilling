<?php

namespace Box\Mod\Servicehosting\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testchangePlan(): void
    {
        $data = [
            'plan_id' => 1,
        ];

        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPlan')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingHp());

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $result = $apiMock->change_plan($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchangePlanMissingPlanId(): void
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('plan_id is missing');
        $this->api->change_plan($data);
    }

    public function testchangeUsername(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
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

    public function testchangeIp(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountIp')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->change_ip([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchangeDomain(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
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
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
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

    public function testsync(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('sync')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->sync([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testupdate(): void
    {
        $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
        $apiMock = $this->getMockBuilder('\\' . Admin::class)
            ->onlyMethods(['_getService'])
            ->getMock();

        $apiMock->expects($this->atLeastOnce())
            ->method('_getService')
            ->willReturn($getServiceReturnValue);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $apiMock->setService($serviceMock);

        $result = $apiMock->update([]);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testmanagerGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManagers')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->manager_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testserverGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->server_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testaccountGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAccountsSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $systemServiceMock = $this->getMockBuilder('\\' . \Box\Mod\System\Service::class)->getMock();
        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->account_get_list([]);
        $this->assertIsArray($result);
    }

    public function testserverGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServersSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;
        $dbMock = $this->getMockBuilder('Box_Database')->getMock();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_get_list([]);
        $this->assertIsArray($result);
    }

    public function testserverCreate(): void
    {
        $data = [
            'name' => 'test',
            'ip' => '1.1.1.1',
            'manager' => 'ServerManagerCode',
        ];

        $newServerId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createServer')
            ->willReturn($newServerId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->server_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newServerId, $result);
    }

    public function testserverGet(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toHostingServerApiArray')
            ->willReturn([]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingServer());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_get($data);
        $this->assertIsArray($result);
    }

    public function testServerDelete(): void
    {
        // Test case 1: Server can be deleted
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteServer')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingServer());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_delete($data);
        $this->assertTrue($result);

        // Test case 2: Server is used by service_hostings and cannot be deleted
        $data['id'] = 2;

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingServer());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        // Mock the 'find' method to return a non-empty array, simulating the server being used by service hostings
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn(['dummy_data']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);

        // Now, we expect an exception to be thrown because the server is used by service_hostings
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(704);

        $this->api->server_delete($data);
    }

    public function testserverUpdate(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateServer')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingServer());
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testserverTestConnection(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('testConnection')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ServiceHostingServer());
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_test_connection($data);
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

    public function testhpGetList(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->hp_get_list([]);
        $this->assertIsArray($result);
    }

    public function testhpDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_ServiceHostingHp();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteHp')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        // Add a try-catch block to handle the exception thrown in the hp_delete function
        try {
            $result = $this->api->hp_delete($data);

            // If the function doesn't throw an exception, then the test should assert the result
            $this->assertIsBool($result);
            $this->assertTrue($result);
        } catch (\FOSSBilling\Exception $e) {
            // If the function throws an exception, the test should fail
            $this->fail('Exception thrown: ' . $e->getMessage());
        }
    }

    public function testhpGet(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_ServiceHostingHp();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toHostingHpApiArray')
            ->willReturn([]);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->hp_get($data);
        $this->assertIsArray($result);
    }

    public function testhpUpdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_ServiceHostingHp();

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateHp')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->hp_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testhpCreate(): void
    {
        $data = [
            'name' => 'test',
        ];

        $newHpId = 2;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Servicehosting\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createHp')
            ->willReturn($newHpId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->hp_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newHpId, $result);
    }

    public function testGetService(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new \Model_ServiceHosting();
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);

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
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = null;
        $orderServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Order\Service::class)->getMock();
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->_getService($data);
    }
}
