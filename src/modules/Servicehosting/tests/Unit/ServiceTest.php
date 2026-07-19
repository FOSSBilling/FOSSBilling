<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicehosting\Service;

use function Tests\Helpers\container;
use function Tests\Helpers\moduleService;

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->once()
        ->with(Mockery::pattern('/FROM client_order/'), ['hosting', 10])
        ->andReturn([['id' => 50, 'service_id' => 10]]);
    $dbMock->shouldNotReceive('findOne');
    $dbMock->shouldNotReceive('dispense');

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('getBatchForApi')
        ->once()
        ->with([50], Mockery::type(Model_Admin::class))
        ->andReturn([[
            'id' => 50,
            'service_id' => 10,
            'title' => 'Example hosting',
            'client' => ['id' => 7, 'email' => 'client@example.com'],
        ]]);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(moduleService(['order' => $orderService]));
    $service->setDi($di);

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
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
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')->once()->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
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
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $confArr = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getConfig')->atLeast()->once()->andReturn($confArr);

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->id = $confArr['server_id'];
    $hostingPlansModel = new Model_ServiceHostingHp();
    $hostingPlansModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingPlansModel->id = $confArr['hosting_plan_id'];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($hostingServerModel, $hostingPlansModel);

    $servhostingModel = new Model_ServiceHosting();
    $servhostingModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($servhostingModel);

    $newserviceHostingId = 4;
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn($newserviceHostingId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $service->action_create($orderModel);

    expect($servhostingModel->service_hosting_server_id)->toBe($confArr['server_id']);
    expect($servhostingModel->service_hosting_hp_id)->toBe($confArr['hosting_plan_id']);
    expect($servhostingModel->sld)->toBe($confArr['sld']);
    expect($servhostingModel->tld)->toBe($confArr['tld']);
});

test('action renew', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $hostingServiceModel = new Model_ServiceHosting();
    $hostingServiceModel->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($hostingServiceModel);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->action_renew($orderModel);
    expect($result)->toBeTrue();
});

test('action renew order without active service', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_renew($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action suspend', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('suspendAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_suspend($orderModel);
    expect($result)->toBeTrue();
});

test('action suspend order without active service', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_suspend($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action unsuspend', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_unsuspend($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action cancel', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturnNull();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    expect(fn (): bool => $service->action_cancel($orderModel))
        ->toThrow(FOSSBilling\Exception::class, sprintf('Order %d has no active service', $orderModel->id));
});

test('action delete', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());
    $orderModel->status = 'active';

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once()->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('action_cancel')->atLeast()->once();

    $serviceMock->setDi($di);
    $serviceMock->action_delete($orderModel);
});

