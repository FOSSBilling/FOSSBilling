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

test('getDi returns dependency injection container', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $di = container();
    $guestClient->setDi($di);
    $getDi = $guestClient->getDi();
    expect($getDi)->toEqual($di);
});

test('create returns true and creates the account for a new email', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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

    $model = new Box\Mod\Client\Entity\Client();
    $prop = new ReflectionProperty($model, 'id');
    $prop->setValue($model, 1);
    $prop = new ReflectionProperty($model, 'email');
    $prop->setValue($model, 'test@email.com');

    $serviceMock
    ->shouldReceive('guestCreateClient')
    ->once()
    ->andReturn($model);
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();

    $validatorMock = Mockery::mock(FOSSBilling\Core\Validation\Validator::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();
    $validatorMock->shouldReceive('passwordsMatch')->atLeast()->once();

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $result = $guestClient->create($data);

    expect($result)->toBeTrue();
});

test('create returns true without creating a duplicate account or disclosing that the email exists', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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
    // The submitted (attacker-controlled) password won't match the real
    // account's password, so the fallback login attempt fails just like an
    // ordinary bad-password login would.
    $serviceMock->shouldReceive('authorizeClient')->atLeast()->once()->andReturn(null);
    $serviceMock->shouldNotReceive('guestCreateClient');

    $validatorMock = Mockery::mock(FOSSBilling\Core\Validation\Validator::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();
    $validatorMock->shouldReceive('passwordsMatch')->atLeast()->once();

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $result = $guestClient->create($data);

    expect($result)->toBeTrue();
});

test('create returns true without creating an account when the per-email signup rate limit is hit', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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
    // Never reached: the email limiter short-circuits before the existence
    // check, so a fresh (not-yet-registered) email can't burn through this
    // path repeatedly to have its true existence state observed.
    $serviceMock->shouldNotReceive('clientAlreadyExists');
    $serviceMock->shouldReceive('checkExtraRequiredFields')->atLeast()->once();
    $serviceMock->shouldReceive('checkCustomFields')->atLeast()->once();
    $serviceMock->shouldReceive('authorizeClient')->atLeast()->once()->andReturn(null);
    $serviceMock->shouldNotReceive('guestCreateClient');

    $validatorMock = Mockery::mock(FOSSBilling\Core\Validation\Validator::class);
    $validatorMock->shouldReceive('isPasswordStrong')->atLeast()->once();
    $validatorMock->shouldReceive('passwordsMatch')->atLeast()->once();

    $rateLimiterMock = new class {
        public function consume(string $policyName, string $subject, int $tokens = 1): FOSSBilling\Core\Security\RateLimitResult
        {
            $limited = $policyName === 'client_signup_email';

            return new FOSSBilling\Core\Security\RateLimitResult($policyName, $limited, null, null);
        }

        public function consumeOrThrow(string $policyName, string $subject, int $tokens = 1): FOSSBilling\Core\Security\RateLimitResult
        {
            return $this->consume($policyName, $subject, $tokens);
        }
    };

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
    $di['validator'] = $validatorMock;
    $di['rate_limiter'] = $rateLimiterMock;

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $result = $guestClient->create($data);

    expect($result)->toBeTrue();
});

test('create throws exception when signup is disabled', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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
})->throws(FOSSBilling\Core\Exception\BaseException::class, 'New registrations are temporarily disabled');

test('create throws exception when passwords do not match', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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
})->throws(FOSSBilling\Core\Exception\BaseException::class, 'Passwords do not match.');

test('login returns array', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $data = [
        'email' => 'test@example.com',
        'password' => 'sezam',
    ];

    $model = createEntity(Box\Mod\Client\Entity\Client::class);

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

    $eventMock = Mockery::mock(FOSSBilling\Core\Event\Manager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $sessionMock = Mockery::mock(FOSSBilling\Core\Session::class);
    $sessionMock->shouldReceive('set')->atLeast()->once();
    $sessionMock->shouldReceive('getId')->atLeast()->once()->andReturn('test_session_id');
    $sessionMock->shouldReceive('regenerateId')->atLeast()->once();
    $sessionMock->shouldReceive('delete')->atLeast()->once();

    $cartServiceMock = Mockery::mock(Box\Mod\Cart\Service::class);
    $cartServiceMock->shouldReceive('transferFromOtherSession')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['session'] = $sessionMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(moduleService(['cart' => $cartServiceMock]));

    $guestClient->setDi($di);
    $guestClient->setService($serviceMock);

    $results = $guestClient->login($data);

    expect($results)->toBeArray();
});

