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

function staffGuestTestEventDispatcher(): object
{
    return new class {
        /** @var list<FOSSBilling\Events\Event> */
        public array $dispatched = [];

        public function dispatch(FOSSBilling\Events\Event $event): FOSSBilling\Events\Event
        {
            $this->dispatched[] = $event;

            return $event;
        }
    };
}

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

test('password reset requires an email', function (): void {
    $dispatcher = new FOSSBilling\Api\Dispatcher();
    $guestApi = new Box\Mod\Staff\Api\Guest();

    expect(fn () => $dispatcher->validateRequiredParams($guestApi, 'passwordreset', []))
        ->toThrow(FOSSBilling\InformationException::class, 'Email required');
});

test('password reset request dispatches only IP metadata before email validation', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')->once()->andReturn([]);

    $eventDispatcher = staffGuestTestEventDispatcher();
    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')
        ->once()
        ->with('private@example.com')
        ->andReturnUsing(function () use ($eventDispatcher): string {
            expect($eventDispatcher->dispatched)->toHaveCount(1);
            expect($eventDispatcher->dispatched[0])->toBeInstanceOf(Box\Mod\Staff\Event\BeforeStaffPasswordResetRequestEvent::class);

            return 'private@example.com';
        });

    $rateLimiter = Mockery::mock(FOSSBilling\Security\RateLimiter::class);
    $rateLimiter->shouldReceive('consume')
        ->once()
        ->with('staff_password_reset_ip', '192.0.2.11')
        ->andReturn(new FOSSBilling\Security\RateLimitResult('staff_password_reset_ip', true, 5, 0));

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['tools'] = $toolsMock;
    $di['rate_limiter'] = $rateLimiter;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestApi->setMod($modMock);
    $guestApi->setDi($di);
    $guestApi->setIp('192.0.2.11');

    expect($guestApi->passwordreset(['email' => 'private@example.com']))->toBeTrue();
    expect($eventDispatcher->dispatched)->toHaveCount(1);
    expect($eventDispatcher->dispatched[0])->toEqual(new Box\Mod\Staff\Event\BeforeStaffPasswordResetRequestEvent('192.0.2.11'));
    expect(get_object_vars($eventDispatcher->dispatched[0]))->toBe(['ip' => '192.0.2.11']);
});

test('password reset confirmation dispatches before required-field validation', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Staff\Api\Guest());
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')->once()->andReturn([]);

    $eventDispatcher = staffGuestTestEventDispatcher();
    $rateLimiter = Mockery::mock(FOSSBilling\Security\RateLimiter::class);
    $rateLimiter->shouldReceive('consumeOrThrow')
        ->once()
        ->with('staff_password_reset_confirm_post_ip', '192.0.2.12')
        ->andReturn(new FOSSBilling\Security\RateLimitResult('staff_password_reset_confirm_post_ip', false, 5, 4));

    $validator = Mockery::mock(FOSSBilling\Validate::class);
    $validator->shouldReceive('checkRequiredParamsForArray')->once()->andReturnUsing(function () use ($eventDispatcher): void {
        expect($eventDispatcher->dispatched)->toHaveCount(1);
        expect($eventDispatcher->dispatched[0])->toBeInstanceOf(Box\Mod\Staff\Event\BeforeStaffPasswordResetConfirmationEvent::class);

        throw new FOSSBilling\InformationException('Code required');
    });

    $di = container();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['rate_limiter'] = $rateLimiter;
    $di['validator'] = $validator;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestApi->setMod($modMock);
    $guestApi->setDi($di);
    $guestApi->setIp('192.0.2.12');

    expect(fn () => $guestApi->update_password([
        'code' => 'private-reset-code',
        'password' => 'private-password',
        'password_confirm' => 'private-password',
    ]))->toThrow(FOSSBilling\InformationException::class, 'Code required');

    expect($eventDispatcher->dispatched)->toHaveCount(1);
    expect($eventDispatcher->dispatched[0])->toEqual(new Box\Mod\Staff\Event\BeforeStaffPasswordResetConfirmationEvent('192.0.2.12'));
    expect(get_object_vars($eventDispatcher->dispatched[0]))->toBe(['ip' => '192.0.2.12']);
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

    $eventDispatcher = staffGuestTestEventDispatcher();

    $passwordMock = Mockery::mock(FOSSBilling\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once();

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->once()->andReturnUsing(function () use ($eventDispatcher): void {
        expect($eventDispatcher->dispatched)->toHaveCount(2);
        expect($eventDispatcher->dispatched[1])->toBeInstanceOf(Box\Mod\Staff\Event\AfterStaffPasswordResetEvent::class);
    });

    $profileServiceMock = Mockery::mock(Box\Mod\Profile\Service::class);
    $profileServiceMock->shouldReceive('invalidateSessions')->atLeast()->once();

    $di = container();
    $di['em']->shouldReceive('getRepository')->with(Box\Mod\Staff\Entity\AdminPasswordReset::class)->andReturn($passwordResetRepository);
    $di['em']->shouldReceive('persist')->once()->with($admin);
    $di['em']->shouldReceive('remove')->once()->with($passwordReset)->andReturnUsing(function () use ($eventDispatcher): void {
        expect($eventDispatcher->dispatched)->toHaveCount(2);
        expect($eventDispatcher->dispatched[1])->toBeInstanceOf(Box\Mod\Staff\Event\AfterStaffPasswordResetEvent::class);
    });
    $di['em']->shouldReceive('flush')->atLeast()->once();
    $di['event_dispatcher'] = $eventDispatcher;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailServiceMock, 'profile' => $profileServiceMock]));

    $guestApi->setMod($modMock);
    $guestApi->setDi($di);
    $guestApi->setIp('192.0.2.10');

    $guestApi->update_password([
        'code' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ]);

    expect($eventDispatcher->dispatched)->toHaveCount(2);
    expect($eventDispatcher->dispatched[0])->toEqual(new Box\Mod\Staff\Event\BeforeStaffPasswordResetConfirmationEvent('192.0.2.10'));
    expect(get_object_vars($eventDispatcher->dispatched[0]))->toBe(['ip' => '192.0.2.10']);
    expect($eventDispatcher->dispatched[1])->toEqual(new Box\Mod\Staff\Event\AfterStaffPasswordResetEvent(1));
    expect(get_object_vars($eventDispatcher->dispatched[1]))->toBe(['adminId' => 1]);
});
