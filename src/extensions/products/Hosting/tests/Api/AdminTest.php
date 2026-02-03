<?php

declare(strict_types=1);

namespace FOSSBilling\ProductType\Hosting\Tests\Api;

use FOSSBilling\ProductType\Hosting\Api;
use FOSSBilling\ProductType\Hosting\Entity\Hosting;
use FOSSBilling\ProductType\Hosting\Entity\HostingPlan;
use FOSSBilling\ProductType\Hosting\Entity\HostingServer;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
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

    public function testChangePlan(): void
    {
        $data = [
            'order_id' => 1,
            'plan_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($clientOrderModel, new HostingPlan());

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPlan')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_plan($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testChangePlanMissingPlanId(): void
    {
        $data = [];

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('plan_id is missing');
        $this->api->admin_change_plan($data);
    }

    public function testChangeUsername(): void
    {
        $data = [
            'order_id' => 1,
            'username' => 'newuser',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountUsername')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_username($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testChangeDomain(): void
    {
        $data = [
            'order_id' => 1,
            'domain' => 'newdomain.com',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountDomain')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_domain($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testChangePassword(): void
    {
        $data = [
            'order_id' => 1,
            'password' => 'newpassword',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPassword')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_password($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testSync(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('sync')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_sync($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testUpdate(): void
    {
        $data = [
            'order_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testChangeIp(): void
    {
        $data = [
            'order_id' => 1,
            'ip' => '2.2.2.2',
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientOrderModel);

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountIp')
            ->willReturn(true);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_ip($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testManagerGetPairs(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerManagers')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_manager_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testServerGetPairs(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServerPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testAccountGetList(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAccountsSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $dbMock = $this->createMock('Box_Database');
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_account_get_list([]);
        $this->assertIsArray($result);
    }

    public function testServerGetList(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getServersSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;
        $dbMock = $this->createMock('Box_Database');
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_get_list([]);
        $this->assertIsArray($result);
    }

    public function testServerCreate(): void
    {
        $data = [
            'name' => 'test',
            'ip' => '1.1.1.1',
            'manager' => 'ServerManagerCode',
        ];

        $newServerId = 1;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createServer')
            ->willReturn($newServerId);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newServerId, $result);
    }

    public function testServerGet(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toHostingServerApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new HostingServer());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_get($data);
        $this->assertIsArray($result);
    }

    public function testServerDelete(): void
    {
        // Test case 1: Server can be deleted
        $data['id'] = 1;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteServer')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new HostingServer());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_delete($data);
        $this->assertTrue($result);

        // Test case 2: Server is used by service_hostings and cannot be deleted
        $data['id'] = 2;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new HostingServer());

        // Mock the 'find' method to return a non-empty array, simulating the server being used by service hostings
        $dbMock->expects($this->atLeastOnce())
            ->method('find')
            ->willReturn(['dummy_data']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);

        // Now, we expect an exception to be thrown because the server is used by service_hostings
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(704);

        $this->api->admin_server_delete($data);
    }

    public function testServerUpdate(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateServer')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new HostingServer());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testServerTestConnection(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('testConnection')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new HostingServer());

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_server_test_connection($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testHpGetPairs(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpPairs')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->admin_hp_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testHpGetList(): void
    {
        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHpSearchQuery')
            ->willReturn(['SQLstring', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_hp_get_list([]);
        $this->assertIsArray($result);
    }

    public function testHpDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new HostingPlan();

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteHp')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        // Add a try-catch block to handle the exception thrown in the hp_delete function
        try {
            $result = $this->api->admin_hp_delete($data);

            // If the function doesn't throw an exception, then the test should assert the result
            $this->assertIsBool($result);
            $this->assertTrue($result);
        } catch (\FOSSBilling\Exception $e) {
            // If the function throws an exception, the test should fail
            $this->fail('Exception thrown: ' . $e->getMessage());
        }
    }

    public function testHpGet(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new HostingPlan();

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toHostingHpApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_hp_get($data);
        $this->assertIsArray($result);
    }

    public function testHpUpdate(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new HostingPlan();

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateHp')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_hp_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testHpCreate(): void
    {
        $data = [
            'name' => 'test',
        ];

        $newHpId = 2;

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createHp')
            ->willReturn($newHpId);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->admin_hp_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newHpId, $result);
    }

    public function testGetService(): void
    {
        $data = [
            'order_id' => 1,
            'plan_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($clientOrderModel, new HostingPlan());

        $model = new Hosting(1);
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $serviceMock = $this->createMock(\FOSSBilling\ProductType\Hosting\HostingHandler::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changeAccountPlan')
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->admin_change_plan($data);
        $this->assertTrue($result);
    }

    public function testGetServiceOrderNotActivated(): void
    {
        $data = [
            'order_id' => 1,
            'plan_id' => 1,
        ];

        $clientOrderModel = new \Model_ClientOrder();
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturnOnConsecutiveCalls($clientOrderModel, new HostingPlan());

        $model = null;
        $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
        $orderServiceMock->expects($this->atLeastOnce())
            ->method('getOrderService')
            ->willReturn($model);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;
        $this->api->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Order is not activated');
        $this->api->admin_change_plan($data);
    }
}