test('resetPassword returns true with new flow', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $data['email'] = 'John@exmaple.com';

    $eventMock = Mockery::mock(FOSSBilling\Core\Event\Manager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $modelClient = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1, 'status' => Box\Mod\Client\Entity\Client::ACTIVE]);

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('findOneByEmailAndActive')->atLeast()->once()->andReturn($modelClient);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Client\Entity\Client::class => $clientRepository,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $serviceMock = Mockery::mock(Box\Mod\Client\Service::class);
    $serviceMock->shouldReceive('createPasswordResetRequestForClient')->atLeast()->once()->andReturn('hashedString');
    $serviceMock->shouldReceive('sendPasswordResetRequestEmailForClient')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventMock;
    $di['mod_service'] = $di->protect(moduleService(['client' => $serviceMock]));
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestClient->setDi($di);

    $result = $guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('resetPassword returns true when email not found', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $data['email'] = 'joghn@example.eu';

    $eventMock = Mockery::mock(FOSSBilling\Core\Event\Manager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $clientRepository = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepository->shouldReceive('findOneByEmailAndActive')->atLeast()->once()->andReturn(null);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Client\Entity\Client::class => $clientRepository,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestClient->setDi($di);

    $result = $guestClient->reset_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword returns true', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $data = [
        'hash' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ];

    $client = new Box\Mod\Client\Entity\Client();
    $rp = new ReflectionProperty($client, 'id');
    $rp->setValue($client, 1);
    $rp = new ReflectionProperty($client, 'status');
    $rp->setValue($client, 'active');

    $passwordReset = new Box\Mod\Client\Entity\ClientPasswordReset();
    $rp = new ReflectionProperty($passwordReset, 'id');
    $rp->setValue($passwordReset, 1);
    $rp = new ReflectionProperty($passwordReset, 'createdAt');
    $rp->setValue($passwordReset, new DateTime('-300 seconds'));
    $passwordReset->setClient($client);

    $passwordResetRepository = Mockery::mock(Box\Mod\Client\Repository\ClientPasswordResetRepository::class);
    $passwordResetRepository->shouldReceive('findOneByHash')->atLeast()->once()->andReturn($passwordReset);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $em->shouldReceive('getRepository')->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Client\Entity\ClientPasswordReset::class => $passwordResetRepository,
        default => Mockery::mock()->shouldIgnoreMissing(),
    });

    $eventMock = Mockery::mock(FOSSBilling\Core\Event\Manager::class);
    $eventMock->shouldReceive('fire')->times(2);

    $passwordMock = Mockery::mock(FOSSBilling\Core\PasswordManager::class);
    $passwordMock->shouldReceive('hashIt')->atLeast()->once();

    $emailServiceMock = Mockery::mock(Box\Mod\Email\Service::class);
    $emailServiceMock->shouldReceive('sendTemplate')->atLeast()->once();

    $profileServiceMock = Mockery::mock(Box\Mod\Profile\Service::class);
    $profileServiceMock->shouldReceive('invalidateSessions')->atLeast()->once();

    $di = container();
    $di['em'] = $em;
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['password'] = $passwordMock;
    $di['mod_service'] = $di->protect(moduleService(['email' => $emailServiceMock, 'profile' => $profileServiceMock]));

    $guestClient->setDi($di);

    $result = $guestClient->update_password($data);
    expect($result)->toBeTrue();
});

test('updatePassword throws exception when reset not found', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $data = [
        'hash' => 'hashedString',
        'password' => 'NewPassword1',
        'password_confirm' => 'NewPassword1',
    ];

    $eventMock = Mockery::mock(FOSSBilling\Core\Event\Manager::class);
    $eventMock->shouldReceive('fire')->atLeast()->once();

    $di = container();
    $di['events_manager'] = $eventMock;
    $di['logger'] = new Tests\Helpers\TestLogger();

    $guestClient->setDi($di);

    $guestClient->update_password($data);
})->throws(FOSSBilling\Core\Exception\BaseException::class, 'The link has expired or you have already reset your password.');

test('required returns array', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $configArr = [];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

    $guestClient->setDi($di);

    $result = $guestClient->required();
    expect($result)->toBeArray();
});

test('custom_fields returns fields sorted alphabetically by title', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
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

test('custom_fields normalizes incomplete and malformed field configuration', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $configArr = [
        'custom_fields' => [
            'custom_1' => ['title' => 'Optional field'],
            'custom_2' => ['active' => '1', 'required' => '0', 'title' => 'Required flags'],
            'custom_3' => null,
        ],
    ];

    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

    $guestClient->setDi($di);

    $result = $guestClient->custom_fields();
    expect($result['custom_1'])->toBe([
        'title' => 'Optional field',
        'active' => false,
        'required' => false,
    ]);
    expect($result['custom_2'])->toBe([
        'active' => true,
        'required' => false,
        'title' => 'Required flags',
    ]);
    expect($result['custom_3'])->toBe([
        'title' => '',
        'active' => false,
        'required' => false,
    ]);
});

test('custom_fields returns an empty array when custom field configuration is malformed', function (): void {
    $guestClient = apiEndpoint(new Box\Mod\Client\Api\Guest());
    $di = container();
    $di['mod_config'] = $di->protect(fn ($name): array => ['custom_fields' => 'invalid']);

    $guestClient->setDi($di);

    expect($guestClient->custom_fields())->toBe([]);
});