test('change account plan', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $modelHp = new Model_ServiceHostingHp();
    $modelHp->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountUsername')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account username missing username', function (): void {
    $service = new Service();
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $data = [];

    expect(fn (): bool => $service->changeAccountUsername($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account username is missing or is invalid');
});

test('change account ip', function (): void {
    $service = new Service();
    $data = [
        'ip' => '1.1.1.1',
    ];

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountIp')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account ip missing ip', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    expect(fn (): bool => $service->changeAccountIp($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account IP address is missing or is invalid');
});

test('change account domain', function (): void {
    $service = new Service();
    $data = [
        'tld' => 'com',
        'sld' => 'testingSld',
    ];

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountDomain')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account domain missing params', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    expect(fn (): bool => $service->changeAccountDomain($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Domain SLD or TLD is missing');
});

test('change account password', function (): void {
    $service = new Service();
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountPassword')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new Server_Account()];
    $serviceMock->shouldReceive('_getAM')->atLeast()->once()->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('change account password missing params', function (): void {
    $service = new Service();
    $data = [];
    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    expect(fn (): bool => $service->changeAccountPassword($orderModel, $model, $data))
        ->toThrow(FOSSBilling\Exception::class, 'Account password is missing or is invalid');
});

test('sync', function (): void {
    $service = new Service();
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new Model_ClientOrder();
    $orderModel->loadBean(new Tests\Helpers\DummyBean());

    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $serviceMock->setDi($di);

    $result = $serviceMock->sync($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('to api array', function (): void {
    $service = new Service();
    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $hostingServer = new Model_ServiceHostingServer();
    $hostingServer->loadBean(new Tests\Helpers\DummyBean());
    $hostingServer->manager = 'Custom';
    $hostingHp = new Model_ServiceHostingHp();
    $hostingHp->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')->atLeast()->once()->andReturn($hostingServer, $hostingHp);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once();

    $serverManagerCustomMock = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomMock);

    $service->setDi($di);

    $result = $service->toApiArray($model, false, new Model_Admin());
    expect($result)->toBeArray();
});

test('update', function (): void {
    $service = new Service();
    $data = [
        'username' => 'testUser',
        'ip' => '1.1.1.1',
    ];
    $model = new Model_ServiceHosting();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')->atLeast()->once()->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
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
    $dbMock = Mockery::mock('\Box_Database');

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($hostingServerModel);

    $newId = 1;
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $name = 'newSuperFastServer';
    $ip = '1.1.1.1';
    $manager = 'Custom';
    $data = [];
    $result = $service->createServer($name, $ip, $manager, $data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('delete server', function (): void {
    $service = new Service();
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
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

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
});

test('get server manager', function (): void {
    $service = new Service();
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serverManagerCustom = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustom);
    $service->setDi($di);

    $result = $service->getServerManager($hostingServerModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('get server manager manager not defined', function (): void {
    $service = new Service();
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());

    expect(fn () => $service->getServerManager($hostingServerModel))
        ->toThrow(FOSSBilling\Exception::class);
});

test('get server manager server manager invalid', function (): void {
    $service = new Service();
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
    $service->setDi($di);

    expect(fn () => $service->getServerManager($hostingServerModel))
        ->toThrow(FOSSBilling\Exception::class, "Server manager {$hostingServerModel->manager} is invalid.");
});

test('test connection', function (): void {
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('testConnection')->atLeast()->once()->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);

    $hostingServerModel = new Model_ServiceHostingServer();
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

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')->atLeast()->once()->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
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
    $model = new Model_ServiceHostingHp();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $service->setDi($di);

    $result = $service->deleteHp($model);
    expect($result)->toBeTrue();
});

test('to hosting hp api array', function (): void {
    $service = new Service();
    $model = new Model_ServiceHostingHp();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->toHostingHpApiArray($model);
    expect($result)->toBeArray();
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

    $model = new Model_ServiceHostingHp();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->updateHp($model, $data);
    expect($result)->toBeTrue();
});

test('create hp', function (): void {
    $service = new Service();
    $model = new Model_ServiceHostingHp();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $newId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($model);
    $dbMock->shouldReceive('store')->atLeast()->once()->andReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();

    $service->setDi($di);

    $result = $service->createHp('Free Plan', []);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('get server package', function (): void {
    $service = new Service();
    $model = new Model_ServiceHostingHp();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->config = '{}';

    $di = container();

    $service->setDi($di);
    $result = $service->getServerPackage($model);
    expect($result)->toBeInstanceOf('\Server_Package');
});

test('get server manager with log', function (): void {
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $clientOrderModel = new Model_ClientOrder();
    $clientOrderModel->loadBean(new Tests\Helpers\DummyBean());

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom')->shouldIgnoreMissing();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('getServerManager')->atLeast()->once()->andReturn($serverManagerMock);

    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getLogger')->atLeast()->once()->andReturn(new Box_Log());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('get manager urls', function (): void {
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

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
    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

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

    $tldArray = ['tld' => '.com'];
    $serviceDomainServiceMock = Mockery::mock(Box\Mod\Servicedomain\Service::class);
    $serviceDomainServiceMock->shouldReceive('tldToApiArray')->atLeast()->once()->andReturn($tldArray);
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $serviceDomainServiceMock);

    $tldModel = new Model_Tld();
    $tldModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')->atLeast()->once()->andReturn([$tldModel]);
    $di['db'] = $dbMock;

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

    $identity = new Model_Admin();
    $identity->loadBean(new Tests\Helpers\DummyBean());

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->id = 1;
    $hostingServerModel->name = 'Test';
    $hostingServerModel->hostname = 'host.example.com';
    $hostingServerModel->ip = '127.0.0.1';
    $hostingServerModel->manager = 'Whm';
    $hostingServerModel->username = 'real-admin';
    $hostingServerModel->accesshash = 'super-secret-hash';

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

    $identity = new Model_Client();
    $identity->loadBean(new Tests\Helpers\DummyBean());

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->id = 1;
    $hostingServerModel->name = 'Test';
    $hostingServerModel->ip = '127.0.0.1';
    $hostingServerModel->manager = 'Whm';
    $hostingServerModel->accesshash = 'super-secret-hash';

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

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->id = 1;
    $hostingServerModel->name = 'Test';
    $hostingServerModel->ip = '127.0.0.1';
    $hostingServerModel->manager = 'Whm';
    $hostingServerModel->username = 'real-admin';
    $hostingServerModel->accesshash = 'super-secret-hash';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $di['loggedin_admin'] = (object) ['id' => 7];
    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
    expect($hostingServerModel->username)->toBe('real-admin');
    expect($hostingServerModel->accesshash)->toBe('super-secret-hash');
    expect($hostingServerModel->password)->toBeNull();
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

    $hostingServerModel = new Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new Tests\Helpers\DummyBean());
    $hostingServerModel->id = 1;
    $hostingServerModel->name = 'Test';
    $hostingServerModel->ip = '127.0.0.1';
    $hostingServerModel->manager = 'Whm';
    $hostingServerModel->username = 'real-admin';
    $hostingServerModel->accesshash = 'old-hash';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Box_Log();
    $di['loggedin_admin'] = (object) ['id' => 7];
    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
    expect($hostingServerModel->username)->toBe('new-admin');
    expect($hostingServerModel->accesshash)->toBe('new-hash');
});
