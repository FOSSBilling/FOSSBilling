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

beforeEach(function () {
    $this->adminClient = new \Box\Mod\Client\Api\Admin();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->adminClient->setDi($di);
    $getDi = $this->adminClient->getDi();
    expect($getDi)->toEqual($di);
});

test('getList returns array', function () {
    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSearchQuery')
        ->willReturn(['String', []]);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

    $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->getMock();

    $pagerMock->expects($this->atLeastOnce())
        ->method('getPaginatedResultSet')
        ->willReturn($simpleResultArr);

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($model);

    $di = container();
    $di['pager'] = $pagerMock;
    $di['db'] = $dbMock;

    $this->adminClient->setService($serviceMock);
    $this->adminClient->setDi($di);
    $data = [];

    $result = $this->adminClient->get_list($data);
    expect($result)->toBeArray();
});

test('getPairs returns array', function () {
    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('getPairs')->willReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

    $this->adminClient->setDi($di);

    $data = ['id' => 1];
    $result = $this->adminClient->get_pairs($data);
    expect($result)->toBeArray();
});

test('get returns array', function () {
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('get')->willReturn($model);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

    $this->adminClient->setService($serviceMock);

    $result = $this->adminClient->get([]);
    expect($result)->toBeArray();
});

test('login returns array', function () {
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $sessionArray = [
        'id' => 1,
        'email' => 'email@example.com',
        'name' => 'John Smith',
        'role' => 'client',
    ];
    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('toSessionArray')->willReturn($sessionArray);

    $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
    $sessionMock->expects($this->atLeastOnce())->method('set');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['session'] = $sessionMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);

    $data = ['id' => 1];
    $result = $this->adminClient->login($data);
    expect($result)->toBeArray();
});

