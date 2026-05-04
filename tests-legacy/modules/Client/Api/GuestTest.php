<?php

declare(strict_types=1);

namespace Box\Mod\Client\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    public function testCreate(): void
    {
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
        $model->loadBean(new \DummyBean());
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

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
        $di['validator'] = $validatorMock;
        $di['tools'] = $toolsMock;
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $result = $client->create($data);

        $this->assertIsInt($result);
        $this->assertEquals($model->id, $result);
    }

    public function testCreateExceptionClientExists(): void
    {
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
        $model->loadBean(new \DummyBean());

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
        $di['validator'] = $validatorMock;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail')->willReturn($data['email']);
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $client->create($data);
    }

    public function testCreateSignupDoNotAllowed(): void
    {
        $configArr = [
            'disable_signup' => true,
        ];
        $data = [
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'testpaswword',
        ];

        $client = new Guest();
        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('New registrations are temporary disabled');
        $client->create($data);
    }

    public function testCreatePasswordsDoNotMatchException(): void
    {
        $configArr = [
            'disable_signup' => false,
        ];
        $data = [
            'email' => 'test@email.com',
            'first_name' => 'John',
            'password' => 'testpaswword',
            'password_confirm' => 'wrongpaswword',
        ];

        $client = new Guest();
        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => $configArr);
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Passwords do not match.');
        $client->create($data);
    }

    public function testLogin(): void
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'sezam',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

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

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        // $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolsMock;
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $cartServiceMock);

        $client = new Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $results = $client->login($data);

        $this->assertIsArray($results);
    }

    public function testResetPasswordNewFlow(): void
    {
        $data['email'] = 'John@exmaple.com';

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->status = \Model_Client::ACTIVE;

        $modelPasswordReset = new \Model_ClientPasswordReset();
        $modelPasswordReset->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');

        // Specify that 'findOne' will be called exactly twice
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

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $this->registerPasswordResetModService($di, $emailServiceMock);
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolsMock;
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
    }

    public function testResetPasswordEmailNotFound(): void
    {
        $data['email'] = 'joghn@example.eu';

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail')->willReturn($data['email']);
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $this->registerPasswordResetModService($di);

        $client = new Guest();
        $client->setDi($di);

        // expects true because we don't want to give away if the email exists or not
        $result = $client->reset_password($data);
        $this->assertTrue($result);
    }

    public function testUpdatePassword(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'NewPassword1',
            'password_confirm' => 'NewPassword1',
        ];

        // Mocks for dependent services and classes
        $dbMock = $this->createMock('\Box_Database');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->status = \Model_Client::ACTIVE;

        $modelPasswordReset = new \Model_ClientPasswordReset();
        $modelPasswordReset->loadBean(new \DummyBean());
        $modelPasswordReset->created_at = date('Y-m-d H:i:s', time() - 300);  // Set timestamp to 5 minutes ago

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

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['password'] = $passwordMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $emailServiceMock);
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);

        $result = $client->update_password($data);
        $this->assertTrue($result);
    }

    public function testResetPasswordInactiveClientIsIgnored(): void
    {
        $data['email'] = 'john@example.com';

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->id = 1;
        $modelClient->status = \Model_Client::SUSPENDED;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Client', 'email = ?', [$data['email']])
            ->willReturn($modelClient);
        $dbMock->expects($this->never())->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')->willReturn($data['email']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $this->registerPasswordResetModService($di);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
    }

    public function testResetPasswordRateLimitedRequestIsIgnored(): void
    {
        $data['email'] = 'john@example.com';

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())->method('findOne');
        $dbMock->expects($this->never())->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')->willReturn($data['email']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $rateLimiter = $this->getLimitedRateLimiter();
        $di['rate_limiter'] = $rateLimiter;
        $antispamService = $this->registerActiveAntispamModService($di);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
        $this->assertSame(1, $rateLimiter->consumeCount);
        $this->assertSame(0, $antispamService->checkCaptchaCount);
    }

    public function testResetPasswordEmailRateLimitedRequestSkipsCaptcha(): void
    {
        $data['email'] = 'john@example.com';

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())->method('findOne');
        $dbMock->expects($this->never())->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')->willReturn($data['email']);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $rateLimiter = $this->getEmailLimitedRateLimiter();
        $di['rate_limiter'] = $rateLimiter;
        $antispamService = $this->registerActiveAntispamModService($di);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->reset_password($data);
        $this->assertTrue($result);
        $this->assertSame(['client_password_reset_ip', 'client_password_reset_email'], $rateLimiter->policies);
        $this->assertSame(0, $antispamService->checkCaptchaCount);
    }

    public function testUpdatePasswordInactiveClientIsRejected(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'NewPassword1',
            'password_confirm' => 'NewPassword1',
        ];

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());
        $modelClient->status = \Model_Client::SUSPENDED;

        $modelPasswordReset = new \Model_ClientPasswordReset();
        $modelPasswordReset->loadBean(new \DummyBean());
        $modelPasswordReset->created_at = date('Y-m-d H:i:s', time() - 300);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')->willReturn($modelPasswordReset);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')->willReturn($modelClient);
        $dbMock->expects($this->never())->method('store');
        $dbMock->expects($this->never())->method('trash');

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
        $passwordMock->expects($this->never())
            ->method('hashIt');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['password'] = $passwordMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('The link has expired or you have already reset your password.');
        $client->update_password($data);
    }

    public function testUpdatePasswordResetNotFound(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'NewPassword1',
            'password_confirm' => 'NewPassword1',
        ];

        // Mock for the database service
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')->willReturn(null);

        // Mock for the events manager
        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        // Dependency injection container setup
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);

        // Expect a FOSSBilling\Exception to be thrown with a specific message
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('The link has expired or you have already reset your password.');
        $client->update_password($data);
    }

    public function testUpdatePasswordRequiresStrongPassword(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'weak',
            'password_confirm' => 'weak',
        ];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('findOne');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $client = new Guest();
        $client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Minimum password length is 8 characters.');
        $client->update_password($data);
    }

    public function testUpdatePasswordRateLimitedRequestIsRejected(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'NewPassword1',
            'password_confirm' => 'NewPassword1',
        ];

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->never())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('findOne');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $rateLimiter = $this->getLimitedRateLimiter();
        $di['rate_limiter'] = $rateLimiter;

        $client = new Guest();
        $client->setDi($di);

        try {
            $client->update_password($data);
            $this->fail('Expected rate limit exception was not thrown.');
        } catch (\FOSSBilling\InformationException $e) {
            $this->assertSame('Rate limit exceeded. Please try again later.', $e->getMessage());
        }

        $this->assertSame(1, $rateLimiter->consumeCount);
        $this->assertSame('client_password_reset_confirm_post_ip', $rateLimiter->lastPolicy);
    }

    public function testRequired(): void
    {
        $configArr = [];

        $di = $this->getDi();
        $di['mod_config'] = $di->protect(fn ($name): array => $configArr);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->required();
        $this->assertIsArray($result);
    }

    private function getAllowedRateLimiter(): object
    {
        return new class {
            public function consume(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                return new \FOSSBilling\Security\RateLimitResult($policy, false, 10, 9);
            }

            public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                return $this->consume($policy, $subject, $tokens);
            }
        };
    }

    private function registerPasswordResetModService(\Pimple\Container $di, ?object $emailService = null): void
    {
        $extensionService = new class {
            public function isExtensionActive(string $type, string $id): bool
            {
                return false;
            }
        };

        $di['mod_service'] = $di->protect(
            fn (string $name): object => strtolower($name) === 'extension' ? $extensionService : ($emailService ?? new \stdClass())
        );
    }

    private function registerActiveAntispamModService(\Pimple\Container $di): object
    {
        $antispamService = new class {
            public int $checkCaptchaCount = 0;

            public function checkCaptcha(array $data): void
            {
                ++$this->checkCaptchaCount;
            }
        };
        $extensionService = new class {
            public function isExtensionActive(string $type, string $id): bool
            {
                return true;
            }
        };

        $di['mod_service'] = $di->protect(fn (string $name): object => strtolower($name) === 'antispam' ? $antispamService : $extensionService);

        return $antispamService;
    }

    private function getEmailLimitedRateLimiter(): object
    {
        return new class {
            public array $policies = [];

            public function consume(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                $this->policies[] = $policy;
                $limited = count($this->policies) === 2;

                return new \FOSSBilling\Security\RateLimitResult($policy, $limited, 10, $limited ? 0 : 9);
            }
        };
    }

    private function getLimitedRateLimiter(): object
    {
        return new class {
            public int $consumeCount = 0;
            public ?string $lastPolicy = null;

            public function consume(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                ++$this->consumeCount;
                $this->lastPolicy = $policy;

                return new \FOSSBilling\Security\RateLimitResult($policy, true, 10, 0);
            }

            public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                $result = $this->consume($policy, $subject, $tokens);
                if ($result->isLimited()) {
                    throw new \FOSSBilling\InformationException('Rate limit exceeded. Please try again later.', null, 429);
                }

                return $result;
            }
        };
    }
}
