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

test('dependency injection', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('get params', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getParams')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->get_params($data);
    expect($result)->toBeArray();
});

test('update params', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('updateParams')
    ->atLeast()->once()
    ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->update_params($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('messages', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $di = container();

    $api->setDi($di);

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getMessages')
    ->atLeast()->once()
    ->andReturn([]);

    $api->setService($serviceMock);

    $result = $api->messages($data);
    expect($result)->toBeArray();
});

test('template exists', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [
        'file' => 'testing.txt',
    ];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('templateExists')
    ->atLeast()->once()
    ->andReturn(true);

    $api->setService($serviceMock);

    $result = $api->template_exists($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('env', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [];

    $serviceMock = Mockery::mock(Box\Mod\System\Service::class);
    $serviceMock
    ->shouldReceive('getEnv')
    ->atLeast()->once()
    ->andReturn([]);

    $di = container();

    $api->setDi($di);
    $api->setService($serviceMock);

    $result = $api->env($data);
    expect($result)->toBeArray();
});

test('is allowed', function (): void {
    $api = new Box\Mod\System\Api\Admin();
    $data = [
        'mod' => 'extension',
    ];

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock
    ->shouldReceive('hasPermission')
    ->atLeast()->once()
    ->andReturn(true);

    $validatorStub = $this->createStub(FOSSBilling\Validate::class);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
        if ($serviceName == 'Staff') {
            return $staffServiceMock;
        }

        return false;
    });
    $di['validator'] = $validatorStub;
    $api->setDi($di);

    $result = $api->is_allowed($data);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('update finalization status allows super administrator while pending', function (): void {
    $api = new Box\Mod\System\Api\Admin();

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;
    $admin->role = 'staff';
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andReturn(true);

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);
    $updateFinalization->shouldReceive('getStatus')->once()->withNoArgs()->andReturn(['required' => true]);

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $api->setDi($di);

    expect($api->update_finalization_status())->toBe(['required' => true]);
});

test('update finalization status falls back to legacy admin while pending', function (): void {
    $api = new Box\Mod\System\Api\Admin();

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andThrow(new class('admin groups unavailable') extends RuntimeException implements Doctrine\DBAL\Exception {});

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);
    $updateFinalization->shouldReceive('getStatus')->once()->withNoArgs()->andReturn(['required' => true]);

    $db = Mockery::mock(Box_Database::class);
    $db->shouldReceive('getCell')->once()->with("SHOW COLUMNS FROM `admin` LIKE 'role'")->andReturn('role');
    $db->shouldReceive('getCell')->once()->with('SELECT role FROM admin WHERE id = :id', ['id' => 1])->andReturn('admin');

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $di['db'] = $db;
    $api->setDi($di);

    expect($api->update_finalization_status())->toBe(['required' => true]);
});

test('update finalization status rejects legacy non-admin while pending', function (): void {
    $api = new Box\Mod\System\Api\Admin();

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andThrow(new class('admin groups unavailable') extends RuntimeException implements Doctrine\DBAL\Exception {});

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);

    $db = Mockery::mock(Box_Database::class);
    $db->shouldReceive('getCell')->once()->with("SHOW COLUMNS FROM `admin` LIKE 'role'")->andReturn('role');
    $db->shouldReceive('getCell')->once()->with('SELECT role FROM admin WHERE id = :id', ['id' => 1])->andReturn('staff');

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $di['db'] = $db;
    $api->setDi($di);

    expect(fn (): array => $api->update_finalization_status())
        ->toThrow(FOSSBilling\InformationException::class, 'You need to be a Super Administrator to finalize this update.');
});

test('update finalization status does not mask unrelated errors from isSuperAdministrator while pending', function (): void {
    $api = new Box\Mod\System\Api\Admin();

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andThrow(new RuntimeException('unexpected failure'));

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $api->setDi($di);

    expect(fn (): array => $api->update_finalization_status())
        ->toThrow(RuntimeException::class, 'unexpected failure');
});
