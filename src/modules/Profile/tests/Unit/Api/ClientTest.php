<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);
use Box\Mod\Profile\Api\Client;
use Box\Mod\Profile\Service;

use function Tests\Helpers\container;

test('gets client profile', function (): void {
    $api = new Client();
    $clientService = Mockery::mock(Box\Mod\Client\Service::class);
    $clientService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $clientService);
    $api->setDi($di);
    $api->setIdentity(new Model_Client());

    $result = $api->get();
    expect($result)->toBeArray();
});

test('updates client profile', function (): void {
    $api = new Client();
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('updateClient')
        ->atLeast()->once()
        ->andReturn(true);

    $api->setService($service);
    $api->setIdentity(new Model_Client());

    $result = $api->update([]);
    expect($result)->toBeTrue();
});

test('gets api key', function (): void {
    $api = new Client();
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->api_token = '16047a3e69f5245756d73b419348f0c7';
    $api->setIdentity($client);

    $result = $api->api_key_get([]);
    expect($result)->toBe($client->api_token);
});

test('resets api key', function (): void {
    $api = new Client();
    $apiKey = '16047a3e69f5245756d73b419348f0c7';
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('resetApiKey')
        ->atLeast()->once()
        ->andReturn($apiKey);

    $api->setService($service);
    $api->setIdentity(new Model_Client());

    $result = $api->api_key_reset([]);
    expect($result)->toBe($apiKey);
});

test('changes client password', function (): void {
    $api = new Client();
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('changeClientPassword')
        ->atLeast()->once()
        ->andReturn(true);
    $service->shouldReceive('invalidateSessions')
        ->atLeast()->once();

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isPasswordStrong')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['validator'] = $validatorMock;
    $di['password'] = new FOSSBilling\PasswordManager();

    $model = new Model_Client();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->pass = $di['password']->hashIt('oldpw');

    $api->setDi($di);
    $api->setService($service);
    $api->setIdentity($model);

    $data = [
        'current_password' => 'oldpw',
        'new_password' => '16047a3e69f5245756d73b419348f0c7',
        'confirm_password' => '16047a3e69f5245756d73b419348f0c7',
    ];
    $result = $api->change_password($data);
    expect($result)->toBeTrue();
});

test('throws exception when passwords do not match', function (): void {
    $api = new Client();
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('changeClientPassword')
        ->never()
        ->andReturn(true);

    $di = container();

    $api->setDi($di);
    $api->setService($service);
    $api->setIdentity(new Model_Client());

    $data = [
        'current_password' => '1234',
        'new_password' => '16047a3e69f5245756d73b419348f0c7',
        'confirm_password' => '7c0f843914b37d6575425f96e3a74061',
    ];

    expect(fn () => $api->change_password($data))
        ->toThrow(Exception::class);
});

test('logs out client', function (): void {
    $api = new Client();
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('logoutClient')
        ->atLeast()->once()
        ->andReturn(true);
    $api->setService($service);

    $result = $api->logout();
    expect($result)->toBeTrue();
});
