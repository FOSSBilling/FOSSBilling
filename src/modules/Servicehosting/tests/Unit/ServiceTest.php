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
use Box\Mod\Servicehosting\Service;

dataset('validateOrderDataProvider', function () {
    return [
        ['server_id', 'Hosting product is not configured completely. Configure server for hosting product.', 701],
        ['hosting_plan_id', 'Hosting product is not configured completely. Configure hosting plan for hosting product.', 702],
        ['sld', 'Domain name is invalid.', 703],
        ['tld', 'Domain extension is invalid.', 704],
    ];
});

beforeEach(function (): void {
    $service = new Service();
});

test('testGetDi', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('testValidateOrderData', function (string $field, string $exceptionMessage, int $excCode) {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];

    unset($data[$field]);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage($exceptionMessage);
    $service->validateOrderData($data);
})->with('validateOrderDataProvider');

test('testActionCreate', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $confArr = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getConfig')
    ->atLeast()->once()
    ->andReturn($confArr);

    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingPlansModel = new \Model_ServiceHostingHp();
    $hostingPlansModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
        ->shouldReceive('getExistingModelById')
        ->andReturn($hostingServerModel, $hostingPlansModel);

    $servhostingModel = new \Model_ServiceHosting();
    $servhostingModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock
    ->shouldReceive('dispense')
    ->atLeast()->once()
    ->andReturn($servhostingModel);

    $newserviceHostingId = 4;
    $dbMock
    ->shouldReceive('store')
    ->atLeast()->once()
    ->andReturn($newserviceHostingId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $service->action_create($orderModel);
});

test('testActionRenew', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $result = $service->action_renew($orderModel);
    expect($result)->toBeTrue();
});

test('testActionRenewOrderWithoutActiveService', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $service->action_renew($orderModel);
});

test('testActionSuspend', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('suspendAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_suspend($orderModel);
    expect($result)->toBeTrue();
});

test('testActionSuspendOrderWithoutActiveService', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $service->action_suspend($orderModel);
});

test('testActionUnsuspend', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('unsuspendAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_unsuspend($orderModel);
    expect($result)->toBeTrue();
});

test('testActionUnsuspendOrderWithoutActiveService', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $service->action_unsuspend($orderModel);
});

test('testActionCancel', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('cancelAccount')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_cancel($orderModel);
    expect($result)->toBeTrue();
});

test('testActionCancelOrderWithoutActiveService', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getOrderService')->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $service->action_cancel($orderModel);
});

test('testActionDelete', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->status = 'active';

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getOrderService')
    ->atLeast()->once()
    ->andReturn($model);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('action_cancel')->atLeast()->once();

    $serviceMock->setDi($di);
    $serviceMock->action_delete($orderModel);
});

test('testChangeAccountPlan', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $modelHp = new \Model_ServiceHostingHp();
    $modelHp->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountPackage')->atLeast()->once();
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);
    $serviceMock
    ->shouldReceive('getServerPackage')
    ->atLeast()->once()
    ->andReturn(new \Server_Package());

    $serviceMock->setDi($di);
    $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
    expect($result)->toBeTrue();
});

test('testChangeAccountUsername', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'username' => 'u123456',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountUsername')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountUsernameMissingUsername', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $data = [];

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account username is missing or is invalid');
    $service->changeAccountUsername($orderModel, $model, $data);
});

test('testChangeAccountIp', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'ip' => '1.1.1.1',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountIp')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountIpMissingIp', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account IP address is missing or is invalid');
    $service->changeAccountIp($orderModel, $model, $data);
});

test('testChangeAccountDomain', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'tld' => 'com',
        'sld' => 'testingSld',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountDomain')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountDomainMissingParams', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Domain SLD or TLD is missing');
    $service->changeAccountDomain($orderModel, $model, $data);
});

test('testChangeAccountPassword', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('changeAccountPassword')->atLeast()->once();

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountPasswordMissingParams', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account password is missing or is invalid');
    $service->changeAccountPassword($orderModel, $model, $data);
});

test('testSync', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $accountObj = new \Server_Account();
    $accountObj->setUsername('testUser1');
    $accountObj->setIp('1.1.1.1');

    $accountObj2 = new \Server_Account();
    $accountObj2->setUsername('testUser2');
    $accountObj2->setIp('2.2.2.2');

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock
    ->shouldReceive('synchronizeAccount')
    ->atLeast()->once()
    ->andReturn($accountObj2);

    $AMresultArray = [$serverManagerMock, $accountObj];
    $serviceMock
    ->shouldReceive('_getAM')
    ->atLeast()->once()
    ->andReturn($AMresultArray);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->sync($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testToApiArray', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $hostingServer = new \Model_ServiceHostingServer();
    $hostingServer->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServer->manager = 'Custom';
    $hostingHp = new \Model_ServiceHostingHp();
    $hostingHp->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
        ->shouldReceive('load')
        ->andReturn($hostingServer, $hostingHp);

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('getServiceOrder')->atLeast()->once();

    $serverManagerCustomStub = Mockery::mock('\Server_Manager_Custom');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomStub);

    $service->setDi($di);

    $result = $service->toApiArray($model, false, new \Model_Admin());
    expect($result)->toBeArray();
});

