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
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository;
use Box\Mod\Servicehosting\Service;
use Doctrine\DBAL\Connection;
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

    $connection = Mockery::mock(Connection::class);
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
    $connection = Mockery::mock(Connection::class);
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
    expect($result->getServiceHostingServer()?->getId())->toBe($confArr['server_id']);
    expect($result->getServiceHostingHp()?->getId())->toBe($confArr['hosting_plan_id']);
    expect($result->getSld())->toBe($confArr['sld']);
    expect($result->getTld())->toBe($confArr['tld']);
});

test('action activate creates the account when it has not been provisioned yet', function (): void {
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();
    $model->setServiceHostingServer(new ServiceHostingServer());
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
    $adapterMock->shouldReceive('createAccount')->once()->andReturnTrue();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);
    $serviceMock->shouldReceive('_getAM')->once()->andReturn([$adapterMock, new Server_Account()]);
    $serviceMock->setDi($di);

    $result = $serviceMock->action_activate($orderModel);

    expect($result)->toBe(['username' => 'example'])
        ->and($model->getUsername())->toBe('example');
});

test('action activate skips provisioning for existing or imported accounts', function (bool $import): void {
    $orderModel = createEntity(Order::class);

    $model = new ServiceHosting();
    $model->setServiceHostingServer(new ServiceHostingServer());
    $model->setSld('example');
    $model->setTld('.com');
    if (!$import) {
        $model->setUsername('example');
    }

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn(['import' => $import, 'username' => 'example']);

    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class)->shouldIgnoreMissing();
    $serverRepo->shouldReceive('find')->atLeast()->once()->andReturn(new ServiceHostingServer());

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldNotReceive('getPasswordLength');
    $serverManagerMock->shouldNotReceive('generateUsername');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);
    $serviceMock->shouldNotReceive('_getAM');
    $serviceMock->setDi($di);

    $result = $serviceMock->action_activate($orderModel);

    expect($result)->toBe(['username' => 'example']);
})->with([true, false]);

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

test('action delete with force removes local service when remote cancel fails', function (): void {
    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('remove')->once()->with($model);
    $emMock->shouldReceive('flush')->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('action_cancel')->once()->andThrow(new FOSSBilling\Exception('WHM unreachable'));

    $serviceMock->setDi($di);
    $serviceMock->action_delete($orderModel, true);
});

test('action delete without force rethrows remote cancel failure', function (): void {
    $orderModel = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);
    $model = new ServiceHosting();

    $orderServiceMock = Mockery::mock(OrderService::class);
    $orderServiceMock->shouldReceive('getOrderService')->andReturn($model);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldNotReceive('remove');
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('action_cancel')->once()->andThrow(new FOSSBilling\Exception('WHM unreachable'));

    $serviceMock->setDi($di);

    expect(fn () => $serviceMock->action_delete($orderModel, false))->toThrow(FOSSBilling\Exception::class, 'WHM unreachable');
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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

    $serviceMock->setDi($di);

    $result = $serviceMock->sync($orderModel, $model);
    expect($result)->toBeTrue();
});

test('to api array', function (): void {
    $service = new Service();

    $hostingServer = new ServiceHostingServer();
    setEntityId($hostingServer, 1);
    $hostingServer->setManager('Custom');
    $hostingHp = new ServiceHostingHp();
    setEntityId($hostingHp, 2);

    $model = new ServiceHosting();
    $model->setServiceHostingServer($hostingServer);
    $model->setServiceHostingHp($hostingHp);

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
    $di['logger'] = new FOSSBilling\Logger();
    $service->setDi($di);

    $result = $service->update($model, $data);
    expect($result)->toBeTrue();
});

test('get server managers', function (): void {
    $service = new Service();
    $result = $service->getServerManagers();
    expect($result)->toBeArray();
});

test('get server managers skips managers without a usable config', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getServerManagerConfig')->andReturnUsing(
        fn ($manager) => $manager === 'Custom' ? [] : ['label' => $manager]
    );

    $result = $service->getServerManagers();

    expect($result)->toBeArray();
    expect($result)->not->toHaveKey('Custom');
    expect($result)->not->toBeEmpty();
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

    $connection = Mockery::mock(Connection::class);
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