test('create returns int', function () {
    $data = [
        'email' => 'email@example.com',
        'first_name' => 'John', 'password' => 'StrongPass123',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(false);
    $serviceMock->expects($this->atLeastOnce())->method('adminCreateClient')->willReturn(1);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['tools'] = $toolsMock;

    $this->adminClient->setDi($di);
    $this->adminClient->setService($serviceMock);

    $result = $this->adminClient->create($data);

    expect($result)->toBeInt();
});

test('create throws exception when email is already registered', function () {
    $data = [
        'email' => 'email@example.com',
        'first_name' => 'John', 'password' => 'StrongPass123',
    ];

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(true);

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

    $di = container();
    $di['tools'] = $toolsMock;

    $this->adminClient->setDi($di);
    $this->adminClient->setService($serviceMock);

    $this->adminClient->create($data);
})->throws(\FOSSBilling\Exception::class, 'This email address is already registered.');

test('delete returns true', function () {
    $data = ['id' => 1];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $serviceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
        ->onlyMethods(['remove'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('remove');

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);
    $this->adminClient->setService($serviceMock);
    $result = $this->adminClient->delete($data);
    expect($result)->toBeTrue();
});

test('update returns true', function () {
    $data = [
        'id' => 1,
        'first_name' => 'John', 'password' => 'StrongPass123',
        'last_name' => 'Smith',
        'aid' => '0',
        'gender' => 'male',
        'birthday' => '1999-01-01',
        'company' => 'LTD Testing',
        'company_vat' => 'VAT0007',
        'address_1' => 'United States',
        'address_2' => 'Utah',
        'phone_cc' => '+1',
        'phone' => '555-345-345',
        'document_type' => 'doc',
        'document_nr' => '1',
        'notes' => 'none',
        'country' => 'Moon',
        'postcode' => 'IL-11123',
        'city' => 'Chicaco',
        'state' => 'IL',
        'currency' => 'USD',
        'tax_exempt' => 'N/A',
        'created_at' => '2012-05-10',
        'email' => 'test@example.com',
        'group_id' => 1,
        'status' => 'test status',
        'company_number' => '1234',
        'type' => '',
        'lang' => 'en',
        'custom_1' => '',
        'custom_2' => '',
        'custom_3' => '',
        'custom_4' => '',
        'custom_5' => '',
        'custom_6' => '',
        'custom_7' => '',
        'custom_8' => '',
        'custom_9' => '',
        'custom_10' => '',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);
    $dbMock->expects($this->atLeastOnce())
        ->method('store')->willReturn(1);

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(false);
    $serviceMock->expects($this->atLeastOnce())->method('canChangeCurrency')->willReturn(true);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolsMock;

    $this->adminClient->setDi($di);
    $result = $this->adminClient->update($data);
    expect($result)->toBeTrue();
});

test('update throws exception when email is already registered', function () {
    $data = [
        'id' => 1,
        'first_name' => 'John', 'password' => 'StrongPass123',
        'last_name' => 'Smith',
        'aid' => '0',
        'gender' => 'male',
        'birthday' => '1999-01-01',
        'company' => 'LTD Testing',
        'company_vat' => 'VAT0007',
        'address_1' => 'United States',
        'address_2' => 'Utah',
        'phone_cc' => '+1',
        'phone' => '555-345-345',
        'document_type' => 'doc',
        'document_nr' => '1',
        'notes' => 'none',
        'country' => 'Moon',
        'postcode' => 'IL-11123',
        'city' => 'Chicaco',
        'state' => 'IL',
        'currency' => 'USD',
        'tax_exempt' => 'N/A',
        'created_at' => '2012-05-10',
        'email' => 'test@example.com',
        'group_id' => 1,
        'status' => 'test status',
        'company_number' => '1234',
        'type' => '',
        'lang' => 'en',
        'custom_1' => '',
        'custom_2' => '',
        'custom_3' => '',
        'custom_4' => '',
        'custom_5' => '',
        'custom_6' => '',
        'custom_7' => '',
        'custom_8' => '',
        'custom_9' => '',
        'custom_10' => '',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(true);
    $serviceMock->expects($this->never())->method('canChangeCurrency')->willReturn(true);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->never())->method('fire');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['validator'] = new \FOSSBilling\Validate();

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
    $di['tools'] = $toolsMock;

    $this->adminClient->setDi($di);

    $this->adminClient->update($data);
})->throws(\FOSSBilling\Exception::class, 'This email address is already registered.');

test('update throws exception when id is not passed', function () {
    $data = [];

    $di = container();

    $di['validator'] = new \FOSSBilling\Validate();
    $this->adminClient->setDi($di);

    // Validate required parameters before calling update
    $validator = $di['validator'];
    $validator->checkRequiredParamsForArray(['id' => 'Client ID was not passed'], $data);

    $this->adminClient->update($data);
})->throws(\FOSSBilling\Exception::class, 'Client ID was not passed');

test('changePassword returns true', function () {
    $data = [
        'id' => 1,
        'password' => 'strongPass',
        'password_confirm' => 'strongPass',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $dbMock->expects($this->atLeastOnce())
        ->method('store')->willReturn(1);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
    $passwordMock->expects($this->atLeastOnce())
        ->method('hashIt')
        ->with($data['password']);

    $profileService = $this->createMock(\Box\Mod\Profile\Service::class);
    $profileService->expects($this->atLeastOnce())
        ->method('invalidateSessions');

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $profileService);

    $this->adminClient->setDi($di);

    $result = $this->adminClient->change_password($data);
    expect($result)->toBeTrue();
});

test('changePassword throws exception when passwords do not match', function () {
    $data = [
        'id' => 1,
        'password' => 'strongPass',
        'password_confirm' => 'NotIdentical',
    ];

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorStub;
    $this->adminClient->setDi($di);

    $this->adminClient->change_password($data);
})->throws(\FOSSBilling\Exception::class, 'Passwords do not match');

test('balanceGetList returns array', function () {
    $simpleResultArr = [
        'list' => [
            [
                'id' => 1,
                'description' => 'Testing',
                'amount' => '1.00',
                'currency' => 'USD',
                'created_at' => date('Y:m:d H:i:s'),
            ],
        ],
    ];

    $data = [];

    $serviceMock = $this->createMock(\Box\Mod\Client\ServiceBalance::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSearchQuery')
        ->willReturn(['String', []]);

    $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->getMock();

    $pagerMock->expects($this->atLeastOnce())
        ->method('getPaginatedResultSet')
        ->willReturn($simpleResultArr);

    $model = new \Model_ClientBalance();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['pager'] = $pagerMock;

    $this->adminClient->setDi($di);

    $result = $this->adminClient->balance_get_list($data);
    expect($result)->toBeArray();
});

test('balanceDelete returns true', function () {
    $data = [
        'id' => 1,
    ];

    $model = new \Model_ClientBalance();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $dbMock->expects($this->atLeastOnce())
        ->method('trash');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);

    $result = $this->adminClient->balance_delete($data);
    expect($result)->toBeTrue();
});

test('balanceAddFunds returns true', function () {
    $data = [
        'id' => 1,
        'amount' => '1.00',
        'description' => 'testDescription',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('addFunds');

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);

    $result = $this->adminClient->balance_add_funds($data);
    expect($result)->toBeTrue();
});

test('batchExpirePasswordReminders returns true', function () {
    $expiredArr = [
        new \Model_ClientPasswordReset(),
    ];

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('trash');

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('getExpiredPasswordReminders')->willReturn($expiredArr);

    $di = container();
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();

    $this->adminClient->setDi($di);

    $result = $this->adminClient->batch_expire_password_reminders();
    expect($result)->toBeTrue();
});

test('loginHistoryGetList returns array', function () {
    $data = [];
    $pagerResultSet = [
        'list' => [],
    ];

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getHistorySearchQuery')
        ->willReturn(['String', []]);

    $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->getMock();

    $pagerMock->expects($this->atLeastOnce())
        ->method('getPaginatedResultSet')
        ->willReturn($pagerResultSet);

    $di = container();
    $di['pager'] = $pagerMock;

    $this->adminClient->setDi($di);
    $this->adminClient->setService($serviceMock);

    $result = $this->adminClient->login_history_get_list($data);
    expect($result)->toBeArray();
});

test('getStatuses returns array', function () {
    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('counter')->willReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

    $this->adminClient->setDi($di);

    $result = $this->adminClient->get_statuses([]);
    expect($result)->toBeArray();
});

test('groupGetPairs returns array', function () {
    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('getGroupPairs')->willReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

    $this->adminClient->setDi($di);

    $result = $this->adminClient->group_get_pairs([]);
    expect($result)->toBeArray();
});

test('groupCreate returns int', function () {
    $data['title'] = 'test Group';

    $newGroupId = 1;
    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('createGroup')->willReturn($newGroupId);

    $di = container();
    $this->adminClient->setService($serviceMock);
    $this->adminClient->setDi($di);
    $result = $this->adminClient->group_create($data);

    expect($result)->toBeInt();
    expect($result)->toEqual($newGroupId);
});

test('groupUpdate returns true', function () {
    $data['id'] = '2';
    $data['title'] = 'test Group updated';

    $model = new \Model_ClientGroup();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $dbMock->expects($this->atLeastOnce())
        ->method('store')->willReturn(1);

    $di = container();
    $di['db'] = $dbMock;

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);

    $result = $this->adminClient->group_update($data);

    expect($result)->toBeTrue();
});

test('groupDelete returns true', function () {
    $data['id'] = '2';

    $model = new \Model_ClientGroup();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);
    $dbMock->expects($this->once())
        ->method('find')->with('Client', 'client_group_id = :group_id', [':group_id' => $data['id']])
        ->willReturn([]);

    $serviceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
        ->onlyMethods(['deleteGroup'])
        ->getMock();
    $serviceMock->expects($this->atLeastOnce())
        ->method('deleteGroup')
        ->willReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);
    $this->adminClient->setService($serviceMock);

    $result = $this->adminClient->group_delete($data);

    expect($result)->toBeTrue();
});

test('groupGet returns array', function () {
    $data['id'] = '2';

    $model = new \Model_ClientGroup();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')->willReturn($model);

    $dbMock->expects($this->atLeastOnce())
        ->method('toArray')->willReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);
    $di['validator'] = $validatorStub;

    $this->adminClient->setDi($di);

    $result = $this->adminClient->group_get($data);

    expect($result)->toBeArray();
});

test('batchDelete returns true', function () {
    $activityMock = $this->getMockBuilder(\Box\Mod\Client\Api\Admin::class)->onlyMethods(['delete'])->getMock();
    $activityMock->expects($this->atLeastOnce())->method('delete')->willReturn(true);

    $validatorStub = $this->createStub(\FOSSBilling\Validate::class);

    $di = container();
    $di['validator'] = $validatorStub;
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});
