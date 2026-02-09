<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Servicehosting\Api\Admin;

beforeEach(function (): void {
    $this->api = new Admin();
});

test('testGetDi', function (): void {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangePlan', function (): void {
    $data = [
        'plan_id' => 1,
    ];

    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('changeAccountPlan')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingHp());

    $di = container();
    $di['db'] = $dbMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->change_plan($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePlanMissingPlanId', function (): void {
    $data = [];

    $di = container();
    $this->api->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('plan_id is missing');
    $this->api->change_plan($data);
});

test('testChangeUsername', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('changeAccountUsername')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_username([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangeIp', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('changeAccountIp')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_ip([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangeDomain', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('changeAccountDomain')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_domain([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePassword', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('changeAccountPassword')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_password([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testSync', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('sync')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->sync([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testUpdate', function (): void {
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = $this->getMockBuilder(Admin::class)
        ->onlyMethods(['_getService'])
        ->getMock();

    $apiMock->expects($this->atLeastOnce())
        ->method('_getService')
        ->willReturn($getServiceReturnValue);

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('update')
        ->willReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->update([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testManagerGetPairs', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerManagers')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->manager_get_pairs([]);
    expect($result)->toBeArray();
});

test('testServerGetPairs', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerPairs')
        ->willReturn([]);

    $this->api->setService($serviceMock);

    $result = $this->api->server_get_pairs([]);
    expect($result)->toBeArray();
});

test('testAccountGetList', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
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

    $di = container();
    $systemServiceStub = $this->createStub(\Box\Mod\System\Service::class);
    $dbStub = $this->createStub('Box_Database');
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceStub);
    $di['pager'] = $pagerMock;
    $di['db'] = $dbStub;

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->account_get_list([]);
    expect($result)->toBeArray();
});

test('testServerGetList', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
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

    $di = container();
    $di['pager'] = $pagerMock;
    $dbStub = $this->createStub('Box_Database');
    $di['db'] = $dbStub;

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->server_get_list([]);
    expect($result)->toBeArray();
});

test('testServerCreate', function (): void {
    $data = [
        'name' => 'test',
        'ip' => '1.1.1.1',
        'manager' => 'ServerManagerCode',
    ];

    $newServerId = 1;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('createServer')
        ->willReturn($newServerId);

    $di = container();
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->server_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newServerId);
});

test('testServerGet', function (): void {
    $data['id'] = 1;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toHostingServerApiArray')
        ->willReturn([]);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->server_get($data);
    expect($result)->toBeArray();
});

test('testServerDelete', function (): void {
    // Test case 1: Server can be deleted
    $data['id'] = 1;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('deleteServer')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->server_delete($data);
    expect($result)->toBeTrue();

    // Test case 2: Server is used by service_hostings and cannot be deleted
    $data['id'] = 2;

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingServer());

    // Mock the 'find' method to return a non-empty array, simulating the server being used by service hostings
    $dbMock->expects($this->atLeastOnce())
        ->method('find')
        ->willReturn(['dummy_data']);

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);

    // Now, we expect an exception to be thrown because the server is used by service_hostings
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionCode(704);

    $this->api->server_delete($data);
});

test('testServerUpdate', function (): void {
    $data['id'] = 1;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('updateServer')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->server_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testServerTestConnection', function (): void {
    $data['id'] = 1;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('testConnection')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->server_test_connection($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpGetPairs', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getHpPairs')
        ->willReturn([]);

    $this->api->setService($serviceMock);
    $result = $this->api->hp_get_pairs([]);
    expect($result)->toBeArray();
});

test('testHpGetList', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
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

    $di = container();
    $di['pager'] = $pagerMock;

    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->hp_get_list([]);
    expect($result)->toBeArray();
});

test('testHpDelete', function (): void {
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('deleteHp')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    // Add a try-catch block to handle the exception thrown in the hp_delete function
    try {
        $result = $this->api->hp_delete($data);

        // If the function doesn't throw an exception, then the test should assert the result
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    } catch (\FOSSBilling\Exception $e) {
        // If the function throws an exception, the test should fail
        $this->fail('Exception thrown: ' . $e->getMessage());
    }
});

test('testHpGet', function (): void {
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toHostingHpApiArray')
        ->willReturn([]);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->hp_get($data);
    expect($result)->toBeArray();
});

test('testHpUpdate', function (): void {
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('updateHp')
        ->willReturn(true);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $this->api->setDi($di);
    $this->api->setService($serviceMock);

    $result = $this->api->hp_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpCreate', function (): void {
    $data = [
        'name' => 'test',
    ];

    $newHpId = 2;

    $serviceMock = $this->createMock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('createHp')
        ->willReturn($newHpId);

    $di = container();
    $this->api->setDi($di);

    $this->api->setService($serviceMock);

    $result = $this->api->hp_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newHpId);
});

test('testGetService', function (): void {
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($clientOrderModel);

    $model = new \Model_ServiceHosting();
    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
    $di['db'] = $dbMock;

    $this->api->setDi($di);

    $result = $this->api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('\Model_ClientOrder');
    expect($result[1])->toBeInstanceOf('\Model_ServiceHosting');
});

test('testGetServiceOrderNotActivated', function (): void {
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
    $di['db'] = $dbMock;
    $this->api->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order is not activated');
    $this->api->_getService($data);
});
