<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('get di', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $api = new Box\Mod\Staff\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('get list', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getSearchQuery')
    ->atLeast()->once()
    ->andReturn(['sqlString', []]);
    $serviceMock
    ->shouldReceive('toModel_AdminApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $resultSet = [
        'list' => ['id' => 1],
    ];
    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($resultSet);

    $adminModel = new Model_Admin();
    $adminModel->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($adminModel);

    $di = container();
    $di['pager'] = $pagerMock;
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->get_list($data);
    expect($result)->toBeArray();
});

test('get', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('toModel_AdminApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_Admin());

    $di = container();
    $di['db'] = $dbMock;

    $api->setService($serviceMock);
    $api->setDi($di);

    $result = $api->get($data);
    expect($result)->toBeArray();
});

test('update', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('update')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_Admin());

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('delete', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('delete')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_Admin());

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('change password', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [
        'id' => '1',
        'password' => 'test!23A',
        'password_confirm' => 'test!23A',
    ];

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock
    ->shouldReceive('isPasswordStrong')
    ->atLeast()->once()
    ->andReturn(true);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('changePassword')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_Admin());

    $di = container();
    $di['validator'] = $validatorMock;
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->change_password($data);

    expect(true)->toBeTrue();
});

test('change password password do not match', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [
        'id' => '1',
        'password' => 'test!23A',
        'password_confirm' => 'test',
    ];

    $di = container();
    $api->setDi($di);
    expect(fn () => $api->change_password($data))
        ->toThrow(FOSSBilling\Exception::class, 'Passwords do not match');
});

test('create', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [
        'admin_group_id' => '1',
        'password' => 'test!23A',
        'email' => 'okey@example.com',
        'name' => 'OkeyTest',
    ];

    $newStaffId = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('create')
    ->atLeast()->once()
    ->andReturn($newStaffId);

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock
    ->shouldReceive('isPasswordStrong')
    ->atLeast()->once()
    ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($newStaffId);
});

test('permissions get', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = 1;

    $staffModel = new Model_Admin();
    $staffModel->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getPermissions')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($staffModel);

    $di = container();
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->permissions_get($data);
    expect($result)->toBeArray();
});

test('permissions update', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [
        'id' => '1',
        'permissions' => 'default',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('setPermissions')
    ->atLeast()->once()
    ->andReturn(true);

    $staffModel = new Model_Admin();
    $staffModel->loadBean(new Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($staffModel);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->permissions_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('group get pairs', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getAdminGroupPair')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->group_get_pairs([]);
    expect($result)->toBeArray();
});

test('group get list', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getAdminGroupSearchQuery')
    ->atLeast()->once()
    ->andReturn(['sqlString', []]);

    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

    $di = container();
    $di['pager'] = $pagerMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_get_list($data);
    expect($result)->toBeArray();
});

test('group create', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['name'] = 'Prime Group';
    $newGroupId = 1;

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('createGroup')
    ->atLeast()->once()
    ->andReturn($newGroupId);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($newGroupId);
});

test('group get', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = '1';

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('toAdminGroupApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_AdminGroup());

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity(new Model_Admin());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_get($data);
    expect($result)->toBeArray();
});

test('group delete', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = '1';

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('deleteGroup')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_AdminGroup());

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity(new Model_Admin());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_delete($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('group update', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = '1';

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('updateGroup')
    ->atLeast()->once()
    ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_AdminGroup());

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity(new Model_Admin());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->group_update($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('login history get list', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('getActivityAdminHistorySearchQuery')
    ->atLeast()->once()
    ->andReturn(['sqlString', []]);
    $serviceMock
    ->shouldReceive('toActivityAdminHistoryApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $resultSet = [
        'list' => ['id' => 1],
    ];
    $pagerMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($resultSet);

    $model = new Model_ActivityAdminHistory();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['pager'] = $pagerMock;
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->login_history_get_list($data);
    expect($result)->toBeArray();
});

test('login history get', function (): void {
    $api = new Box\Mod\Staff\Api\Admin();
    $data['id'] = '1';

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('toActivityAdminHistoryApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new Model_ActivityAdminHistory());

    $di = container();
    $di['db'] = $dbMock;

    $api->setIdentity(new Model_Admin());
    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->login_history_get($data);
    expect($result)->toBeArray();
});
