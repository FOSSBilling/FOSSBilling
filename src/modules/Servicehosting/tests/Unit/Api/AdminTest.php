<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Service as OrderService;
use Box\Mod\Servicehosting\Api\Admin;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository;
use Box\Mod\Servicehosting\Service;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

/**
 * @param array<class-string, Mockery\MockInterface> $repositories
 */
function serviceHostingAdminEmWith(array $repositories): EntityManagerInterface
{
    $em = Mockery::mock(EntityManagerInterface::class);
    foreach ($repositories as $entityClass => $repository) {
        $repository->shouldIgnoreMissing();
        $em->shouldReceive('getRepository')->with($entityClass)->andReturn($repository);
    }
    $em->shouldIgnoreMissing();

    return $em;
}

afterEach(function (): void {
    Mockery::close();
});

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

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('changeAccountPlan')
    ->atLeast()->once()
    ->andReturn(true);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingHp());

    $emMock = serviceHostingAdminEmWith([ServiceHostingHp::class => $hpRepo]);

    $di = container();
    $di['em'] = $emMock;

    $apiMock->setDi($di);
    $apiMock->setService($serviceMock);

    $result = $apiMock->change_plan($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testChangePlanMissingPlanId', function (): void {
    $adminApi = apiEndpoint(new Admin());

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'change_plan', []))
        ->toThrow(FOSSBilling\InformationException::class, 'plan_id is missing');
});

test('testChangeUsername', function (): void {
    $api = apiEndpoint(new Admin());
    $getServiceReturnValue = [new Model_ClientOrder(), new ServiceHosting()];
    $apiMock = apiEndpoint(Mockery::mock(Admin::class)->makePartial());

    $apiMock
    ->shouldReceive('_getService')
    ->atLeast()->once()
    ->andReturn($getServiceReturnValue);

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
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
    $serviceMock = Mockery::mock(Service::class);
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
    $serviceMock = Mockery::mock(Service::class);
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
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('getAccountsSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);
    $serviceMock
    ->shouldReceive('getAccountsBatchForApi')
    ->once()
    ->with([['id' => 1]], null)
    ->andReturn([['id' => 1, 'order' => null]]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => [['id' => 1]]]);

    $di = container();
    $dbStub = Mockery::mock('Box_Database');
    $dbStub->shouldNotReceive('dispense');
    $dbStub->shouldNotReceive('findOne');
    $di['mod_service'] = $di->protect(moduleService());
    $di['pager'] = $pagerMock;
    $di['db'] = $dbStub;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->account_get_list([]);
    expect($result['list'])->toBe([['id' => 1, 'order' => null]]);
});

test('testServerGetList', function (): void {
    $api = apiEndpoint(new Admin());
    $server = (new ServiceHostingServer())->setId(1);
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('getServersSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);
    $serviceMock
    ->shouldReceive('toHostingServerApiArray')
    ->once()
    ->with($server, false, null)
    ->andReturn(['id' => 1]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => [['id' => 1]]]);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('findBy')->once()->with(['id' => [1]])->andReturn([$server]);

    $di = container();
    $di['pager'] = $pagerMock;
    $di['em'] = serviceHostingAdminEmWith([ServiceHostingServer::class => $serverRepo]);
    $dbStub = Mockery::mock('Box_Database');
    $di['db'] = $dbStub;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get_list([]);
    expect($result['list'])->toBe([['id' => 1]]);
});

test('testServerCreate', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'name' => 'test',
        'ip' => '1.1.1.1',
        'manager' => 'ServerManagerCode',
    ];

    $newServerId = 1;

    $serviceMock = Mockery::mock(Service::class);
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

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('toHostingServerApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $emMock = serviceHostingAdminEmWith([ServiceHostingServer::class => $serverRepo]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_get($data);
    expect($result)->toBeArray();
});

