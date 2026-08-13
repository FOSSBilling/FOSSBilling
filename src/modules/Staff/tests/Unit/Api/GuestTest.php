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
use function Tests\Helpers\createEntity;
use function Tests\Helpers\moduleService;

test('get di', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('login without email', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($guestApi, 'login', ['email' => null, 'password' => 'pass']))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('login without password', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi->setDi($di);
    expect(fn () => $guestApi->login(['email' => 'email@domain.com']))->toThrow(FOSSBilling\Exception::class);
});

test('successful login', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock
    ->shouldReceive('getConfig')
    ->atLeast()->once()
    ->andReturn([]);

    $serviceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $serviceMock
    ->shouldReceive('login')
    ->atLeast()->once()
    ->andReturn([]);

    $sessionMock = Mockery::mock(FOSSBilling\Session::class);
    $sessionMock->shouldReceive('delete')->atLeast()->once();

    $di = container();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;
    $di['session'] = $sessionMock;
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $guestApi->setMod($modMock);
    $guestApi->setService($serviceMock);
    $guestApi->setDi($di);
    $result = $guestApi->login(['email' => 'email@domain.com', 'password' => 'pass']);
    expect($result)->toBeArray();
});

test('login check ip exception', function (): void {
    $api = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $configArr = [
        'allowed_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
        'check_ip' => true,
    ];
    $modMock
    ->shouldReceive('getConfig')
    ->atLeast()->once()
    ->andReturn($configArr);

    $di = container();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')->atLeast()->once();
    $di['tools'] = $toolsMock;
    $di['validator'] = new FOSSBilling\Validate();

    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $guestApi->setMod($modMock);
    $guestApi->setDi($di);
    $ip = '192.168.0.1';
    $guestApi->setIp($ip);

    $data = [
        'email' => 'email@domain.com',
        'password' => 'pass',
    ];
    expect(fn () => $guestApi->login($data))
        ->toThrow(FOSSBilling\Exception::class, 'You are not allowed to login to admin area from this IP address.');
});

test('updatePassword invalidates existing sessions', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());

    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $admin = \Tests\Helpers\admin(['id' => 1, 'status' => Box\Mod\Staff\Entity\Admin::STATUS_ACTIVE]);

    $passwordReset = createEntity(Box\Mod\Staff\Entity\AdminPasswordReset::class, ['id' => 1, 'admin' => $admin, 'created_at' => new DateTime('-300 seconds')]);

    $passwordResetRepository = Mockery::mock(Box\Mod\Staff\Repository\AdminPasswordResetRepository::class);
    $passwordResetRepository->shouldReceive('findOneByHash')->once()->with('hashedString')->andReturn($passwordReset);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')->times(2);

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once();

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once();

    $profileServiceMock = Mockery::mock(Box\Mod\Profile\Service::class);
    $profileServiceMock->shouldReceive('invalidateSessions')->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Staff\Entity\AdminPasswordReset::class)->andReturn($passwordResetRepository);
    $di['em']->shouldReceive('persist')->once()->with($admin);
    $di['em']->shouldReceive('remove')->once()->with($passwordReset);
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailServiceMock, 'profile' => $profileServiceMock]));

    $guestApi->setMod($modMock);
    $guestApi->setDi($di);

    $guestApi->update_password([
        'code' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ]);
});
