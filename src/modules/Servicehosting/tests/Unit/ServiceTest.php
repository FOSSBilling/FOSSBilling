<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Service as OrderService;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository;
use Box\Mod\Servicehosting\Service;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;
use function Tests\Helpers\setEntityId;

afterEach(function (): void {
    Mockery::close();
});

test('batch enriches hosting accounts with orders and clients', function (): void {
    $service = new Service();
    $account = [
        'id' => 10,
        'sld' => 'example',
        'tld' => '.com',
        'client_id' => 7,
        'service_hosting_server_id' => 2,
        'service_hosting_hp_id' => 3,
        'reseller' => 0,
        'ip' => '192.0.2.10',
        'username' => 'example',
        'created_at' => '2026-07-19 10:00:00',
        'updated_at' => '2026-07-19 10:01:00',
    ];

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->with(Mockery::pattern('/FROM client_order/'), ['hosting', 10])
        ->andReturn([['id' => 50, 'service_id' => 10]]);
    $connection->shouldNotReceive('fetchAssociative');
    $connection->shouldNotReceive('fetchOne');

    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getBatchForApi')
        ->once()
        ->with([50], Mockery::type(Box\Mod\Staff\Entity\Admin::class))
        ->andReturn([[
            'id' => 50,
            'service_id' => 10,
            'title' => 'Example hosting',
            'client' => ['id' => 7, 'email' => 'client@example.com'],
        ]]);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderService]));
    $service->setDi($di);

    $admin = \Tests\Helpers\admin();
    $result = $service->getAccountsBatchForApi([$account], $admin);

    expect($result[0])
        ->toMatchArray([
            'id' => 10,
            'server_id' => 2,
            'plan_id' => 3,
            'client' => ['id' => 7, 'email' => 'client@example.com'],
            'order' => ['id' => 50, 'service_id' => 10, 'title' => 'Example hosting'],
        ])
        ->and($result[0]['order'])->not->toHaveKey('client');
});

test('batch returns hosting accounts without orders', function (): void {
    $service = new Service();
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([]);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->getAccountsBatchForApi([[
        'id' => 10,
        'sld' => 'example',
        'tld' => '.com',
        'client_id' => 7,
        'service_hosting_server_id' => 2,
        'service_hosting_hp_id' => 3,
        'reseller' => 0,
    ]]);

    expect($result[0]['order'])->toBeNull()
        ->and($result[0])->not->toHaveKey('client');
});

test('validate order data', function (string $field, string $exceptionMessage, int $excCode): void {
    $service = new Service();
    $data = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];

    unset($data[$field]);

    try {
        $service->validateOrderData($data);
        expect(true)->toBeFalse('Expected FOSSBilling\Exception was not thrown.');
    } catch (FOSSBilling\Exception $e) {
        expect($e->getMessage())->toBe($exceptionMessage);
        expect($e->getCode())->toBe($excCode);
    }
})->with([
    ['server_id', 'Hosting product is not configured completely. Configure server for hosting product.', 701],
    ['hosting_plan_id', 'Hosting product is not configured completely. Configure hosting plan for hosting product.', 702],
    ['sld', 'Domain name is invalid.', 703],
    ['tld', 'Domain extension is invalid.', 704],
]);

test('action create', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['client_id' => 5]);
    $confArr = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];
    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn($confArr);

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, $confArr['server_id']);
    $hostingServerModel->setIp('1.1.1.1');
    $hostingPlansModel = new ServiceHostingHp();
    setEntityId($hostingPlansModel, $confArr['hosting_plan_id']);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn($hostingServerModel);
    $serverRepo->shouldIgnoreMissing();

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn($hostingPlansModel);
    $hpRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $emMock->shouldReceive('getRepository')->with(ServiceHostingHp::class)->andReturn($hpRepo);
    $emMock->shouldReceive('persist')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->action_create($orderModel);

    expect($result)->toBeInstanceOf(ServiceHosting::class);
    expect($result->getServiceHostingServerId())->toBe($confArr['server_id']);
    expect($result->getServiceHostingHpId())->toBe($confArr['hosting_plan_id']);
    expect($result->getSld())->toBe($confArr['sld']);
    expect($result->getTld())->toBe($confArr['tld']);
});

