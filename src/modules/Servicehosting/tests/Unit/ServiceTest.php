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
    $this->service = new Service();
});

test('testGetDi', function (): void {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('testValidateOrderData', function (string $field, string $exceptionMessage, int $excCode) {
    $data = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];

    unset($data[$field]);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage($exceptionMessage);
    $this->service->validateOrderData($data);
})->with('validateOrderDataProvider');

test('testActionCreate', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $confArr = [
        'server_id' => 1,
        'hosting_plan_id' => 2,
        'sld' => 'great',
        'tld' => 'com',
    ];
    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getConfig')
        ->willReturn($confArr);

    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingPlansModel = new \Model_ServiceHostingHp();
    $hostingPlansModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturnOnConsecutiveCalls($hostingServerModel, $hostingPlansModel);

    $servhostingModel = new \Model_ServiceHosting();
    $servhostingModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->expects($this->atLeastOnce())
        ->method('dispense')
        ->willReturn($servhostingModel);

    $newserviceHostingId = 4;
    $dbMock->expects($this->atLeastOnce())
        ->method('store')
        ->willReturn($newserviceHostingId);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $this->service->action_create($orderModel);
});

test('testActionRenew', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $result = $this->service->action_renew($orderModel);
    expect($result)->toBeTrue();
});

test('testActionRenewOrderWithoutActiveService', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $this->service->action_renew($orderModel);
});

test('testActionSuspend', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();
    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('suspendAccount');
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_suspend($orderModel);
    expect($result)->toBeTrue();
});

test('testActionSuspendOrderWithoutActiveService', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $this->service->action_suspend($orderModel);
});

test('testActionUnsuspend', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();
    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('unsuspendAccount');
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_unsuspend($orderModel);
    expect($result)->toBeTrue();
});

test('testActionUnsuspendOrderWithoutActiveService', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $this->service->action_unsuspend($orderModel);
});

test('testActionCancel', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();
    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('cancelAccount');
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $serviceMock->setDi($di);
    $result = $serviceMock->action_cancel($orderModel);
    expect($result)->toBeTrue();
});

test('testActionCancelOrderWithoutActiveService', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->id = 1;

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService');

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $this->service->setDi($di);
    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage(sprintf('Order %d has no active service', $orderModel->id));
    $this->service->action_cancel($orderModel);
});

test('testActionDelete', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());
    $orderModel->status = 'active';

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getOrderService')
        ->willReturn($model);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('trash');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['action_cancel'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('action_cancel');

    $serviceMock->setDi($di);
    $serviceMock->action_delete($orderModel);
});

test('testChangeAccountPlan', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $modelHp = new \Model_ServiceHostingHp();
    $modelHp->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM', 'getServerPackage'])
        ->getMock();
    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('changeAccountPackage');
    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerPackage')
        ->willReturn(new \Server_Package());

    $serviceMock->setDi($di);
    $result = $serviceMock->changeAccountPlan($orderModel, $model, $modelHp);
    expect($result)->toBeTrue();
});

