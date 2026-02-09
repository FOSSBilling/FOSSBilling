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
    $this->guestClient = new \Box\Mod\Client\Api\Guest();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->guestClient->setDi($di);
    $getDi = $this->guestClient->getDi();
    expect($getDi)->toEqual($di);
});

test('create returns int', function () {
    $configArr = [
        'disable_signup' => false,
        'required' => [],
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpaswword',
        'password_confirm' => 'testpaswword',
    ];

    $serviceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('clientAlreadyExists')
    ->atLeast()->once()
    ->andReturn(false);

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock
    ->shouldReceive('guestCreateClient')
    ->atLeast()->once()
    ->andReturn($model);
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;
    $di['tools'] = $toolsMock;

    $this->guestClient->setDi($di);
    $this->guestClient->setService($serviceMock);

    $result = $this->guestClient->create($data);

    expect($result)->toBeInt();
    expect($result)->toEqual($model->id);
});

test('create throws exception when client exists', function () {
    $configArr = [
        'disable_signup' => false,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpaswword',
        'password_confirm' => 'testpaswword',
    ];

    $serviceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('clientAlreadyExists')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $validatorMock = Mockery::mock(\FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $this->guestClient->setDi($di);
    $this->guestClient->setService($serviceMock);

    $this->guestClient->create($data);
})->throws(\FOSSBilling\Exception::class, 'This email address is already registered.');

test('create throws exception when signup is disabled', function () {
    $configArr = [
        'disable_signup' => true,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpaswword',
        'password_confirm' => 'testpaswword',
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $this->guestClient->setDi($di);

    $this->guestClient->create($data);
})->throws(\FOSSBilling\Exception::class, 'New registrations are temporary disabled');

test('create throws exception when passwords do not match', function () {
    $configArr = [
        'disable_signup' => false,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpaswword',
        'password_confirm' => 'wrongpaswword',
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $this->guestClient->setDi($di);

    $this->guestClient->create($data);
})->throws(\FOSSBilling\Exception::class, 'Passwords do not match.');

test('login returns array', function () {
    $data = [
        'email' => 'test@example.com',
        'password' => 'sezam',
    ];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('authorizeClient')
    ->atLeast()->once()
        ->with($data['email'], $data['password'])
    ->andReturn($model);
    $serviceMock
    ->shouldReceive('toSessionArray')
    ->atLeast()->once()
    ->andReturn([]);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $sessionMock = Mockery::mock(\FOSSBilling\Session::class);
    $sessionMock->shouldReceive('set')->atLeast()->once();
    $sessionMock->shouldReceive('getId')->atLeast()->once();
    $sessionMock->shouldReceive('delete')->atLeast()->once();

    $cartServiceMock = Mockery::mock(\Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive("transferFromOtherSession")->atLeast()->once()
        ->andReturn(true);

    $toolsStub = $this->createStub(\FOSSBilling\Tools::class);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolsStub;
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $cartServiceMock);

    $this->guestClient->setDi($di);
    $this->guestClient->setService($serviceMock);

    $results = $this->guestClient->login($data);

    expect($results)->toBeArray();
});

test('resetPassword returns true with new flow', function () {
    $data['email'] = 'John@exmaple.com';

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $modelPasswordReset = new \Model_ClientPasswordReset();
    $modelPasswordReset->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');

    $dbMock->shouldReceive("findOne")->andReturn($modelClient, null);

    $dbMock->shouldReceive('dispense')->atLeast()->once()->andReturn($modelPasswordReset);

    $dbMock
        ->shouldReceive('store')->atLeast()->once()->andReturn(1);

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once();

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->andReturn($data['email']);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $emailServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolsMock;

    $this->guestClient->setDi($di);

    $result = $this->guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('resetPassword returns true when email not found', function () {
    $data['email'] = 'joghn@example.eu';

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
        ->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;

    $this->guestClient->setDi($di);

    $result = $this->guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword returns true', function () {
    $data = [
        'hash' => 'hashedString',
        'password' => 'newPassword',
        'password_confirm' => 'newPassword',
    ];

    $dbMock = Mockery::mock('\Box_Database');

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $modelPasswordReset = new \Model_ClientPasswordReset();
    $modelPasswordReset->loadBean(new \Tests\Helpers\DummyBean());
    $modelPasswordReset->created_at = date('Y-m-d H:i:s', time() - 300);

    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($modelPasswordReset);

    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($modelClient);

    $dbMock->shouldReceive("store")->atLeast()->once();

    $dbMock->shouldReceive("trash")->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive("fire")->times(2);

    $passwordMock = Mockery::mock(\FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive("hashIt")->atLeast()->once();

    $emailServiceMock = Mockery::mock(\Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive("sendTemplate")->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['password'] = $passwordMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $emailServiceMock);

    $this->guestClient->setDi($di);

    $result = $this->guestClient->update_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword throws exception when reset not found', function () {
    $data = [
        'hash' => 'hashedString',
        'password' => 'newPassword',
        'password_confirm' => 'newPassword',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive("fire")->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;

    $this->guestClient->setDi($di);

    $this->guestClient->update_password($data);
})->throws(\FOSSBilling\Exception::class, 'The link has expired or you have already reset your password.');

test('required returns array', function () {
    $configArr = [];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

    $this->guestClient->setDi($di);

    $result = $this->guestClient->required();
    expect($result)->toBeArray();
});
