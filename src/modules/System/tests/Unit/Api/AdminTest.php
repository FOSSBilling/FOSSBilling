<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\System\Config;

use function Tests\Helpers\container;

test('dependency injection', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('get params', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
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

test('update cache settings clears the saved redis password only when explicitly requested', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $originalConfig = Config::getConfig();

    try {
        // Saving a password stores it.
        $api->update_cache_settings(['driver' => 'filesystem', 'redis_password' => 'secret']);
        expect(Config::getProperty('cache.redis.password'))->toBe('secret');

        // Leaving the field blank on a later save keeps the existing password.
        $api->update_cache_settings(['driver' => 'filesystem']);
        expect(Config::getProperty('cache.redis.password'))->toBe('secret');

        // The explicit "clear" checkbox is what actually removes it.
        $api->update_cache_settings(['driver' => 'filesystem', 'redis_password_clear' => '1']);
        expect(Config::getProperty('cache.redis.password'))->toBeNull();
    } finally {
        Config::setConfig($originalConfig, false);
    }
});

test('update cache settings rejects a remote driver when no installation identifier is configured', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $originalConfig = Config::getConfig();

    try {
        $config = Config::getConfig();
        $config['info']['instance_id'] = '';
        Config::setConfig($config, false);

        expect(fn () => $api->update_cache_settings(['driver' => 'redis']))
            ->toThrow(FOSSBilling\Exception::class, 'installation identifier');
    } finally {
        Config::setConfig($originalConfig, false);
    }
});

test('update cache settings clears the previously configured backend, not just the new one', function (): void {
    if (hasRedisExtension()) {
        $this->markTestSkipped('This test requires an environment without the redis/relay extension.');
    }

    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $originalConfig = Config::getConfig();

    try {
        // Seed a redis config directly (bypassing the API's own eager-connect check on save,
        // since no redis server is reachable in this test environment).
        $config = Config::getConfig();
        $config['cache'] = ['driver' => 'redis', 'redis' => ['host' => '127.0.0.1', 'port' => 6379]];
        Config::setConfig($config, false);

        // Switching back to filesystem must not throw even though clearing the previous
        // (unreachable) redis backend is attempted as part of the switch.
        expect(fn () => $api->update_cache_settings(['driver' => 'filesystem']))->not->toThrow(Throwable::class);
        expect(Config::getProperty('cache.driver'))->toBe('filesystem');
    } finally {
        Config::setConfig($originalConfig, false);
    }
});

test('update cache settings saves and reads back the redis TLS options', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $originalConfig = Config::getConfig();

    try {
        $api->update_cache_settings([
            'driver' => 'filesystem',
            'redis_tls_enabled' => '1',
            'redis_tls_verify_peer' => '0',
            'redis_tls_verify_peer_name' => '0',
            'redis_tls_allow_self_signed' => '1',
            'redis_tls_cafile' => '/etc/ssl/certs/redis-ca.pem',
        ]);

        $settings = $api->cache_settings();
        expect($settings['redis_tls_enabled'])->toBeTrue();
        expect($settings['redis_tls_verify_peer'])->toBeFalse();
        expect($settings['redis_tls_verify_peer_name'])->toBeFalse();
        expect($settings['redis_tls_allow_self_signed'])->toBeTrue();
        expect($settings['redis_tls_cafile'])->toBe('/etc/ssl/certs/redis-ca.pem');

        // The template pairs every TLS checkbox with a hidden "0" input (same trick already used
        // for redis_password_clear), so an unchecked box still submits explicit "0" rather than
        // being left out of the request entirely - this is what that resulting request looks like.
        $api->update_cache_settings([
            'driver' => 'filesystem',
            'redis_tls_enabled' => '0',
            'redis_tls_verify_peer' => '0',
            'redis_tls_verify_peer_name' => '0',
            'redis_tls_allow_self_signed' => '0',
            'redis_tls_cafile' => '',
        ]);

        $settings = $api->cache_settings();
        expect($settings['redis_tls_enabled'])->toBeFalse();
        expect($settings['redis_tls_verify_peer'])->toBeFalse();
        expect($settings['redis_tls_verify_peer_name'])->toBeFalse();
        expect($settings['redis_tls_allow_self_signed'])->toBeFalse();
        expect($settings['redis_tls_cafile'])->toBe('');
    } finally {
        Config::setConfig($originalConfig, false);
    }
});

test('update cache settings rejects a redis password on a non-loopback host with TLS disabled', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $originalConfig = Config::getConfig();

    try {
        expect(fn (): bool => $api->update_cache_settings([
            'driver' => 'redis',
            'redis_host' => 'redis.example.com',
            'redis_password' => 'secret',
        ]))->toThrow(FOSSBilling\Exception::class, 'without TLS enabled');
    } finally {
        Config::setConfig($originalConfig, false);
    }
});

test('messages', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());
    $data = [
        'mod' => 'extension',
    ];

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock
    ->shouldReceive('hasPermission')
    ->atLeast()->once()
    ->andReturn(true);

    $validatorStub = $this->createStub(FOSSBilling\Validation\Validator::class);

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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());

    $admin = \Tests\Helpers\admin(['id' => 1, 'role' => 'staff']);
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
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());

    $admin = \Tests\Helpers\admin(['id' => 1]);
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andThrow(new class('admin groups unavailable') extends RuntimeException implements Doctrine\DBAL\Exception {});

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);
    $updateFinalization->shouldReceive('getStatus')->once()->withNoArgs()->andReturn(['required' => true]);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchOne')->once()->with("SHOW COLUMNS FROM `admin` LIKE 'role'")->andReturn('role');
    $connection->shouldReceive('fetchOne')->once()->with('SELECT role FROM admin WHERE id = :id', ['id' => 1])->andReturn('admin');

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $api->setDi($di);

    expect($api->update_finalization_status())->toBe(['required' => true]);
});

test('update finalization status rejects legacy non-admin while pending', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());

    $admin = \Tests\Helpers\admin(['id' => 1]);
    $api->setIdentity($admin);

    $staffService = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffService->shouldReceive('isSuperAdministrator')->once()->with(1)->andThrow(new class('admin groups unavailable') extends RuntimeException implements Doctrine\DBAL\Exception {});

    $updateFinalization = Mockery::mock();
    $updateFinalization->shouldReceive('isRequired')->once()->andReturn(true);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchOne')->once()->with("SHOW COLUMNS FROM `admin` LIKE 'role'")->andReturn('role');
    $connection->shouldReceive('fetchOne')->once()->with('SELECT role FROM admin WHERE id = :id', ['id' => 1])->andReturn('staff');

    $di = container();
    $di['update_finalization'] = $updateFinalization;
    $di['mod_service'] = $di->protect(fn (string $serviceName): mixed => $serviceName === 'Staff' ? $staffService : false);
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $api->setDi($di);

    expect(fn (): array => $api->update_finalization_status())
        ->toThrow(FOSSBilling\Exception\InformationException::class, 'You need to be a Super Administrator to finalize this update.');
});

test('update finalization status does not mask unrelated errors from isSuperAdministrator while pending', function (): void {
    $api = apiEndpoint(new Box\Mod\System\Api\Admin());

    $admin = \Tests\Helpers\admin(['id' => 1]);
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
