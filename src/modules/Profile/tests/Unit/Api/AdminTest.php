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
use function Tests\Helpers\createEntity;

test('gets admin profile', function (): void {
    $service = new Service();

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class, [
        'id' => 1,
        'email' => 'admin@fossbilling.org',
        'name' => 'Admin',
        'signature' => 'Sincerely',
        'status' => 'active',
        'created_at' => '2014-01-01',
        'updated_at' => '2014-01-01',
        'timezone' => null,
    ]);

    $adminApi = apiEndpoint(new Admin());
    $adminApi->setIdentity($model);
    $adminApi->setService($service);
    $result = $adminApi->get();
    $expected = [
        'id' => $model->getId(),
        'email' => $model->getEmail(),
        'name' => $model->getName(),
        'signature' => $model->getSignature(),
        'status' => $model->getStatus(),
        'api_token' => $model->getApiToken(),
        'timezone' => $model->getTimezone(),
        'created_at' => $model->getCreatedAt()?->format('Y-m-d'),
        'updated_at' => $model->getUpdatedAt()?->format('Y-m-d'),
    ];
    expect($result)->toBe($expected);
});

test('logs out admin', function (): void {
    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('destroy')
        ->atLeast()->once();

    $di = container();
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $adminApi = apiEndpoint(new Admin());
    $adminApi->setDi($di);
    $result = $adminApi->logout();
    expect($result)->toBeTrue();
});

test('updates admin profile', function (): void {
    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('updateAdmin')
        ->once()
        ->andReturn(true);

    $adminApi = apiEndpoint(new Admin());
    $adminApi->setIdentity($model);
    $adminApi->setService($serviceMock);
    $result = $adminApi->update(['name' => 'Root']);
    expect($result)->toBeTrue();
});

test('generates api key', function (): void {
    $model = createEntity(Box\Mod\Staff\Entity\Admin::class);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('generateNewApiKey')
        ->once()
        ->andReturn(true);

    $adminApi = apiEndpoint(new Admin());
    $adminApi->setIdentity($model);
    $adminApi->setService($serviceMock);
    $result = $adminApi->generate_api_key([]);
    expect($result)->toBeTrue();
});

test('throws exception when changing password without required params', function (): void {
    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi = apiEndpoint(new Admin());
    $adminApi->setDi($di);

    expect(fn () => $adminApi->change_password([]))
        ->toThrow(FOSSBilling\Exception::class);
});

test('changes password', function (): void {
    $di = container();
    $di['validator'] = new FOSSBilling\Validate();
    $di['password'] = new FOSSBilling\PasswordManager();

    $model = createEntity(Box\Mod\Staff\Entity\Admin::class, ['pass' => $di['password']->hashIt('oldpw')]);

    $serviceMock = Mockery::mock(Service::class);
    $serviceMock->shouldReceive('changeAdminPassword')
        ->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('invalidateSessions')
        ->once();

    $adminApi = apiEndpoint(new Admin());
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
