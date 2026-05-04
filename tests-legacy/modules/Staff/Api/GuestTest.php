<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Staff\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class GuestTest extends \BBTestCase
{
    protected ?\Box\Mod\Staff\Api\Guest $api;

    public function setUp(): void
    {
        $this->api = new \Box\Mod\Staff\Api\Guest();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testCreate(): void
    {
        $adminId = 1;

        $apiMock = $this->getMockBuilder(\Box\Mod\Staff\Api\Guest::class)
            ->onlyMethods(['login'])
            ->getMock();
        $apiMock->expects($this->atLeastOnce())
            ->method('login');

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createAdmin')
            ->willReturn($adminId);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = $validatorMock;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $apiMock->setDi($di);
        $apiMock->setService($serviceMock);

        $data = [
            'email' => 'example@fossbilling.org',
            'password' => 'EasyToGuess',
        ];
        $result = $apiMock->create($data);
        $this->assertTrue($result);
    }

    public function testCreateException(): void
    {
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn([[]]);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);

        $data = [
            'email' => 'example@fossbilling.org',
            'password' => 'EasyToGuess',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(55);
        $this->expectExceptionMessage('Administrator account already exists');
        $this->api->create($data);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testLoginWithoutEmail(): void
    {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($guestApi, 'login', []);
        $guestApi->login([]);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testLoginWithoutPassword(): void
    {
        $guestApi = new \Box\Mod\Staff\Api\Guest();

        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($guestApi, 'login', ['email' => 'email@domain.com']);
        $guestApi->login(['email' => 'email@domain.com']);
    }

    public function testSuccessfulLogin(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Staff\Service::class)
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('login')
            ->willReturn([]);

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $di = $this->getDi();

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;
        $di['session'] = $sessionMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setService($serviceMock);
        $guestApi->setDi($di);
        $result = $guestApi->login(['email' => 'email@domain.com', 'password' => 'pass']);
        $this->assertIsArray($result);
    }

    public function testLoginCheckIpException(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configArr = [
            'allowed_ips' => '1.1.1.1' . PHP_EOL . '2.2.2.2',
            'check_ip' => true,
        ];
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn($configArr);

        $di = $this->getDi();

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;
        $di['validator'] = new \FOSSBilling\Validate();

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);
        $ip = '192.168.0.1';
        $guestApi->setIp($ip);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage("You are not allowed to login to admin area from this IP address.");

        $data = [
            'email' => 'email@domain.com',
            'password' => 'pass',
        ];
        $guestApi->login($data);
    }

    public function testUpdatePasswordRequiresStrongPassword(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

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

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Minimum password length is 8 characters.');
        $guestApi->update_password([
            'code' => 'hashedString',
            'password' => 'weak',
            'password_confirm' => 'weak',
        ]);
    }

    public function testPasswordResetInactiveStaffIsIgnored(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Admin', 'email = ?', ['email@domain.com'])
            ->willReturn(null);
        $dbMock->expects($this->never())
            ->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')
            ->willReturn('email@domain.com');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();
        $this->registerDisabledAntispamModService($di);

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        $this->assertTrue($guestApi->passwordreset(['email' => 'email@domain.com']));
    }

    public function testUpdatePasswordInactiveStaffIsRejected(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());
        $adminModel->status = \Model_Admin::STATUS_INACTIVE;
        $adminModel->role = \Model_Admin::ROLE_STAFF;

        $passwordResetModel = new \Model_AdminPasswordReset();
        $passwordResetModel->loadBean(new \DummyBean());
        $passwordResetModel->created_at = date('Y-m-d H:i:s', time() - 300);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->willReturn($passwordResetModel);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->willReturn($adminModel);
        $dbMock->expects($this->never())
            ->method('store');
        $dbMock->expects($this->never())
            ->method('trash');

        $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
        $passwordMock->expects($this->never())
            ->method('hashIt');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['password'] = $passwordMock;
        $di['logger'] = new \Box_Log();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $this->expectExceptionMessage('The link has expired or you have already confirmed the password reset.');
        $guestApi->update_password([
            'code' => 'hashedString',
            'password' => 'NewPassword1',
            'password_confirm' => 'NewPassword1',
        ]);
    }

    public function testUpdatePasswordRateLimitedRequestIsRejected(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->never())
            ->method('getConfig');

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

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        try {
            $guestApi->update_password([
                'code' => 'hashedString',
                'password' => 'NewPassword1',
                'password_confirm' => 'NewPassword1',
            ]);
            $this->fail('Expected rate limit exception was not thrown.');
        } catch (\FOSSBilling\InformationException $e) {
            $this->assertSame('Rate limit exceeded. Please try again later.', $e->getMessage());
        }

        $this->assertSame(1, $rateLimiter->consumeCount);
        $this->assertSame('staff_password_reset_confirm_post_ip', $rateLimiter->lastPolicy);
    }

    public function testPasswordResetRateLimitedRequestIsIgnored(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('findOne');
        $dbMock->expects($this->never())
            ->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')
            ->willReturn('email@domain.com');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $rateLimiter = $this->getLimitedRateLimiter();
        $di['rate_limiter'] = $rateLimiter;
        $antispamService = $this->registerActiveAntispamModService($di);

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        $this->assertTrue($guestApi->passwordreset(['email' => 'email@domain.com']));
        $this->assertSame(1, $rateLimiter->consumeCount);
        $this->assertSame(0, $antispamService->checkCaptchaCount);
    }

    public function testPasswordResetEmailRateLimitedRequestSkipsCaptcha(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->never())
            ->method('findOne');
        $dbMock->expects($this->never())
            ->method('dispense');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')
            ->willReturn('email@domain.com');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['db'] = $dbMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['tools'] = $toolsMock;
        $di['logger'] = new \Box_Log();
        $rateLimiter = $this->getEmailLimitedRateLimiter();
        $di['rate_limiter'] = $rateLimiter;
        $antispamService = $this->registerActiveAntispamModService($di);

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setMod($modMock);
        $guestApi->setDi($di);

        $this->assertTrue($guestApi->passwordreset(['email' => 'email@domain.com']));
        $this->assertSame(['staff_password_reset_ip', 'staff_password_reset_email'], $rateLimiter->policies);
        $this->assertSame(0, $antispamService->checkCaptchaCount);
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

    private function registerDisabledAntispamModService(\Pimple\Container $di): void
    {
        $extensionService = new class {
            public function isExtensionActive(string $type, string $id): bool
            {
                return false;
            }
        };

        $di['mod_service'] = $di->protect(fn (string $name): object => $extensionService);
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
