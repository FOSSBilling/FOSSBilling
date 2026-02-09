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
    $this->api = new \Box\Mod\Staff\Api\Admin();
});

test('get di', function () {
        $di = container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        expect($getDi)->toEqual($di);
    });

    test('get list', function () {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => ['id' => 1],
        ];
        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($adminModel);

        $di = container();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        expect($result)->toBeArray();
    });

    test('get', function () {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->get($data);
        expect($result)->toBeArray();
    });

    test('update', function () {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('delete', function () {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->delete($data);
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('change password', function () {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test!23A',
        ];

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())
            ->method('isPasswordStrong')
            ->willReturn(true);

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changePassword')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->change_password($data);

        expect(true)->toBeTrue();
    });

    test('change password password do not match', function () {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test',
        ];

        $di = container();
        $this->api->setDi($di);
        expect(fn () => $this->api->change_password($data))
            ->toThrow(\FOSSBilling\Exception::class, 'Passwords do not match');
    });

    test('create', function () {
        $data = [
            'admin_group_id' => '1',
            'password' => 'test!23A',
            'email' => 'okey@example.com',
            'name' => 'OkeyTest',
        ];

        $newStaffId = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newStaffId);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())
            ->method('isPasswordStrong')
            ->willReturn(true);

        $di = container();
        $di['validator'] = $validatorMock;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->create($data);
        expect($result)->toBeInt();
        expect($result)->toEqual($newStaffId);
    });

    test('permissions get', function () {
        $data['id'] = 1;

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \Tests\Helpers\DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_get($data);
        expect($result)->toBeArray();
    });

    test('permissions update', function () {
        $data = [
            'id' => '1',
            'permissions' => 'default',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('setPermissions')
            ->willReturn(true);

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Tests\Helpers\TestLogger();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_update($data);
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('group get pairs', function () {
        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupPair')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->group_get_pairs([]);
        expect($result)->toBeArray();
    });

    test('group get list', function () {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = container();
        $di['pager'] = $pagerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get_list($data);
        expect($result)->toBeArray();
    });

    test('group create', function () {
        $data['name'] = 'Prime Group';
        $newGroupId = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createGroup')
            ->willReturn($newGroupId);

        $di = container();
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_create($data);
        expect($result)->toBeInt();
        expect($result)->toEqual($newGroupId);
    });

    test('group get', function () {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toAdminGroupApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get($data);
        expect($result)->toBeArray();
    });

    test('group delete', function () {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_delete($data);
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('group update', function () {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateGroup')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_update($data);
        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('login history get list', function () {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getActivityAdminHistorySearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => ['id' => 1],
        ];
        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $model = new \Model_ActivityAdminHistory();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = container();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get_list($data);
        expect($result)->toBeArray();
    });

    test('login history get', function () {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ActivityAdminHistory());

        $di = container();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get($data);
        expect($result)->toBeArray();
    });