test('testChangeAccountUsername', function (): void {
    $data = [
        'username' => 'u123456',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('changeAccountUsername');

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountUsername($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountUsernameMissingUsername', function (): void {
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $data = [];

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account username is missing or is invalid');
    $this->service->changeAccountUsername($orderModel, $model, $data);
});

test('testChangeAccountIp', function (): void {
    $data = [
        'ip' => '1.1.1.1',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('changeAccountIp');

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountIp($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountIpMissingIp', function (): void {
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account IP address is missing or is invalid');
    $this->service->changeAccountIp($orderModel, $model, $data);
});

test('testChangeAccountDomain', function (): void {
    $data = [
        'tld' => 'com',
        'sld' => 'testingSld',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('changeAccountDomain');

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountDomain($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountDomainMissingParams', function (): void {
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Domain SLD or TLD is missing');
    $this->service->changeAccountDomain($orderModel, $model, $data);
});

test('testChangeAccountPassword', function (): void {
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('changeAccountPassword');

    $AMresultArray = [$serverManagerMock, new \Server_Account()];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->changeAccountPassword($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testChangeAccountPasswordMissingParams', function (): void {
    $data = [];
    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage('Account password is missing or is invalid');
    $this->service->changeAccountPassword($orderModel, $model, $data);
});

test('testSync', function (): void {
    $data = [
        'password' => 'topsecret',
        'password_confirm' => 'topsecret',
    ];

    $orderModel = new \Model_ClientOrder();
    $orderModel->loadBean(new \Tests\Helpers\DummyBean());

    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['_getAM'])
        ->getMock();

    $accountObj = new \Server_Account();
    $accountObj->setUsername('testUser1');
    $accountObj->setIp('1.1.1.1');

    $accountObj2 = new \Server_Account();
    $accountObj2->setUsername('testUser2');
    $accountObj2->setIp('2.2.2.2');

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('synchronizeAccount')
        ->willReturn($accountObj2);

    $AMresultArray = [$serverManagerMock, $accountObj];
    $serviceMock->expects($this->atLeastOnce())
        ->method('_getAM')
        ->willReturn($AMresultArray);

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $serviceMock->setDi($di);

    $result = $serviceMock->sync($orderModel, $model, $data);
    expect($result)->toBeTrue();
});

test('testToApiArray', function (): void {
    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $hostingServer = new \Model_ServiceHostingServer();
    $hostingServer->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServer->manager = 'Custom';
    $hostingHp = new \Model_ServiceHostingHp();
    $hostingHp->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('load')
        ->willReturnOnConsecutiveCalls($hostingServer, $hostingHp);

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getServiceOrder');

    $serverManagerCustomStub = $this->createStub('\Server_Manager_Custom');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomStub);

    $this->service->setDi($di);

    $result = $this->service->toApiArray($model, false, new \Model_Admin());
    expect($result)->toBeArray();
});

test('testUpdate', function (): void {
    $data = [
        'username' => 'testUser',
        'ip' => '1.1.1.1',
    ];
    $model = new \Model_ServiceHosting();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->update($model, $data);
    expect($result)->toBeTrue();
});

test('testGetServerManagers', function (): void {
    $result = $this->service->getServerManagers();
    expect($result)->toBeArray();
});

test('testGetServerManagerConfig', function (): void {
    $manager = 'Custom';

    $expected = [
        'label' => 'Custom Server Manager',
    ];

    $result = $this->service->getServerManagerConfig($manager);
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetServerPairs', function (): void {
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

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getAll')
        ->willReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getServerPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetServerSearchQuery', function (): void {
    $result = $this->service->getServersSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('testCreateServer', function (): void {
    $dbMock = $this->createMock('\Box_Database');

    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->expects($this->atLeastOnce())
        ->method('dispense')
        ->willReturn($hostingServerModel);

    $newId = 1;
    $dbMock->expects($this->atLeastOnce())
        ->method('store')
        ->willReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $name = 'newSuperFastServer';
    $ip = '1.1.1.1';
    $manager = 'Custom';
    $data = [];
    $result = $this->service->createServer($name, $ip, $manager, $data);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('testDeleteServer', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('trash');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->deleteServer($hostingServerModel);
    expect($result)->toBeTrue();
});

test('testUpdateServer', function (): void {
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

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->updateServer($hostingServerModel, $data);
    expect($result)->toBeTrue();
});

test('testGetServerManager', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serverManagerCustomStub = $this->createStub('\Server_Manager_Custom');

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config) => $serverManagerCustomStub);
    $this->service->setDi($di);

    $result = $this->service->getServerManager($hostingServerModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('testGetServerManagerManagerNotDefined', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionCode(654);
    $this->expectExceptionMessage('Invalid server manager. Server was not configured properly');
    $this->service->getServerManager($hostingServerModel);
});

test('testGetServerManagerServerManagerInvalid', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $di = container();
    $di['server_manager'] = $di->protect(fn ($manager, $config): null => null);
    $this->service->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $this->expectExceptionMessage("Server manager {$hostingServerModel->manager} is invalid.");
    $this->service->getServerManager($hostingServerModel);
});

test('testTestConnection', function (): void {
    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('testConnection')
        ->willReturn(true);

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getServerManager'])
        ->getMock();

    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerManager')
        ->willReturn($serverManagerMock);

    $hostingServerModel = new \Model_ServiceHostingServer();
    $result = $serviceMock->testConnection($hostingServerModel);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('testGetHpPairs', function (): void {
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

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getAll')
        ->willReturn($queryResult);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getHpPairs();
    expect($result)->toBeArray();
    expect($result)->toBe($expected);
});

test('testGetHpSearchQuery', function (): void {
    $result = $this->service->getServersSearchQuery([]);
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    expect($result[1])->toBe([]);
});

test('testDeleteHp', function (): void {
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('trash');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $result = $this->service->deleteHp($model);
    expect($result)->toBeTrue();
});

test('testToHostingHpApiArray', function (): void {
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->toHostingHpApiArray($model);
    expect($result)->toBeArray();
});

test('testUpdateHp', function (): void {
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

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('store');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->updateHp($model, $data);
    expect($result)->toBeTrue();
});

test('testCreateHp', function (): void {
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $newId = 1;

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('dispense')->willReturn($model);
    $dbMock->expects($this->atLeastOnce())
        ->method('store')->willReturn($newId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->service->setDi($di);

    $result = $this->service->createHp('Free Plan', []);
    expect($result)->toBeInt();
    expect($result)->toBe($newId);
});

test('testGetServerPackage', function (): void {
    $model = new \Model_ServiceHostingHp();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = '{}';

    $di = container();

    $this->service->setDi($di);
    $result = $this->service->getServerPackage($model);
    expect($result)->toBeInstanceOf('\Server_Package');
});

test('testGetServerManagerWithLog', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $clientOrderModel = new \Model_ClientOrder();
    $clientOrderModel->loadBean(new \Tests\Helpers\DummyBean());

    $serverManagerStub = $this->createStub('\Server_Manager_Custom');
    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getServerManager'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerManager')
        ->willReturn($serverManagerStub);

    $orderServiceMock = $this->createMock(\Box\Mod\Order\Service::class);
    $orderServiceMock->expects($this->atLeastOnce())
        ->method('getLogger')
        ->willReturn(new \Tests\Helpers\TestLogger());

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $orderServiceMock);

    $serviceMock->setDi($di);
    $result = $serviceMock->getServerManagerWithLog($hostingServerModel, $clientOrderModel);
    expect($result)->toBeInstanceOf('\Server_Manager_Custom');
});

test('testGetManagerUrls', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serverManagerMock = $this->getMockBuilder('\Server_Manager_Custom')->disableOriginalConstructor()->getMock();
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('getLoginUrl')
        ->willReturn('/login');
    $serverManagerMock->expects($this->atLeastOnce())
        ->method('getResellerLoginUrl')
        ->willReturn('/admin/login');

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getServerManager'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerManager')
        ->willReturn($serverManagerMock);

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeString();
});

test('testGetManagerUrlsException', function (): void {
    $hostingServerModel = new \Model_ServiceHostingServer();
    $hostingServerModel->loadBean(new \Tests\Helpers\DummyBean());
    $hostingServerModel->manager = 'Custom';

    $serviceMock = $this->getMockBuilder(Service::class)
        ->onlyMethods(['getServerManager'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('getServerManager')
        ->will($this->throwException(new \Exception('Controlled unit test exception')));

    $result = $serviceMock->getManagerUrls($hostingServerModel);
    expect($result)->toBeArray();
    expect($result[0])->toBeFalse();
    expect($result[1])->toBeFalse();
});

test('testGetFreeTldsFreeTldsAreNotSet', function (): void {
    $di = container();

    $tldArray = ['tld' => '.com'];
    $serviceDomainServiceMock = $this->createMock(\Box\Mod\Servicedomain\Service::class);
    $serviceDomainServiceMock->expects($this->atLeastOnce())
        ->method('tldToApiArray')
        ->willReturn($tldArray);
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $serviceDomainServiceMock);

    $tldModel = new \Model_Tld();
    $tldModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('find')
        ->willReturn([$tldModel]);
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $result = $this->service->getFreeTlds($model);
    expect($result)->toBeArray();
});

test('testGetFreeTlds', function (): void {
    $config = [
        'free_tlds' => ['.com'],
    ];
    $di = container();

    $this->service->setDi($di);
    $model = new \Model_Product();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->config = json_encode($config);

    $result = $this->service->getFreeTlds($model);
    expect($result)->toBeArray();
    expect($result)->not->toBeEmpty();
});
