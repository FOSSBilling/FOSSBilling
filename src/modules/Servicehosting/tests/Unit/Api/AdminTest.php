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
    $api = new Admin();
});

test('testGetDi', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangePlan', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'plan_id' => 1,
    ];

    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountPlan')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingHp());

    $di = container();
    $di['db'] = $dbMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->change_plan($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePlanMissingPlanId', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [];

    $di = container();
    $api->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('plan_id is missing');
    $api->change_plan($data);
});

test('testChangeUsername', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountUsername')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_username([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangeIp', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountIp')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_ip([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangeDomain', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountDomain')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_domain([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePassword', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountPassword')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->change_password([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testSync', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('sync')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->sync([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testUpdate', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $getServiceReturnValue = [new \Model_ClientOrder(), new \Model_ServiceHosting()];
    $apiMock = Mockery::mock(Admin::class)->makePartial();

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('update')
    ->atLeast()->once()
    ->andReturn(true);

    $apiMock->setService($serviceMock);

    $result = $apiMock->update([]);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testManagerGetPairs', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServerManagers')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->manager_get_pairs([]);
    expect($result)->toBeArray();
});

test('testServerGetPairs', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServerPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->server_get_pairs([]);
    expect($result)->toBeArray();
});

test('testAccountGetList', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getAccountsSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $systemServiceStub = $this->createStub(\Box\Mod\System\Service::class);
    $dbStub = Mockery::mock('Box_Database');
    $di['mod_service'] = $di->protect(fn ($name) => $systemServiceStub);
    $di['pager'] = $pagerMock;
    $di['db'] = $dbStub;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->account_get_list([]);
    expect($result)->toBeArray();
});

test('testServerGetList', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServersSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;
    $dbStub = Mockery::mock('Box_Database');
    $di['db'] = $dbStub;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get_list([]);
    expect($result)->toBeArray();
});

test('testServerCreate', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'name' => 'test',
        'ip' => '1.1.1.1',
        'manager' => 'ServerManagerCode',
    ];

    $newServerId = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('createServer')
    ->atLeast()->once()
    ->andReturn($newServerId);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->server_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newServerId);
});

test('testServerGet', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('toHostingServerApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get($data);
    expect($result)->toBeArray();
});

test('testServerDelete', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    // Test case 1: Server can be deleted
    $data['id'] = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('deleteServer')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingServer());
    $dbMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_delete($data);
    expect($result)->toBeTrue();

    // Test case 2: Server is used by service_hostings and cannot be deleted
    $data['id'] = 2;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingServer());

    // Mock the 'find' method to return a non-empty array, simulating the server being used by service hostings
    $dbMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn(['dummy_data']);

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);

    // Now, we expect an exception to be thrown because the server is used by service_hostings
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionCode(704);

    $api->server_delete($data);
});

test('testServerUpdate', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('updateServer')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testServerTestConnection', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('testConnection')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_ServiceHostingServer());

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_test_connection($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpGetPairs', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->hp_get_pairs([]);
    expect($result)->toBeArray();
});

test('testHpGetList', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_get_list([]);
    expect($result)->toBeArray();
});

test('testHpDelete', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('deleteHp')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);
    $dbMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    // Add a try-catch block to handle the exception thrown in the hp_delete function
    try {
        $result = $api->hp_delete($data);

        // If the function doesn't throw an exception, then the test should assert the result
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    } catch (\FOSSBilling\Exception $e) {
        // If the function throws an exception, the test should fail
        $this->fail('Exception thrown: ' . $e->getMessage());
    }
});

test('testHpGet', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('toHostingHpApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_get($data);
    expect($result)->toBeArray();
});

test('testHpUpdate', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ServiceHostingHp();

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('updateHp')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpCreate', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'name' => 'test',
    ];

    $newHpId = 2;

    $serviceMock = Mockery::mock(\Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('createHp')
    ->atLeast()->once()
    ->andReturn($newHpId);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $result = $api->hp_create($data);
    expect($result)->toBeInt();
    expect($result)->toBe($newHpId);
});

test('testGetService', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = new \Model_ServiceHosting();
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $result = $api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('\Model_ClientOrder');
    expect($result[1])->toBeInstanceOf('\Model_ServiceHosting');
});

test('testGetServiceOrderNotActivated', function (): void {
    $api = new \Box\Mod\Servicehosting\Api\Admin();
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new \Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;
    $api->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order is not activated');
    $api->_getService($data);
});
