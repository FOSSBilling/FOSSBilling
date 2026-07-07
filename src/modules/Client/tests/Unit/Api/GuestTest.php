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
use function Tests\Helpers\moduleService;

test('getDi returns dependency injection container', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $di = container();
    $guestClient->setDi($di);
    $getDi = $guestClient->getDi();
    expect($getDi)->toEqual($di);
});

test('create returns int', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [
        'disable_signup' => false,
        'auto_login_after_signup' => false,
        'required' => [],
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpassword',
        'password_confirm' => 'testpassword',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('clientAlreadyExists')
    ->atLeast()->once()
    ->andReturn(false);

    $model = new Model_Client();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock
    ->shouldReceive('guestCreateClient')
    ->atLeast()->once()
    ->andReturn($model);
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();
    $validatorMock->shouldReceive('passwordsMatch')->atLeast()->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->andReturn($data['email']);

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;
    $di['tools'] = $toolsMock;

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $result = $guestClient->create($data);

    expect($result)->toBeInt();
    expect($result)->toEqual($model->id);
});

test('create throws exception when client exists', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [
        'disable_signup' => false,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpassword',
        'password_confirm' => 'testpassword',
    ];

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('clientAlreadyExists')
    ->atLeast()->once()
    ->andReturn(true);
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();

    $model = new Model_Client();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $validatorMock = Mockery::mock(FOSSBilling\Validate::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();
    $validatorMock->shouldReceive('passwordsMatch')->atLeast()->once();

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->andReturn($data['email']);
    $di['tools'] = $toolsMock;

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $guestClient->create($data);
})->throws(FOSSBilling\Exception::class, 'This email address is already registered.');

test('create throws exception when signup is disabled', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [
        'disable_signup' => true,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpassword',
        'password_confirm' => 'testpassword',
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $guestClient->setDi($di);

    $guestClient->create($data);
})->throws(FOSSBilling\Exception::class, 'New registrations are temporarily disabled');

test('create throws exception when passwords do not match', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [
        'disable_signup' => false,
    ];
    $data = [
        'email' => 'test@email.com',
        'first_name' => 'John',
        'password' => 'testpassword',
        'password_confirm' => 'wrongpassword',
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $guestClient->setDi($di);

    $guestClient->create($data);
})->throws(FOSSBilling\Exception::class, 'Passwords do not match.');

test('login returns array', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $data = [
        'email' => 'test@example.com',
        'password' => 'sezam',
    ];

    $model = new Model_Client();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
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

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('set')->atLeast()->once();
    $sessionMock->shouldReceive('getId')->atLeast()->once();
    $sessionMock->shouldReceive('regenerateId')->atLeast()->once();
    $sessionMock->shouldReceive('delete')->atLeast()->once();

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('transferFromOtherSession')->atLeast()->once()
        ->andReturn(true);

    $toolsStub = Mockery::mock(FOSSBilling\Tools::class);
    $toolsStub->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->with($data['email'], true, false)->andReturn($data['email']);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = $toolsStub;
    $di['mod_service'] = $di->protect(moduleService(['cart' => $cartServiceMock]));

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $results = $guestClient->login($data);

    expect($results)->toBeArray();
});

test('resetPassword returns true with new flow', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $data['email'] = 'John@exmaple.com';

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->id = 1;
    $modelClient->status = Model_Client::ACTIVE;

    $dbMock = Mockery::mock('\Box_Database');

    $dbMock->shouldReceive('findOne')->andReturn($modelClient);

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('createPasswordResetRequestForClient')->atLeast()->once()->with($modelClient)->andReturn('hashedString');
    $serviceMock->shouldReceive('sendPasswordResetRequestEmailForClient')->atLeast()->once()->with($modelClient, 'hashedString');

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->andReturn($data['email']);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['tools'] = $toolsMock;

    $guestClient->setDi($di);

    $result = $guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('resetPassword returns true when email not found', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $data['email'] = 'joghn@example.eu';

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
        ->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once()->andReturn($data['email']);
    $di['tools'] = $toolsMock;

    $guestClient->setDi($di);

    $result = $guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword returns true', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $data = [
        'hash' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ];

    $dbMock = Mockery::mock('\Box_Database');

    $modelClient = new Model_Client();
    $modelClient->loadBean(new Tests\Helpers\DummyBean());
    $modelClient->id = 1;
    $modelClient->status = Model_Client::ACTIVE;

    $modelPasswordReset = new Model_ClientPasswordReset();
    $modelPasswordReset->loadBean(new Tests\Helpers\DummyBean());
    $modelPasswordReset->created_at = date('Y-m-d H:i:s', time() - 300);

    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn($modelPasswordReset);

    $dbMock->shouldReceive('getExistingModelById')->atLeast()->once()->andReturn($modelClient);

    $dbMock->shouldReceive('store')->atLeast()->once();

    $dbMock->shouldReceive('trash')->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->times(2);

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once();

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailServiceMock]));

    $guestClient->setDi($di);

    $result = $guestClient->update_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword throws exception when reset not found', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $data = [
        'hash' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->atLeast()->once()->andReturn(null);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestClient->setDi($di);

    $guestClient->update_password($data);
})->throws(FOSSBilling\Exception::class, 'The link has expired or you have already reset your password.');

test('required returns array', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

    $guestClient->setDi($di);

    $result = $guestClient->required();
    expect($result)->toBeArray();
});

test('custom_fields returns fields sorted alphabetically by title', function (): void {
    $guestClient = new Box\Mod\Client\Api\Guest();
    $configArr = [
        'custom_fields' => [
            'custom_2' => ['active' => true, 'title' => 'VAT Number'],
            'custom_1' => ['active' => true, 'title' => 'Company Name'],
            'custom_3' => ['active' => true, 'title' => 'Address'],
        ],
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

    $guestClient->setDi($di);

    $result = $guestClient->custom_fields();
    expect(array_keys($result))->toBe(['custom_3', 'custom_1', 'custom_2']);
});