test('action activate creates the account when it has not been provisioned yet', function (): void {
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();
    $model->setServiceHostingServerId(1);
    $model->setSld('example');
    $model->setTld('.com');

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class)->shouldIgnoreMissing();
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('getPasswordLength')->atLeast()->once()->andReturn(12);
    $serverManagerMock->shouldReceive('generateUsername')->atLeast()->once()->with('example.com')->andReturn('example');

    $adapterMock = Mockery::mock('\Server_Manager_Custom');
    $adapterMock->shouldReceive('createAccount')->once();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);
    $serviceMock->shouldReceive('_getAM')->once()->andReturn([$adapterMock, new Server_Account()]);
    $serviceMock->setDi($di);

    $result = $serviceMock->action_activate($orderModel);

    expect($result)->toBe(['username' => 'example'])
        ->and($model->getUsername())->toBe('example');
});

test('action activate does not recreate an account that was already provisioned', function (): void {
    // Regression test: if a previous activation attempt already created the
    // account on the server (its username was persisted), retrying must not
    // call createAccount() again - the account already exists remotely and
    // doing so only fails with a duplicate-account server error.
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();
    $model->setServiceHostingServerId(1);
    $model->setSld('example');
    $model->setTld('.com');
    $model->setUsername('example');

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class)->shouldIgnoreMissing();
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('getPasswordLength')->atLeast()->once()->andReturn(12);
    $serverManagerMock->shouldNotReceive('generateUsername');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);
    $serviceMock->shouldNotReceive('_getAM');
    $serviceMock->setDi($di);

    $result = $serviceMock->action_activate($orderModel);

    expect($result)->toBe(['username' => 'example']);
});

test('action renew', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $hostingServiceModel = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($hostingServiceModel);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->action_renew($orderModel);
    expect($result)->toBeTrue();
});

test('action renew order without active service', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['id' => 1]);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_renew($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action suspend', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('suspendAccount')->once()->with(Mockery::on(
        fn (Server_Account $account): bool => $account->getNote() === 'Non-payment'
    ));
    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_suspend($orderModel, 'Non-payment');
    expect($result)->toBeTrue();
});

test('action suspend order without active service', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['id' => 1]);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_suspend($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action unsuspend', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('unsuspendAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_unsuspend($orderModel);
    expect($result)->toBeTrue();
});

test('action unsuspend order without active service', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['id' => 1]);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_unsuspend($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action cancel', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('cancelAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_cancel($orderModel);
    expect($result)->toBeTrue();
});

test('action cancel order without active service', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['id' => 1]);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_cancel($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action delete', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('action_cancel')->atLeast()->once();

    $serviceMock->setDi($di);
    $serviceMock->action_delete($orderModel);
});

test('change account plan', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();
    $modelHp = new ServiceHostingHp();
    setEntityId($modelHp, 2);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountPackage')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);
    $serviceMock->shouldReceive('getServerPackage')->atLeast()->once()->andReturn(new Server_Package());

    $serviceMock->setDi($di);
    $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
    expect($result)->toBeTrue();
});

