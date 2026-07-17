<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicehosting\Api\Admin;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

test('testGetDi', function (): void {
    $api = apiEndpoint(new Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toBe($di);
});

test('testChangePlan', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'plan_id' => 1,
    ];

    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $hpRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository::class);
    $hpRepoMock->shouldReceive('find')->with(1)->andReturn(new ServiceHostingHp());

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('changeAccountPlan')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('getServiceHostingHpRepository')->andReturn($hpRepoMock);

    $di = container();

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->change_plan($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePlanMissingPlanId', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [];

    $di = container();
    $api->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('plan_id is missing');
    $api->change_plan($data);
});

test('testChangeUsername', function (): void {
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServerManagers')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->manager_get_pairs([]);
    expect($result)->toBeArray();
});

test('testServerGetPairs', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServerPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->server_get_pairs([]);
    expect($result)->toBeArray();
});

test('testAccountGetList', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getAccountsSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $di['mod_service'] = $di->protect(moduleService());
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->account_get_list([]);
    expect($result)->toBeArray();
});

test('testServerGetList', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getServersSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get_list([]);
    expect($result)->toBeArray();
});

test('testServerCreate', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'name' => 'test',
        'ip' => '1.1.1.1',
        'manager' => 'ServerManagerCode',
    ];

    $newServerId = 1;

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serverRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock->shouldReceive('find')->with(1)->andReturn(new ServiceHostingServer());

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('toHostingServerApiArray')
    ->atLeast()->once()
    ->andReturn([]);
    $serviceMock->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get($data);
    expect($result)->toBeArray();
});

test('testServerDelete', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serverRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn(new ServiceHostingServer());

    $hostingRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingRepository::class);
    $hostingRepoMock->shouldReceive('findBy')->atLeast()->once()->andReturn([]);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('deleteServer')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock);
    $serviceMock->shouldReceive('getServiceHostingRepository')->andReturn($hostingRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_delete($data);
    expect($result)->toBeTrue();

    // Test case 2: Server is used by service_hostings and cannot be deleted
    $data['id'] = 2;

    $serverRepoMock2 = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock2->shouldReceive('find')->with(2)->atLeast()->once()->andReturn(new ServiceHostingServer());

    $hostingRepoMock2 = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingRepository::class);
    $hostingRepoMock2->shouldReceive('findBy')->atLeast()->once()->andReturn(['dummy_data']);

    $serviceMock2 = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock2->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock2);
    $serviceMock2->shouldReceive('getServiceHostingRepository')->andReturn($hostingRepoMock2);
    $serviceMock2->shouldReceive('deleteServer')->byDefault();

    $api->setService($serviceMock2);

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionCode(704);

    $api->server_delete($data);
});

test('testServerUpdate', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serverRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn(new ServiceHostingServer());

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('updateServer')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andReturn(new Server_Manager_Custom([]));
    $serviceMock->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testServerUpdateSurfacesServerManagerErrorsAsInformationException', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serverRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn(new ServiceHostingServer());

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('updateServer')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andThrow(new Server_Exception('Server manager is not fully configured.'));
    $serviceMock->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn (): bool => $api->server_update($data))->toThrow(FOSSBilling\InformationException::class);
});

test('testServerTestConnection', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serverRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository::class);
    $serverRepoMock->shouldReceive('find')->with(1)->andReturn(new ServiceHostingServer());

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('testConnection')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('getServiceHostingServerRepository')->andReturn($serverRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_test_connection($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpGetPairs', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpPairs')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->hp_get_pairs([]);
    expect($result)->toBeArray();
});

test('testHpGetList', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('getHpSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
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
    $api = apiEndpoint(new Admin());
    $data = [
        'id' => 1,
    ];

    $model = new ServiceHostingHp();

    $hpRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository::class);
    $hpRepoMock->shouldReceive('find')->with(1)->atLeast()->once()->andReturn($model);

    $hostingRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingRepository::class);
    $hostingRepoMock->shouldReceive('findBy')->atLeast()->once()->andReturn([]);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('deleteHp')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('getServiceHostingHpRepository')->andReturn($hpRepoMock);
    $serviceMock->shouldReceive('getServiceHostingRepository')->andReturn($hostingRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    try {
        $result = $api->hp_delete($data);

        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    } catch (FOSSBilling\Exception $e) {
        $this->fail('Exception thrown: ' . $e->getMessage());
    }
});

test('testHpGet', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'id' => 1,
    ];

    $model = new ServiceHostingHp();

    $hpRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository::class);
    $hpRepoMock->shouldReceive('find')->with(1)->andReturn($model);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('toHostingHpApiArray')
    ->atLeast()->once()
    ->andReturn([]);
    $serviceMock->shouldReceive('getServiceHostingHpRepository')->andReturn($hpRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_get($data);
    expect($result)->toBeArray();
});

test('testHpUpdate', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'id' => 1,
    ];

    $model = new ServiceHostingHp();

    $hpRepoMock = Mockery::mock(Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository::class);
    $hpRepoMock->shouldReceive('find')->with(1)->andReturn($model);

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
    $serviceMock
    ->shouldReceive('updateHp')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('getServiceHostingHpRepository')->andReturn($hpRepoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpCreate', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'name' => 'test',
    ];

    $newHpId = 2;

    $serviceMock = Mockery::mock(Box\Mod\Servicehosting\Service::class);
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
    $api = apiEndpoint(new Admin());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = new ServiceHosting();
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;

    $api->setDi($di);

    $result = $api->_getService($data);
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf('\Model_ClientOrder');
    expect($result[1])->toBeInstanceOf(ServiceHosting::class);
});

test('testGetServiceOrderNotActivated', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'order_id' => 1,
    ];

    $clientOrderModel = new Model_ClientOrder();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($clientOrderModel);

    $model = null;
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['db'] = $dbMock;
    $api->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Order is not activated');
    $api->_getService($data);
});
