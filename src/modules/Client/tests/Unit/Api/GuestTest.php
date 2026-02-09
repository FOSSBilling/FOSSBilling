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

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('clientAlreadyExists')
        ->willReturn(false);

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $serviceMock->expects($this->atLeastOnce())
        ->method('guestCreateClient')
        ->willReturn($model);
    $serviceMock->expects($this->atLeastOnce())
        ->method('checkExtraRequiredFields');
    $serviceMock->expects($this->atLeastOnce())
        ->method('checkCustomFields');

    $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
    $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

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

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('clientAlreadyExists')
        ->willReturn(true);
    $serviceMock->expects($this->atLeastOnce())
        ->method('checkExtraRequiredFields');
    $serviceMock->expects($this->atLeastOnce())
        ->method('checkCustomFields');

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
    $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
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

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('authorizeClient')
        ->with($data['email'], $data['password'])
        ->willReturn($model);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toSessionArray')
        ->willReturn([]);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
        ->disableOriginalConstructor()
        ->getMock();

    $sessionMock->expects($this->atLeastOnce())
        ->method('set');

    $cartServiceMock = $this->createMock(\Box\Mod\Cart\Service::class);
    $cartServiceMock->expects($this->once())
        ->method('transferFromOtherSession')
        ->willReturn(true);

    $toolsStub = $this->createStub(\FOSSBilling\Tools::class);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolsStub;
    $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $cartServiceMock);

    $this->guestClient->setDi($di);
    $this->guestClient->setService($serviceMock);

    $results = $this->guestClient->login($data);

    expect($results)->toBeArray();
});

test('resetPassword returns true with new flow', function () {
    $data['email'] = 'John@exmaple.com';

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $modelPasswordReset = new \Model_ClientPasswordReset();
    $modelPasswordReset->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = $this->createMock('\Box_Database');

    $dbMock->expects($this->exactly(2))->method('findOne')
        ->willReturnOnConsecutiveCalls($modelClient, null);

    $dbMock->expects($this->once())
        ->method('dispense')->willReturn($modelPasswordReset);

    $dbMock->expects($this->atLeastOnce())
        ->method('store')->willReturn(1);

    $emailServiceMock = $this->createMock(\Box\Mod\Email\Service::class);
    $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate');

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->once())
        ->method('validateAndSanitizeEmail')->willReturn($data['email']);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $emailServiceMock);
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['tools'] = $toolsMock;

    $this->guestClient->setDi($di);

    $result = $this->guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('resetPassword returns true when email not found', function () {
    $data['email'] = 'joghn@example.eu';

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->atLeastOnce())->method('fire');

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('findOne')->willReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;

    $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
    $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
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

    $dbMock = $this->createMock('\Box_Database');

    $modelClient = new \Model_Client();
    $modelClient->loadBean(new \Tests\Helpers\DummyBean());

    $modelPasswordReset = new \Model_ClientPasswordReset();
    $modelPasswordReset->loadBean(new \Tests\Helpers\DummyBean());
    $modelPasswordReset->created_at = date('Y-m-d H:i:s', time() - 300);

    $dbMock->expects($this->once())
        ->method('findOne')->willReturn($modelPasswordReset);

    $dbMock->expects($this->once())
        ->method('getExistingModelById')->willReturn($modelClient);

    $dbMock->expects($this->once())
        ->method('store');

    $dbMock->expects($this->once())
        ->method('trash');

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->exactly(2))
        ->method('fire');

    $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
    $passwordMock->expects($this->once())
        ->method('hashIt');

    $emailServiceMock = $this->createMock(\Box\Mod\Email\Service::class);
    $emailServiceMock->expects($this->once())
        ->method('sendTemplate');

    $di = container();
    $di['db'] = $dbMock;
    $di['events_manager'] = $eventMock;
    $di['password'] = $passwordMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $emailServiceMock);

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

    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->once())
        ->method('findOne')->willReturn(null);

    $eventMock = $this->createMock('\Box_EventManager');
    $eventMock->expects($this->once())
        ->method('fire');

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