test('change account username', function (): void {
    $service = new Service();
    $data = [
        'username' => 'u123456',
    ];

    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountUsername')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account username missing username', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();
    $data = [];

    expect(fn (): bool => $service->changeAccountUsername($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account username is missing or is invalid');
});

test('change account ip', function (): void {
    $service = new Service();
    $data = [
        'ip' => '1.1.1.1',
    ];

    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountIp')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account ip missing ip', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    expect(fn (): bool => $service->changeAccountIp($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account IP address is missing or is invalid');
});

test('change account domain', function (): void {
    $service = new Service();
    $data = [
        'tld' => 'com',
        'sld' => 'testingSld',
    ];

    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountDomain')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account domain missing params', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    expect(fn (): bool => $service->changeAccountDomain($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Domain SLD or TLD is missing');
});

test('change account password', function (): void {
    $service = new Service();
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $model = new ServiceHosting();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountPassword')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account password missing params', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    expect(fn (): bool => $service->changeAccountPassword($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account password is missing or is invalid');
});

test('sync', function (): void {
    $service = new Service();
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $accountObj = new Server_Account();
    $accountObj->setUsername('testUser1');
    $accountObj->setIp('1.1.1.1');

    $accountObj2 = new Server_Account();
    $accountObj2->setUsername('testUser2');
    $accountObj2->setIp('2.2.2.2');

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('synchronizeAccount')->atLeast()->once()->andReturn($accountObj2);

    $AMresultArray = [$serverManagerMock, $accountObj];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->sync($orderModel, $model);
    expect($result)->toBeTrue();
});

test('to api array', function (): void {
    $service = new Service();
    $model = new ServiceHosting();
    $model->setServiceHostingServerId(1);
    $model->setServiceHostingHpId(2);

    $hostingServer = new ServiceHostingServer();
    setEntityId($hostingServer, 1);
    $hostingServer->setManager('Custom');
    $hostingHp = new ServiceHostingHp();
    setEntityId($hostingHp, 2);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn($hostingServer);
    $serverRepo->shouldIgnoreMissing();

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('find')->atLeast()->once()->andReturn($hostingHp);
    $hpRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $emMock->shouldReceive('getRepository')->with(ServiceHostingHp::class)->andReturn($hpRepo);
    $emMock->shouldIgnoreMissing();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once();

    $serverManagerCustomMock = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomMock);

    $service->setDi($di);

    $result = $service->toApiArray($model, false, \Tests\Helpers\admin());
    expect($result)->toBeArray();
});

test('update', function (): void {
    $service = new Service();
    $data = [
        'username' => 'testUser',
        'ip' => '1.1.1.1',
    ];
    $model = new ServiceHosting();
    setEntityId($model, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $result = $service->update($model, $data);
    expect($result)->toBeTrue();
});

test('get server managers', function (): void {
    $service = new Service();
    $result = $service->getServerManagers();
    expect($result)->toBeArray();
});

test('get server manager config', function (): void {
    $service = new Service();
    $manager = 'Custom';

    $expected = [
        'label' => 'Custom Server Manager',
    ];

    $result = $service->getServerManagerConfig($manager);
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('get server pairs', function (): void {
    $service = new Service();
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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->atLeast()->once()->andReturn($queryResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->getServerPairs();
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('get server search query', function (): void {
    $service = new Service();
    $result = $service->getServersSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toEqual([]);
});

test('create server', function (): void {
    $service = new Service();
    $newId = 1;
    $persistedServer = null;
    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function (ServiceHostingServer $server) use ($newId, &$persistedServer): void {
        setEntityId($server, $newId);
        $persistedServer = $server;
    });
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $name = 'newSuperFastServer';
    $ip = '1.1.1.1';
    $manager = 'Custom';
    $data = [
        'active' => 1,
        'secure' => 0,
    ];
    $result = $service->createServer($name, $ip, $manager, $data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
    expect($persistedServer)->toBeInstanceOf(ServiceHostingServer::class);
    expect($persistedServer->isActive())->toBeTrue();
    expect($persistedServer->isSecure())->toBeFalse();
    expect($persistedServer->getMaxAccounts())->toBeNull();
});

test('delete server', function (): void {
    $service = new Service();
    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $result = $service->deleteServer($hostingServerModel);
    expect($result)->toBeTrue();
});

test('update server', function (): void {
    $service = new Service();
    $data = [
        'name' => 'newName',
        'ip' => '1.1.1.1',
        'hostname' => 'unknownStar',
        'active' => 1,
        'max_accounts' => '12',
        'status_url' => 'na',
        'ns1' => 'ns1.testserver.eu',
        'ns2' => 'ns2.testserver.eu',
        'ns3' => 'ns3.testserver.eu',
        'ns4' => 'ns4.testserver.eu',
        'manager' => 'Custom',
        'username' => 'testingJohn',
        'password' => 'hardToGuess',
        'accesshash' => 'secret',
        'secure' => 0,
    ];

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $di['loggedin_admin'] = \Tests\Helpers\admin(['id' => 7]);

    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
    expect($hostingServerModel->isActive())->toBeTrue();
    expect($hostingServerModel->isSecure())->toBeFalse();
    expect($hostingServerModel->getMaxAccounts())->toBe(12);
});

test('get server manager', function (): void {
    $service = new Service();
    $hostingServerModel = new ServiceHostingServer();
    $hostingServerModel->setManager('Custom');

    $serverManagerCustom = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustom);
    $service->setDi($di);

    $result = $service->getServerManager($hostingServerModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('get server manager manager not defined', function (): void {
    $service = new Service();
    $hostingServerModel = new ServiceHostingServer();

    expect(fn () => $service->getServerManager($hostingServerModel))
        ->toThrow(FOSSBilling\Exception::class);
});

test('get server manager server manager invalid', function (): void {
    $service = new Service();
    $hostingServerModel = new ServiceHostingServer();
    $hostingServerModel->setManager('Custom');

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
    $service->setDi($di);

    expect(fn () => $service->getServerManager($hostingServerModel))
        ->toThrow(FOSSBilling\Exception::class, 'Server manager Custom is invalid.');
});

test('test connection', function (): void {
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('testConnection')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);

    $hostingServerModel = new ServiceHostingServer();
    $result = $serviceMock->testConnection($hostingServerModel);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('get hp pairs', function (): void {
    $service = new Service();
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

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->atLeast()->once()->andReturn($queryResult);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->getHpPairs();
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('get hp search query', function (): void {
    $service = new Service();
    $result = $service->getHpSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toEqual([]);
});

test('delete hp', function (): void {
    $service = new Service();
    $model = new ServiceHostingHp();
    setEntityId($model, 1);

    $repo = Mockery::mock(ServiceHostingRepository::class);
    $repo->shouldReceive('findOneBy')->atLeast()->once()->andReturn(null);
    $repo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')->with(ServiceHosting::class)->andReturn($repo);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $result = $service->deleteHp($model);
    expect($result)->toBeTrue();
});

test('to hosting hp api array', function (): void {
    $service = new Service();
    $model = new ServiceHostingHp();

    $result = $service->toHostingHpApiArray($model);
    expect($result)->toBeArray();
    expect($model->getConfig())->toBeNull();
});

test('update hp', function (): void {
    $service = new Service();
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

    $model = new ServiceHostingHp();
    setEntityId($model, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updateHp($model, $data);
    expect($result)->toBeTrue();
});

test('create hp', function (array $data, string $expectedBandwidth, string $expectedMaxFtp): void {
    $service = new Service();
    $newId = 1;
    $persistedHp = null;

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('persist')->atLeast()->once()->andReturnUsing(function (ServiceHostingHp $hp) use ($newId, &$persistedHp): void {
        setEntityId($hp, $newId);
        $persistedHp = $hp;
    });
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->createHp('Free Plan', $data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
    expect($persistedHp)->toBeInstanceOf(ServiceHostingHp::class);
    expect($persistedHp->getBandwidth())->toBe($expectedBandwidth);
    expect($persistedHp->getMaxFtp())->toBe($expectedMaxFtp);
})->with([
    'provided optional limits' => [
        [
            'bandwidth' => '2048',
            'quota' => '4096',
            'max_addon' => '2',
            'max_park' => '2',
            'max_sub' => '2',
            'max_pop' => '2',
            'max_sql' => '2',
            'max_ftp' => '2',
        ],
        '2048',
        '2',
    ],
    'omitted optional limits' => [
        [],
        '1048576',
        '1',
    ],
]);

test('get server package', function (): void {
    $service = new Service();
    $model = new ServiceHostingHp();
    $model->setConfig('{}');

    $di = container();

    $service->setDi($di);
    $result = $service->getServerPackage($model);
    expect($result)->toBeInstanceOf('\Server_Package');
});

test('get server manager with log', function (): void {
    $hostingServerModel = new ServiceHostingServer();
    $hostingServerModel->setManager('Custom');

    $clientOrderModel = createEntity(Order::class);

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getLogger')->atLeast()->once()->andReturn(new Box_Log());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('get manager urls', function (): void {
    $hostingServerModel = new ServiceHostingServer();
    $hostingServerModel->setManager('Custom');

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('getLoginUrl')->atLeast()->once()->andReturn('/login');
    $serverManagerMock->shouldReceive('getResellerLoginUrl')->atLeast()->once()->andReturn('/admin/login');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeString();
});

test('get manager urls exception', function (): void {
    $hostingServerModel = new ServiceHostingServer();
    $hostingServerModel->setManager('Custom');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andThrow(new Exception('Controlled unit test exception'));

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeFalse();
    expect($result[1])->toBeFalse();
});

test('get free tlds free tlds are not set', function (): void {
    $service = new Service();
    $di = container();

    $tldModel = new Box\Mod\Servicedomain\Entity\Tld();
    $tldModel->setActive(true);
    $tldModel->setAllowRegister(true);

    $tldRepo = Mockery::mock(Box\Mod\Servicedomain\Repository\TldRepository::class);
    $tldRepo->shouldReceive('findBy')
        ->atLeast()->once()
        ->with(['active' => true, 'allowRegister' => true], ['id' => 'ASC'])
        ->andReturn([$tldModel]);
    $tldRepo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->atLeast()->once()
        ->with(Box\Mod\Servicedomain\Entity\Tld::class)
        ->andReturn($tldRepo);
    $emMock->shouldIgnoreMissing();

    $serviceDomainServiceMock = Mockery::mock(Box\Mod\Servicedomain\Service::class);
    $serviceDomainServiceMock->shouldReceive('tldToApiArray')->atLeast()->once()->andReturn(['tld' => '.com']);

    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(function (string $name) use ($serviceDomainServiceMock) {
        if (strtolower($name) === 'servicedomain') {
            return $serviceDomainServiceMock;
        }

        return Mockery::mock()->shouldIgnoreMissing();
    });

    $service->setDi($di);
    $product = new Box\Mod\Product\Entity\Product();
    $result = $service->getFreeTlds($product);
    expect($result)->toBeArray();
});

test('get free tlds', function (): void {
    $service = new Service();
    $config = [
        'free_tlds' => ['.com'],
    ];
    $di = container();

    $service->setDi($di);
    $product = new Box\Mod\Product\Entity\Product();
    $product->setConfig(json_encode($config));

    $result = $service->getFreeTlds($product);
    expect($result)->toBeArray();
    expect($result)->not->toBeEmpty();
});

test('get server manager secret fields', function (string $manager, array $expected): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);

    $result = $service->getServerManagerSecretFields($manager);

    sort($expected);
    $sorted = $result;
    sort($sorted);

    expect($sorted)->toBe($expected);
})->with([
    'WHM' => ['Whm', ['username', 'accesshash', 'password']],
    'Hestia' => ['Hestia', ['username', 'accesshash', 'password']],
    'CWP' => ['CWP', ['accesshash', 'password']],
    'DirectAdmin' => ['Directadmin', ['username', 'password', 'accesshash']],
    'Plesk' => ['Plesk', ['username', 'password', 'accesshash']],
    'unknown manager' => ['DoesNotExist', ['password', 'accesshash']],
]);

test('to hosting server api array masks secrets for an admin', function (): void {
    $service = new Service();

    $identity = \Tests\Helpers\admin();

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);
    $hostingServerModel->setName('Test');
    $hostingServerModel->setHostname('host.example.com');
    $hostingServerModel->setIp('127.0.0.1');
    $hostingServerModel->setManager('Whm');
    $hostingServerModel->setUsername('real-admin');
    $hostingServerModel->setAccesshash('super-secret-hash');

    $di = container();
    $service->setDi($di);

    $result = $service->toHostingServerApiArray($hostingServerModel, true, $identity);

    expect($result['username'])->toBeNull();
    expect($result['accesshash'])->toBeNull();
    expect($result['password'])->toBeNull();
    expect($result['username_set'])->toBeTrue();
    expect($result['accesshash_set'])->toBeTrue();
    expect($result['password_set'])->toBeFalse();
    expect($result['secret_fields'])->toContain('username');
    expect($result['secret_fields'])->toContain('accesshash');
});

test('to hosting server api array does not leak secrets to non-admin callers', function (): void {
    $service = new Service();

    $identity = createEntity(Box\Mod\Client\Entity\Client::class);

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);
    $hostingServerModel->setName('Test');
    $hostingServerModel->setIp('127.0.0.1');
    $hostingServerModel->setManager('Whm');
    $hostingServerModel->setAccesshash('super-secret-hash');

    $di = container();
    $service->setDi($di);

    $result = $service->toHostingServerApiArray($hostingServerModel, true, $identity);

    expect($result)->not->toHaveKey('username');
    expect($result)->not->toHaveKey('password');
    expect($result)->not->toHaveKey('accesshash');
    expect($result)->not->toHaveKey('secret_fields');
});

test('updateServer keeps the existing secret when the incoming value is blank', function (): void {
    $service = new Service();
    $data = [
        'name' => 'Test',
        'ip' => '127.0.0.1',
        'manager' => 'Whm',
        'username' => '',
        'accesshash' => Service::CREDENTIAL_KEEP_SENTINEL,
        'password' => '   ',
    ];

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);
    $hostingServerModel->setName('Test');
    $hostingServerModel->setIp('127.0.0.1');
    $hostingServerModel->setManager('Whm');
    $hostingServerModel->setUsername('real-admin');
    $hostingServerModel->setAccesshash('super-secret-hash');

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $di['loggedin_admin'] = \Tests\Helpers\admin(['id' => 7]);
    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
    expect($hostingServerModel->getUsername())->toBe('real-admin');
    expect($hostingServerModel->getAccesshash())->toBe('super-secret-hash');
    expect($hostingServerModel->getPassword())->toBeNull();
});

test('updateServer replaces the stored secret when a new value is submitted', function (): void {
    $service = new Service();
    $data = [
        'name' => 'Test',
        'ip' => '127.0.0.1',
        'manager' => 'Whm',
        'username' => 'new-admin',
        'accesshash' => 'new-hash',
    ];

    $hostingServerModel = new ServiceHostingServer();
    setEntityId($hostingServerModel, 1);
    $hostingServerModel->setName('Test');
    $hostingServerModel->setIp('127.0.0.1');
    $hostingServerModel->setManager('Whm');
    $hostingServerModel->setUsername('real-admin');
    $hostingServerModel->setAccesshash('old-hash');

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Box_Log();
    $di['loggedin_admin'] = \Tests\Helpers\admin(['id' => 7]);
    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
    expect($hostingServerModel->getUsername())->toBe('new-admin');
    expect($hostingServerModel->getAccesshash())->toBe('new-hash');
});

test('_performOnService rejects expired active order', function (): void {
    $service = new Service();

    $reflection = new ReflectionMethod($service, '_performOnService');

    $expiredOrder = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'expires_at' => date('Y-m-d H:i:s', time() - 3600),
    ]);

    expect($reflection->invoke($service, $expiredOrder))->toBeFalse();
});

test('_performOnService rejects suspended order', function (): void {
    $service = new Service();

    $reflection = new ReflectionMethod($service, '_performOnService');

    $suspendedOrder = createEntity(Order::class, [
        'status' => Order::STATUS_SUSPENDED,
    ]);

    expect($reflection->invoke($service, $suspendedOrder))->toBeFalse();
});

test('_performOnService accepts active order with future expires_at', function (): void {
    $service = new Service();

    $reflection = new ReflectionMethod($service, '_performOnService');

    $activeOrder = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'expires_at' => date('Y-m-d H:i:s', time() + 86400),
    ]);

    expect($reflection->invoke($service, $activeOrder))->toBeTrue();
});

test('_performOnService accepts one-time active order with null expires_at', function (): void {
    $service = new Service();

    $reflection = new ReflectionMethod($service, '_performOnService');

    $oneTimeOrder = createEntity(Order::class, [
        'status' => Order::STATUS_ACTIVE,
        'expires_at' => null,
    ]);

    expect($reflection->invoke($service, $oneTimeOrder))->toBeTrue();
});

test('clientSettableConfigKeys returns the hosting allowlist', function (): void {
    $service = new Service();
    $allowed = $service->clientSettableConfigKeys();

    expect($allowed)->toBeArray();
    // Client may choose billing period, domain options, quantity, and
    // whether multiple items are allowed in a single cart-add. Admin-
    // controlled fields (hosting_plan_id, server_id, reseller, ...) are
    // deliberately absent and will be stripped by the central filter in
    // Product\Service::prepareCartProductConfig before attachOrderConfig runs.
    expect($allowed)->toContain('period');
    expect($allowed)->toContain('domain');
    expect($allowed)->toContain('quantity');
    expect($allowed)->toContain('multiple');
    expect($allowed)->not->toContain('hosting_plan_id');
    expect($allowed)->not->toContain('server_id');
    expect($allowed)->not->toContain('reseller');
});