test('testServerDelete', function (): void {
    $api = apiEndpoint(new Admin());
    // Test case 1: Server can be deleted
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('deleteServer')
    ->atLeast()->once()
    ->andReturn(true);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $serviceHostingRepo = Mockery::mock(ServiceHostingRepository::class);
    $serviceHostingRepo->shouldReceive('count')->once()->with(['serviceHostingServerId' => 1])->andReturn(0);

    $emMock = serviceHostingAdminEmWith([
        ServiceHostingServer::class => $serverRepo,
        ServiceHosting::class => $serviceHostingRepo,
    ]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_delete($data);
    expect($result)->toBeTrue();

    // Test case 2: Server is used by service_hostings and cannot be deleted
    $data['id'] = 2;

    $serverRepo2 = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo2->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $serviceHostingRepo2 = Mockery::mock(ServiceHostingRepository::class);
    $serviceHostingRepo2->shouldReceive('count')->once()->with(['serviceHostingServerId' => 2])->andReturn(1);

    $emMock2 = serviceHostingAdminEmWith([
        ServiceHostingServer::class => $serverRepo2,
        ServiceHosting::class => $serviceHostingRepo2,
    ]);

    $di = container();
    $di['em'] = $emMock2;
    $api->setDi($di);

    // Now, we expect an exception to be thrown because the server is used by service_hostings
    $this->expectException(FOSSBilling\Exception::class);
    $this->expectExceptionCode(704);

    $api->server_delete($data);
});

test('testServerUpdate', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('updateServer')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andReturn(new Server_Manager_Custom([]));

    $serverModel = new ServiceHostingServer();
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn($serverModel);

    $emMock = serviceHostingAdminEmWith([ServiceHostingServer::class => $serverRepo]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testServerUpdateSurfacesServerManagerErrorsAsInformationException', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('updateServer')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andThrow(new Server_Exception('Server manager is not fully configured.'));

    $serverModel = new ServiceHostingServer();
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn($serverModel);

    $emMock = serviceHostingAdminEmWith([ServiceHostingServer::class => $serverRepo]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    expect(fn (): bool => $api->server_update($data))->toThrow(FOSSBilling\InformationException::class);
});

test('testServerTestConnection', function (): void {
    $api = apiEndpoint(new Admin());
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('testConnection')
    ->atLeast()->once()
    ->andReturn(true);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $emMock = serviceHostingAdminEmWith([ServiceHostingServer::class => $serverRepo]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->server_test_connection($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testHpGetPairs', function (): void {
    $api = apiEndpoint(new Admin());
    $serviceMock = Mockery::mock(Service::class);
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
    $hp = (new ServiceHostingHp())->setId(1);
    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('getHpSearchQuery')
    ->atLeast()->once()
    ->andReturn(['SQLstring', []]);
    $serviceMock
    ->shouldReceive('toHostingHpApiArray')
    ->once()
    ->with($hp, false, null)
    ->andReturn(['id' => 1]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => [['id' => 1]]]);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('findBy')->once()->with(['id' => [1]])->andReturn([$hp]);

    $di = container();
    $di['pager'] = $pagerMock;
    $di['em'] = serviceHostingAdminEmWith([ServiceHostingHp::class => $hpRepo]);

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->hp_get_list([]);
    expect($result['list'])->toBe([['id' => 1]]);
});

test('testHpDelete', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('deleteHp')
    ->atLeast()->once()
    ->andReturn(true);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingHp());

    $serviceHostingRepo = Mockery::mock(ServiceHostingRepository::class);
    $serviceHostingRepo->shouldReceive('count')->once()->with(['serviceHostingHpId' => 1])->andReturn(0);

    $emMock = serviceHostingAdminEmWith([
        ServiceHostingHp::class => $hpRepo,
        ServiceHosting::class => $serviceHostingRepo,
    ]);

    $di = container();
    $di['em'] = $emMock;
    $api->setDi($di);
    $api->setService($serviceMock);

    // Add a try-catch block to handle the exception thrown in the hp_delete function
    try {
        $result = $api->hp_delete($data);

        // If the function doesn't throw an exception, then the test should assert the result
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    } catch (FOSSBilling\Exception $e) {
        // If the function throws an exception, the test should fail
        $this->fail('Exception thrown: ' . $e->getMessage());
    }
});

test('testHpGet', function (): void {
    $api = apiEndpoint(new Admin());
    $data = [
        'id' => 1,
    ];

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('toHostingHpApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingHp());

    $emMock = serviceHostingAdminEmWith([ServiceHostingHp::class => $hpRepo]);

    $di = container();
    $di['em'] = $emMock;
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

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock
    ->shouldReceive('updateHp')
    ->atLeast()->once()
    ->andReturn(true);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingHp());

    $emMock = serviceHostingAdminEmWith([ServiceHostingHp::class => $hpRepo]);

    $di = container();
    $di['em'] = $emMock;
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

    $serviceMock = Mockery::mock(Service::class);
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
    $orderServiceMock = Mockery::mock(OrderService::class);
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
    $orderServiceMock = Mockery::mock(OrderService::class);
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
