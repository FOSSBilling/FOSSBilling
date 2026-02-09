<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);
use Box\Mod\Profile\Service;
use function Tests\Helpers\container;

test('gets dependency injection container', function () {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets admin identity array', function () {
    $model = new \Model_Admin();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $result = $service->getAdminIdentityArray($model);
    expect($result)->toBeArray();
});

test('updates admin', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;

    $model = new \Model_Admin();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'signature' => 'new signature',
        'email' => 'example@gmail.com',
        'name' => 'Admin',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateAdmin($model, $data);
    expect($result)->toBeTrue();
});

test('generates new api key', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['tools'] = new \FOSSBilling\Tools();

    $model = new \Model_Admin();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $service->setDi($di);

    $result = $service->generateNewApiKey($model);
    expect($result)->toBeTrue();
});

test('changes admin password', function () {
    $password = 'new_pass';
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['password'] = $passwordMock;

    $model = new \Model_Admin();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $service->setDi($di);

    $result = $service->changeAdminPassword($model, $password);
    expect($result)->toBeTrue();
});

test('updates client', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $modMock = Mockery::mock(\FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 0,
        ]);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail');

    $clientServiceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->andReturn(false);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);
    $di['tools'] = $toolsMock;

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'email' => 'email@example.com',
        'first_name' => 'string',
        'last_name' => 'string',
        'gender' => 'other',
        'birthday' => '1981-01-01',
        'company' => 'string',
        'company_vat' => 'string',
        'company_number' => 'string',
        'type' => 'string',
        'address_1' => 'string',
        'address_2' => 'string',
        'phone_cc' => random_int(10, 300),
        'phone' => random_int(10000, 90000),
        'country' => 'string',
        'postcode' => 'string',
        'city' => 'string',
        'state' => 'string',
        'document_type' => 'passport',
        'document_nr' => random_int(100000, 900000),
        'lang' => 'string',
        'notes' => 'string',
        'custom_1' => 'string',
        'custom_2' => 'string',
        'custom_3' => 'string',
        'custom_4' => 'string',
        'custom_5' => 'string',
        'custom_6' => 'string',
        'custom_7' => 'string',
        'custom_8' => 'string',
        'custom_9' => 'string',
        'custom_10' => 'string',
    ];

    $service = new Service();
    $service->setDi($di);
    $result = $service->updateClient($model, $data);
    expect($result)->toBeTrue();
});

test('throws exception when email change is not allowed', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->never()
        ->andReturn(true);

    $modMock = Mockery::mock(\FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 1,
        ]);

    $clientServiceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->never()
        ->andReturn(false);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn () => $service->updateClient($model, $data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('throws exception when email already registered', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->never()
        ->andReturn(true);

    $modMock = Mockery::mock(\FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn([
            'disable_change_email' => 0,
        ]);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail');

    $clientServiceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $clientServiceMock->shouldReceive('emailAlreadyRegistered')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $clientServiceMock);
    $di['mod'] = $di->protect(fn (): \Mockery\MockInterface => $modMock);
    $di['tools'] = $toolsMock;

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $data = ['email' => 'email@example.com'];

    $service = new Service();
    $service->setDi($di);

    expect(fn () => $service->updateClient($model, $data))
        ->toThrow(\FOSSBilling\Exception::class);
});

test('resets api key', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['db'] = $dbMock;
    $di['tools'] = new \FOSSBilling\Tools();

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $service->setDi($di);
    $result = $service->resetApiKey($model);
    expect($result)->toBeString();
    expect(strlen($result))->toBe(32);
});

test('changes client password', function () {
    $emMock = Mockery::mock('\Box_EventManager');
    $emMock->shouldReceive('fire')
        ->atLeast()->once()
        ->andReturn(true);

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(true);

    $password = 'new password';

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')
        ->with($password);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $emMock;
    $di['db'] = $dbMock;
    $di['password'] = $passwordMock;

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $service->setDi($di);
    $result = $service->changeClientPassword($model, $password);
    expect($result)->toBeTrue();
});

test('logs out client', function () {
    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive('destroy')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['session'] = $sessionMock;

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $service = new Service();
    $service->setDi($di);
    $result = $service->logoutClient();
    expect($result)->toBeTrue();
});
