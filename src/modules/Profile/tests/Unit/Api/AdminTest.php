<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);
use Box\Mod\Profile\Api\Admin;
use Box\Mod\Profile\Service;

use function Tests\Helpers\container;

test('gets admin profile', function () {
    $service = new Service();

    $model = new Model_Admin();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->role = 'admin';
    $model->admin_group_id = 1;
    $model->email = 'admin@fossbilling.org';
    $model->name = 'Admin';
    $model->signature = 'Sincerely';
    $model->status = 'active';
    $model->created_at = '2014-01-01';
    $model->updated_at = '2014-01-01';

    $adminApi = new Admin();
    $adminApi->setIdentity($model);
    $adminApi->setService($service);
    $result = $adminApi->get();
    $expected = [
        'id' => $model->id,
        'role' => $model->role,
        'admin_group_id' => $model->admin_group_id,
        'email' => $model->email,
        'name' => $model->name,
        'signature' => $model->signature,
        'status' => $model->status,
        'api_token' => null,
        'created_at' => $model->created_at,
        'updated_at' => $model->updated_at,
    ];
    expect($result)->toBe($expected);
});

test('logs out admin', function () {
    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('destroy')
        ->atLeast()->once();

    $di = container();
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $adminApi = new Admin();
    $adminApi->setDi($di);
    $result = $adminApi->logout();
    expect($result)->toBeTrue();
});

test('updates admin profile', function () {
    $model = new Model_Admin();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateAdmin')
        ->once()
        ->andReturn(true);

    $adminApi = new Admin();
    $adminApi->setIdentity($model);
    $adminApi->setService($serviceMock);
    $result = $adminApi->update(['name' => 'Root']);
    expect($result)->toBeTrue();
});

test('generates api key', function () {
    $model = new Model_Admin();

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generateNewApiKey')
        ->once()
        ->andReturn(true);

    $adminApi = new Admin();
    $adminApi->setIdentity($model);
    $adminApi->setService($serviceMock);
    $result = $adminApi->generate_api_key([]);
    expect($result)->toBeTrue();
});

test('throws exception when changing password without required params', function () {
    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi = new Admin();
    $adminApi->setDi($di);

    expect(fn () => $adminApi->change_password([]))
        ->toThrow(FOSSBilling\Exception::class);
});

test('changes password', function () {
    $di = container();
    $di['validator'] = new FOSSBilling\Validate();
    $di['password'] = new FOSSBilling\PasswordManager();

    $model = new Model_Admin();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->pass = $di['password']->hashIt('oldpw');

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('changeAdminPassword')
        ->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('invalidateSessions')
        ->once();

    $adminApi = new Admin();
    $adminApi->setDi($di);
    $adminApi->setIdentity($model);
    $adminApi->setService($serviceMock);
    $result = $adminApi->change_password([
        'current_password' => 'oldpw',
        'new_password' => '84asasd221AS',
        'confirm_password' => '84asasd221AS',
    ]);
    expect($result)->toBeTrue();
});