test('testUpdate', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $data = [
        'username' => 'testUser',
        'ip' => '1.1.1.1',
    ];
    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->update($model, $data);
    expect($result)->toBeTrue();
});

test('testGetServerManagers', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $result = $service->getServerManagers();
    expect($result)->toBeArray();
});

test('testGetServerManagerConfig', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $manager = 'Custom';

    $expected = [
        'label' => 'Custom Server Manager',
    ];

    $result = $service->getServerManagerConfig($manager);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetServerPairs', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
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
    $dbMock
    ->shouldReceive('getAll')
    ->atLeast()->once()
    ->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getServerPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetServerSearchQuery', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $result = $service->getServersSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('testCreateServer', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $dbMock = Mockery::mock('\Box_Database');

    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock
    ->shouldReceive('dispense')
    ->atLeast()->once()
    ->andReturn($hostingServerModel);

    $newId = 1;
    $dbMock
    ->shouldReceive('store')
    ->atLeast()->once()
    ->andReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $name = 'newSuperFastServer';
    $ip = '1.1.1.1';
    $manager = 'Custom';
    $data = [];
    $result = $service->createServer($name, $ip, $manager, $data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('testDeleteServer', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->deleteServer($hostingServerModel);
    expect($result)->toBeTrue();
});

test('testUpdateServer', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
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
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
});

test('testGetServerManager', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serverManagerCustomStub = Mockery::mock('\Server_Manager_Custom');

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomStub);
    $service->setDi($di);

    $result = $service->getServerManager($hostingServerModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('testGetServerManagerManagerNotDefined', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionCode(654);
    $this->expectExceptionMessage('Invalid server manager. Server was not configured properly');
    $service->getServerManager($hostingServerModel);
});

test('testGetServerManagerServerManagerInvalid', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
    $service->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage("Server manager {$hostingServerModel->manager} is invalid.");
    $service->getServerManager($hostingServerModel);
});

test('testTestConnection', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock
    ->shouldReceive('testConnection')
    ->atLeast()->once()
    ->andReturn(true);

    $serviceMock = Mockery::mock(Service::class)->makePartial();

    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andReturn($serverManagerMock);

    $hostingServerModel = new \Model_ServiceHostingServer();
    $result = $serviceMock->testConnection($hostingServerModel);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testGetHpPairs', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
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
    $dbMock
    ->shouldReceive('getAll')
    ->atLeast()->once()
    ->andReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getHpPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetHpSearchQuery', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $result = $service->getServersSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('testDeleteHp', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);
    $dbMock->shouldReceive('trash')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->deleteHp($model);
    expect($result)->toBeTrue();
});

test('testToHostingHpApiArray', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->toHostingHpApiArray($model);
    expect($result)->toBeArray();
});

test('testUpdateHp', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
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
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->updateHp($model, $data);
    expect($result)->toBeTrue();
});

test('testCreateHp', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $newId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
        ->shouldReceive('dispense')->atLeast()->once()->andReturn($model);
    $dbMock
        ->shouldReceive('store')->atLeast()->once()->andReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->createHp('Free Plan', []);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('testGetServerPackage', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = '{}';

    $di = container();

    $service->setDi($di);
    $result = $service->getServerPackage($model);
    expect($result)->toBeInstanceOf('\Server_Package');
});

test('testGetServerManagerWithLog', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock->shouldReceive('setLog')->atLeast()->once();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andReturn($serverManagerMock);

    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock
    ->shouldReceive('getLogger')
    ->atLeast()->once()
    ->andReturn(new \Tests\Helpers\TestLogger());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('testGetManagerUrls', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serverManagerMock = Mockery::mock('\Server_Manager_Custom');
    $serverManagerMock
    ->shouldReceive('getLoginUrl')
    ->atLeast()->once()
    ->andReturn('/login');
    $serverManagerMock
    ->shouldReceive('getResellerLoginUrl')
    ->atLeast()->once()
    ->andReturn('/admin/login');

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock
    ->shouldReceive('getServerManager')
    ->atLeast()->once()
    ->andReturn($serverManagerMock);

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeString();
});

test('testGetManagerUrlsException', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock
        ->shouldReceive('getServerManager')
        ->andThrow(new \Exception('Controlled unit test exception'));

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeFalse();
    expect($result[1])->toBeFalse();
});

test('testGetFreeTldsFreeTldsAreNotSet', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $di = container();

    $tldArray = ['tld' => '.com'];
    $serviceDomainServiceMock = Mockery::mock(\Box\Mod\Servicedomain\Service::class);
    $serviceDomainServiceMock
    ->shouldReceive('tldToApiArray')
    ->atLeast()->once()
    ->andReturn($tldArray);
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $serviceDomainServiceMock);

    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('find')
    ->atLeast()->once()
    ->andReturn([$tldModel]);
    $di['db'] = $dbMock;

    $service->setDi($di);
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $result = $service->getFreeTlds($model);
    expect($result)->toBeArray();
});

test('testGetFreeTlds', function (): void {
    $service = new \Box\Mod\Servicehosting\Service();
    $config = [
        'free_tlds' => ['.com'],
    ];
    $di = container();

    $service->setDi($di);
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = json_encode($config);

    $result = $service->getFreeTlds($model);
    expect($result)->toBeArray();
    expect($result)->not->toBeEmpty();
});