test('get server search query applies allowlisted sort', function (): void {
    $service = new Service();

    [$query] = $service->getServersSearchQuery(['sort' => 'name', 'direction' => 'desc']);
    expect($query)->toContain('ORDER BY name DESC, id DESC');

    [$pkQuery] = $service->getServersSearchQuery(['sort' => 'id', 'direction' => 'desc']);
    expect($pkQuery)->toContain('ORDER BY id DESC');
    expect($pkQuery)->not->toContain('id DESC, id DESC');

    [$defaultQuery] = $service->getServersSearchQuery([]);
    expect($defaultQuery)->toContain('ORDER BY id ASC');

    [$invalidQuery] = $service->getServersSearchQuery(['sort' => 'password']);
    expect($invalidQuery)->toContain('ORDER BY id ASC');
    expect($invalidQuery)->not->toContain('password');
});

test('get accounts search query applies allowlisted sort', function (): void {
    $service = new Service();

    [$query, $params] = $service->getAccountsSearchQuery(['server_id' => 3, 'sort' => 'username']);
    expect($query)->toContain('WHERE service_hosting_server_id = :server_id');
    expect($params)->toEqual(['server_id' => 3]);
    expect($query)->toContain('ORDER BY username ASC, id ASC');

    [$pkQuery] = $service->getAccountsSearchQuery(['sort' => 'id', 'direction' => 'desc']);
    expect($pkQuery)->toContain('ORDER BY id DESC');
    expect($pkQuery)->not->toContain('id DESC, id DESC');

    [$defaultQuery] = $service->getAccountsSearchQuery([]);
    expect($defaultQuery)->toContain('ORDER BY id ASC');

    [$invalidQuery] = $service->getAccountsSearchQuery(['sort' => 'pass']);
    expect($invalidQuery)->toContain('ORDER BY id ASC');
    expect($invalidQuery)->not->toContain('pass');
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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();
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
    $di['logger'] = new FOSSBilling\Logger();
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

    $connection = Mockery::mock(Connection::class);
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

test('get hp search query applies allowlisted sort', function (): void {
    $service = new Service();

    [$query] = $service->getHpSearchQuery(['sort' => 'name', 'direction' => 'desc']);
    expect($query)->toContain('ORDER BY name DESC, id DESC');

    [$pkQuery] = $service->getHpSearchQuery(['sort' => 'id', 'direction' => 'desc']);
    expect($pkQuery)->toContain('ORDER BY id DESC');
    expect($pkQuery)->not->toContain('id DESC, id DESC');

    [$defaultQuery] = $service->getHpSearchQuery([]);
    expect($defaultQuery)->toContain('ORDER BY id asc');

    [$invalidQuery] = $service->getHpSearchQuery(['sort' => 'config']);
    expect($invalidQuery)->toContain('ORDER BY id asc');
});

test('delete hp', function (): void {
    $service = new Service();
    $model = new ServiceHostingHp();
    setEntityId($model, 1);

    $connectionMock = Mockery::mock(Connection::class);
    $connectionMock->shouldReceive('fetchFirstColumn')->atLeast()->once()->andReturn([]);

    $repo = Mockery::mock(ServiceHostingRepository::class);
    $repo->shouldReceive('findOneBy')->andReturn(null);
    $repo->shouldIgnoreMissing();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);
    $emMock->shouldReceive('getRepository')->with(ServiceHosting::class)->andReturn($repo);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $service->setDi($di);

    $result = $service->deleteHp($model);
    expect($result)->toBeTrue();
});

test('delete hp refuses orphaned usages without detaching', function (): void {
    $service = new Service();
    $model = new ServiceHostingHp();
    setEntityId($model, 1);

    $connectionMock = Mockery::mock(Connection::class);
    $connectionMock->shouldReceive('fetchFirstColumn')->once()->andReturn([5]);
    $connectionMock->shouldReceive('fetchFirstColumn')->once()->andReturn([]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connectionMock);
    $emMock->shouldNotReceive('remove');
    $emMock->shouldIgnoreMissing();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new FOSSBilling\Logger();
    $service->setDi($di);

    expect(fn () => $service->deleteHp($model))->toThrow(FOSSBilling\InformationException::class, 'orphaned');
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
    $di['logger'] = new FOSSBilling\Logger();

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
    $di['logger'] = new FOSSBilling\Logger();

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
    $orderServiceMock->shouldReceive('getLogger')->atLeast()->once()->andReturn(new FOSSBilling\Logger());

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

    $di = container();
    $serviceMock->setDi($di);

    expect($serviceMock->getManagerUrls($hostingServerModel))->toBe(['/login', '/admin/login'])
        ->and($di['logger']->calls)->toBe([]);
});

test('get manager urls logs adapter failures and returns unavailable links', function (Throwable $error): void {
    $hostingServerModel = createEntity(ServiceHostingServer::class, ['id' => 42]);
    $hostingServerModel->setManager('Custom');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->once()->with($hostingServerModel)->andThrow($error);

    $di = container();
    $serviceMock->setDi($di);

    expect($serviceMock->getManagerUrls($hostingServerModel))->toBe([false, false])
        ->and($di['logger']->calls)->toBe([
            [
                'method' => 'error',
                'params' => ['Failed to retrieve control panel URLs.', [
                    'server_id' => 42,
                    'manager' => 'Custom',
                    'exception_class' => $error::class,
                    'exception_file' => $error->getFile(),
                    'exception_line' => $error->getLine(),
                ]],
            ],
        ]);
})->with([
    'exception' => [new Exception('Adapter credentials: secret')],
    'parse error' => [new ParseError('Adapter credentials: secret')],
    'type error' => [new TypeError('Adapter credentials: secret')],
]);

test('get manager urls returns unavailable links when reseller URL generation fails', function (): void {
    $server = new ServiceHostingServer();
    $server->setManager('Custom');
    $manager = Mockery::mock('\Server_Manager_Custom');
    $manager->shouldReceive('getLoginUrl')->once()->with(null)->andReturn('/login');
    $manager->shouldReceive('getResellerLoginUrl')->once()->with(null)->andThrow(new TypeError('Controlled URL failure'));

    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('getServerManager')->once()->with($server)->andReturn($manager);
    $di = container();
    $service->setDi($di);

    expect($service->getManagerUrls($server))->toBe([false, false])
        ->and($di['logger']->calls)->toHaveCount(1)
        ->and($di['logger']->calls[0]['method'])->toBe('error');
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
    $product = new Product();
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
    $product = new Product();
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
    $di['logger'] = new FOSSBilling\Logger();
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
    $di['logger'] = new FOSSBilling\Logger();
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

test('validateOrderData rejects admin-controlled values differing from product config', function (string $field, mixed $injectedValue): void {
    $service = new Service();
    $product = createEntity(Product::class, [
        'config' => json_encode(['server_id' => 1, 'hosting_plan_id' => 2, 'reseller' => false]),
    ]);
    $data = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'reseller' => false,
        'sld' => 'great',
        'tld' => 'com',
    ];

    $data[$field] = $injectedValue;

    try {
        $service->validateOrderData($data, $product);
        expect(true)->toBeFalse('Expected FOSSBilling\InformationException was not thrown.');
    } catch (FOSSBilling\InformationException $e) {
        expect($e->getMessage())->toBe('The requested configuration does not match the selected product.');
        expect($e->getCode())->toBe(705);
    }
})->with([
    ['hosting_plan_id', 999],
    ['server_id', 42],
    ['reseller', true],
]);

test('validateOrderData accepts values matching product config', function (): void {
    $service = new Service();
    $product = createEntity(Product::class, [
        'config' => json_encode(['server_id' => 1, 'hosting_plan_id' => 2, 'reseller' => false]),
    ]);
    $data = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'reseller' => false,
        'sld' => 'great',
        'tld' => 'com',
    ];

    $service->validateOrderData($data, $product);
    expect(true)->toBeTrue();
});

test('getOrderableHpPairs returns only plans referenced by enabled products', function (): void {
    $service = new Service();

    $hostingProduct = createEntity(Product::class, [
        'config' => json_encode(['server_id' => 1, 'hosting_plan_id' => 3]),
    ]);
    $unrelatedProduct = createEntity(Product::class, [
        'config' => '{}',
    ]);

    $plan = new ServiceHostingHp();
    setEntityId($plan, 3);
    $plan->setName('Gold');

    $productRepo = Mockery::mock(Box\Mod\Product\Repository\ProductRepository::class);
    $productRepo->shouldReceive('findBy')
        ->once()
        ->with([
            'type' => Box\Mod\Product\Service::HOSTING,
            'active' => true,
            'status' => 'enabled',
            'isAddon' => false,
        ])
        ->andReturn([$hostingProduct, $unrelatedProduct]);

    $hpRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $hpRepo->shouldReceive('findBy')
        ->once()
        ->with(['id' => [3]])
        ->andReturn([$plan]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Product::class)
        ->andReturn($productRepo);
    $emMock->shouldReceive('getRepository')
        ->with(ServiceHostingHp::class)
        ->andReturn($hpRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->getOrderableHpPairs())->toBe([3 => 'Gold']);
});

test('getOrderableHpPairs returns empty array when no products reference plans', function (): void {
    $service = new Service();

    $productRepo = Mockery::mock(Box\Mod\Product\Repository\ProductRepository::class);
    $productRepo->shouldReceive('findBy')->once()->andReturn([]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Product::class)
        ->andReturn($productRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    expect($service->getOrderableHpPairs())->toBe([]);
});

test('get domain product from config returns false when no domain action is supplied', function (): void {
    $service = new Service();
    $service->setDi(container());

    $product = createEntity(Product::class, [
        'title' => 'Hosting',
        'config' => json_encode(['server_id' => 1, 'hosting_plan_id' => 2]),
    ]);
    $data = ['server_id' => 1, 'hosting_plan_id' => 2, 'sld' => 'example', 'tld' => '.com'];

    expect($service->getDomainProductFromConfig($product, $data))->toBeFalse();
});

test('cart product title falls back when owndomain fields are missing', function (): void {
    $service = new Service();
    $di = container();
    $di['validator'] = new FOSSBilling\Validate();
    $service->setDi($di);

    $product = createEntity(Product::class, [
        'title' => 'Hosting',
        'config' => json_encode(['domain' => ['action' => 'owndomain']]),
    ]);

    expect($service->getCartProductTitle($product, []))->toBe('Hosting');
});

test('refreshes provisioning defaults while preserving order choices', function (bool $hasService): void {
    $original = ['server_id' => 1, 'hosting_plan_id' => 2, 'sld' => 'example', 'tld' => '.com', 'password' => 'secret', 'import' => false, 'period' => '1Y'];
    $order = createEntity(Order::class, ['id' => 50, 'product_id' => 9, 'service_type' => 'hosting', 'status' => Order::STATUS_FAILED_SETUP, 'config' => json_encode($original)]);
    $product = createEntity(Product::class, ['config' => json_encode(['server_id' => 3, 'hosting_plan_id' => 4, 'reseller' => true, 'period' => '1M'])]);
    $server = createEntity(ServiceHostingServer::class, ['id' => 3, 'ip' => '192.0.2.3']);
    $plan = createEntity(ServiceHostingHp::class, ['id' => 4]);
    $model = $hasService ? new ServiceHosting() : null;
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturn($model);
    $orderService->shouldReceive('getConfig')->once()->with($order)->andReturn($original);
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->once()->with(3)->andReturn($server);
    $planRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $planRepo->shouldReceive('find')->once()->with(4)->andReturn($plan);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('find')->once()->with(Product::class, 9)->andReturn($product);
    $em->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $em->shouldReceive('getRepository')->with(ServiceHostingHp::class)->andReturn($planRepo);
    $em->shouldReceive('flush')->once();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $service = new Service();
    $service->setDi($di);

    expect($service->refreshProductConfiguration($order))->toBeTrue();
    expect(json_decode($order->getConfig(), true))->toBe(array_replace($original, ['server_id' => 3, 'hosting_plan_id' => 4, 'reseller' => true]));
    if ($hasService) {
        expect($model->getServiceHostingServer())->toBe($server);
        expect($model->getServiceHostingHp())->toBe($plan);
        expect($model->getIp())->toBe('192.0.2.3');
        expect($model->isReseller())->toBeTrue();
    }
})->with([true, false]);

test('rejects refreshing active hosting orders', function (): void {
    $service = new Service();
    $order = createEntity(Order::class, ['service_type' => 'hosting', 'status' => Order::STATUS_ACTIVE]);
    expect(fn () => $service->refreshProductConfiguration($order))->toThrow(FOSSBilling\InformationException::class);
});

test('rejects refreshing a failed order with a saved username', function (): void {
    $order = createEntity(Order::class, ['service_type' => 'hosting', 'status' => Order::STATUS_FAILED_SETUP]);
    $model = createEntity(ServiceHosting::class, ['username' => 'existing']);
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturn($model);
    $di = container();
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $service = new Service();
    $service->setDi($di);
    expect(fn () => $service->refreshProductConfiguration($order))->toThrow(FOSSBilling\InformationException::class);
});

test('previews effective service values and product defaults without writing', function (bool $hasUsername): void {
    $order = createEntity(Order::class, ['product_id' => 9, 'service_type' => 'hosting', 'status' => Order::STATUS_FAILED_SETUP]);
    $server = createEntity(ServiceHostingServer::class, ['id' => 3, 'name' => 'New server']);
    $plan = createEntity(ServiceHostingHp::class, ['id' => 4, 'name' => 'New plan']);
    $oldServer = createEntity(ServiceHostingServer::class, ['id' => 1, 'name' => 'Old server']);
    $oldPlan = createEntity(ServiceHostingHp::class, ['id' => 2, 'name' => 'Old plan']);
    $model = new ServiceHosting();
    $model->setServiceHostingServer($oldServer);
    $model->setServiceHostingHp($oldPlan);
    $model->setReseller(false);
    if ($hasUsername) {
        $model->setUsername('possibly-provisioned');
    }
    $product = createEntity(Product::class, ['config' => json_encode(['server_id' => 3, 'hosting_plan_id' => 4, 'reseller' => true])]);
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturn($model);
    // The service values take precedence over an independently edited order config.
    $orderService->shouldReceive('getConfig')->once()->with($order)->andReturn(['server_id' => 3, 'hosting_plan_id' => 4]);
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->with(1)->andReturn($oldServer);
    $serverRepo->shouldReceive('find')->with(3)->andReturn($server);
    $planRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $planRepo->shouldReceive('find')->with(2)->andReturn($oldPlan);
    $planRepo->shouldReceive('find')->with(4)->andReturn($plan);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('find')->once()->with(Product::class, 9)->andReturn($product);
    $em->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $em->shouldReceive('getRepository')->with(ServiceHostingHp::class)->andReturn($planRepo);
    $em->shouldNotReceive('flush');
    $em->shouldNotReceive('persist');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $service = new Service();
    $service->setDi($di);

    $preview = $service->previewProductConfiguration($order);
    expect($preview['current']['server_name'])->toBe('Old server');
    expect($preview['current']['hosting_plan_name'])->toBe('Old plan');
    expect($preview['current']['reseller'])->toBeFalse();
    expect($preview['proposed']['server_name'])->toBe('New server');
    expect($preview['proposed']['hosting_plan_name'])->toBe('New plan');
    expect($preview['proposed']['reseller'])->toBeTrue();
    expect($preview['has_saved_username'])->toBe($hasUsername);
    expect($preview['can_refresh'])->toBe(!$hasUsername);
})->with([true, false]);

test('disables the preview when product provisioning defaults are missing', function (): void {
    $order = createEntity(Order::class, ['product_id' => 9, 'service_type' => 'hosting', 'status' => Order::STATUS_PENDING_SETUP]);
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->once()->with($order)->andReturnNull();
    $orderService->shouldReceive('getConfig')->once()->with($order)->andReturn(['server_id' => 1, 'hosting_plan_id' => 2]);
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->andReturnNull();
    $planRepo = Mockery::mock(ServiceHostingHpRepository::class);
    $planRepo->shouldReceive('find')->andReturnNull();
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('find')->once()->with(Product::class, 9)->andReturnNull();
    $em->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $em->shouldReceive('getRepository')->with(ServiceHostingHp::class)->andReturn($planRepo);
    $em->shouldNotReceive('flush');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $service = new Service();
    $service->setDi($di);

    $preview = $service->previewProductConfiguration($order);
    expect($preview['current']['server_id'])->toBe(1);
    expect($preview['current']['hosting_plan_id'])->toBe(2);
    expect($preview['current']['server_name'])->toBeNull();
    expect($preview['proposed']['valid'])->toBeFalse();
    expect($preview['can_refresh'])->toBeFalse();
});

test('hosting provisioning retries failed steps without saving passwords', function (string $failedStep, bool $configuredPassword, bool $uncancel): void {
    $order = createEntity(Order::class, ['id' => 1, 'service_type' => 'hosting', 'status' => $uncancel ? Order::STATUS_CANCELED : Order::STATUS_PENDING_SETUP]);
    $model = new ServiceHosting();
    $model->setServiceHostingServer(new ServiceHostingServer());
    $model->setSld('example')->setTld('.com')->setReseller(true);
    if ($uncancel) {
        $model->setUsername('example');
    }
    $snapshot = null;
    $em = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('flush')->andReturnUsing(function () use (&$model, &$snapshot): void {
        expect($model->getPass())->toBe('********');
        $snapshot = clone $model;
    });

    $actions = [];
    $failuresRemaining = 2;
    $client = new Symfony\Component\HttpClient\MockHttpClient(function (string $method, string $url, array $options) use (&$actions, &$failuresRemaining, &$model, $failedStep, $configuredPassword): Symfony\Component\HttpClient\Response\MockResponse {
        $action = basename((string) parse_url($url, PHP_URL_PATH));
        $actions[] = $action;
        // Even a flush inside the adapter must not save a transient password.
        expect($model->getPass())->toBe('********');
        if ($action === 'listpkgs') {
            $response = ['package' => []];
        } elseif ($action === $failedStep && $failuresRemaining-- > 0) {
            $response = ['status' => 0, 'statusmsg' => 'Permission denied'];
        } elseif ($action === 'createacct') {
            parse_str((string) $options['body'], $fields);
            expect($fields['password'])->toBe($configuredPassword ? 'configured-secret' : 'generated-secret');
            $response = ['result' => [['status' => 1]]];
        } else {
            $response = ['status' => 1];
        }

        return new Symfony\Component\HttpClient\Response\MockResponse(json_encode($response));
    });
    $manager = new class(['host' => 'whm.example.com', 'username' => 'admin', 'password' => 'secret'], $client) extends Server_Manager_Whm {
        public function __construct(array $options, private readonly Symfony\Contracts\HttpClient\HttpClientInterface $client)
        {
            parent::__construct($options);
        }

        public function getHttpClient(): Symfony\Contracts\HttpClient\HttpClientInterface
        {
            return $this->client;
        }

        public function generateUsername(string $domain): string
        {
            return 'example';
        }
    };
    $manager->setLog(new Psr\Log\NullLogger());
    $di = container();
    $di['em'] = $em;
    $tools = Mockery::mock(FOSSBilling\Tools::class);
    $tools->shouldReceive('generatePassword')->times($configuredPassword ? 0 : ($failedStep === 'createacct' ? 3 : 1))->andReturn('generated-secret');
    $di['tools'] = $tools;
    $orderService = Mockery::mock(OrderService::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $orderService->shouldReceive('getOrderService')->andReturnUsing(function () use (&$model): ServiceHosting {
        return $model;
    });
    $orderService->shouldReceive('getConfig')->andReturn($configuredPassword ? ['password' => 'configured-secret'] : []);
    $orderService->shouldReceive('saveStatusChange')->andReturnNull();
    $orderService->setDi($di);
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $hosting = Mockery::mock(Service::class)->makePartial();
    $hosting->shouldReceive('getServerManager')->andReturn($manager);
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->andReturn(new ServiceHostingServer());
    $em->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $hosting->shouldReceive('_getAM')->times(3)->andReturnUsing(function () use (&$model, $manager): array {
        $account = (new Server_Account())
            ->setUsername($model->getUsername())->setPassword($model->getPass())
            ->setDomain('example.com')->setReseller(true)
            ->setClient((new Server_Client())->setEmail('client@example.com'))
            ->setPackage((new Server_Package())->setCustomValues(['acl' => 'reseller_basic']));

        return [$manager, $account];
    });
    $hosting->setDi($di);
    if ($uncancel) {
        $hosting->shouldReceive('action_create')->once()->andReturn($model);
    }
    $orderService->shouldReceive('_callOnService')->andReturnUsing(function () use ($hosting, $order, $uncancel) {
        if ($uncancel) {
            $hosting->action_uncancel($order);

            return [];
        }

        return $hosting->action_activate($order);
    });

    $provision = fn () => $uncancel ? $orderService->uncancelFromOrder($order) : $orderService->createFromOrder($order);

    for ($attempt = 0; $attempt < 2; ++$attempt) {
        expect($provision)->toThrow($failedStep === 'createacct' ? Server_Exception::class : Server_PartialProvisioningException::class);
        expect($order->getStatus())->toBe($uncancel ? Order::STATUS_CANCELED : Order::STATUS_FAILED_SETUP)
            ->and($snapshot->getUsername())->toBe($failedStep === 'createacct' ? null : 'example')
            ->and($snapshot->getProvisioningStep())->toBe($failedStep === 'createacct' ? null : $failedStep);
        $model = clone $snapshot;
    }

    $provision();
    expect($order->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($snapshot->getProvisioningStep())->toBeNull();
    $calls = array_count_values($actions);
    expect($calls['createacct'])->toBe($failedStep === 'createacct' ? 3 : 1)
        ->and($calls['setupreseller'])->toBe($failedStep === 'setupreseller' ? 3 : 1)
        ->and($calls['setacls'])->toBe($failedStep === 'setacls' ? 3 : 1);

    $model = clone $snapshot;
    $hosting->action_activate($order);
    expect(array_count_values($actions))->toBe($calls);
})->with([
    'account creation, configured password' => ['createacct', true, false],
    'account creation, generated password' => ['createacct', false, false],
    'reseller setup, configured password' => ['setupreseller', true, false],
    'reseller setup, generated password' => ['setupreseller', false, false],
    'ACL assignment, configured password' => ['setacls', true, false],
    'ACL assignment, generated password' => ['setacls', false, false],
    'uncancel reseller setup' => ['setupreseller', false, true],
    'uncancel ACL assignment' => ['setacls', false, true],
]);

test('uncancel does not save credentials when account creation fails', function (): void {
    $order = createEntity(Order::class);
    $model = (new ServiceHosting())->setServiceHostingServer(new ServiceHostingServer());
    $adapter = Mockery::mock(Server_Manager_Custom::class);
    $adapter->shouldReceive('getPasswordLength')->andReturn(12);
    $adapter->shouldReceive('createAccount')->once()->andReturnUsing(function (Server_Account $account) use ($model): never {
        expect($account->getPassword())->not->toBe('********')
            ->and($model->getPass())->toBe('********');

        throw new Server_Exception('Creation failed');
    });
    $serverRepo = Mockery::mock(ServiceHostingServerRepository::class);
    $serverRepo->shouldReceive('find')->andReturn(new ServiceHostingServer());
    $di = container();
    $di['em']->shouldReceive('getRepository')->with(ServiceHostingServer::class)->andReturn($serverRepo);
    $orderService = Mockery::mock(OrderService::class);
    $orderService->shouldReceive('getOrderService')->andReturn($model);
    $di['mod_service'] = $di->protect(fn () => $orderService);
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldReceive('action_create')->once()->andReturn($model);
    $service->shouldReceive('getServerManager')->andReturn($adapter);
    $service->shouldReceive('_getAM')->once()->andReturn([$adapter, (new Server_Account())->setUsername('example')]);
    $service->setDi($di);

    expect(fn () => $service->action_uncancel($order))->toThrow(Server_Exception::class)
        ->and($model->getPass())->toBe('********')
        ->and($model->getUsername())->toBeNull()
        ->and($model->getProvisioningStep())->toBeNull();
});

test('hosting recovery state survives schema upgrades and entity reloads', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $em = FOSSBilling\Doctrine\EntityManagerFactory::create($connection);
    FOSSBilling\Doctrine\SchemaInstaller::createSchema($em);
    $model = (new ServiceHosting())->setUsername('existing')->setPass('********');
    $em->persist($model);
    $em->flush();
    $id = $model->getId();
    $em->clear();

    $connection->executeStatement('ALTER TABLE service_hosting DROP COLUMN provisioning_step');
    FOSSBilling\Doctrine\SchemaSynchronizer::syncEntities($em, [ServiceHosting::class]);
    $model = $em->find(ServiceHosting::class, $id);
    expect($model->getUsername())->toBe('existing')
        ->and($model->getPass())->toBe('********')
        ->and($model->getProvisioningStep())->toBeNull();
    $model->setProvisioningStep('setacls');
    $em->flush();
    $em->clear();
    expect($em->find(ServiceHosting::class, $id)->getProvisioningStep())->toBe('setacls');
    $em->close();
});
