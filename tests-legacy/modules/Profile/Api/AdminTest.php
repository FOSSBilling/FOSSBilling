<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Profile\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testGet(): void
    {
        $service = new \Box\Mod\Profile\Service();

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->role = 'admin';
        $model->admin_group_id = 1;
        $model->email = 'admin@fossbilling.org';
        $model->name = 'Admin';
        $model->signature = 'Sincerely';
        $model->status = 'active';
        $model->created_at = '2014-01-01';
        $model->updated_at = '2014-01-01';

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($service);
        $result = $adminApi->get();
        $expected = [
            'id' => $model->id,
            'role' => $model->role,
            'admin_group_id' => $model->admin_group_id,
            'email' => $model->email,
            'name' => $model->name,
            'signature' => $model->signature,
            'status' => $model->status,
            'api_token' => null,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testLogout(): void
    {
        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $di = $this->getDi();
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $result = $adminApi->logout();
        $this->assertTrue($result);
    }

    public function testUpdate(): void
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder(\Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('updateAdmin')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->update(['name' => 'Root']);
        $this->assertTrue($result);
    }

    public function testGenerateApiKey(): void
    {
        $model = new \Model_Admin();

        $serviceMock = $this->getMockBuilder(\Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('generateNewApiKey')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->generate_api_key([]);
        $this->assertTrue($result);
    }

    public function testChangePasswordThrowsExceptionWhenPasswordMissing(): void
    {
        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->validateRequiredParams($adminApi, 'change_password', []);
        $adminApi->change_password([]);
    }

    public function testChangePasswordThrowsExceptionWhenConfirmationMissing(): void
    {
        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);

        $this->expectException(\Exception::class);
        $this->validateRequiredParams($adminApi, 'change_password', ['password' => 'new_pass']);
        $adminApi->change_password(['password' => 'new_pass']);
    }

    public function testChangePassword(): void
    {
        $di = $this->getDi();
        $di['validator'] = new \FOSSBilling\Validate();
        $di['password'] = new \FOSSBilling\PasswordManager();
        $di['rate_limiter'] = $this->getAllowedRateLimiter();

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());
        $model->pass = $di['password']->hashIt('oldpw');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Profile\Service::class)
            ->getMock();
        $serviceMock->expects($this->once())
            ->method('changeAdminPassword')
            ->willReturn(true);

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $adminApi->setIdentity($model);
        $adminApi->setService($serviceMock);
        $result = $adminApi->change_password(['current_password' => 'oldpw', 'new_password' => '84asasd221AS', 'confirm_password' => '84asasd221AS']);
        $this->assertTrue($result);
    }

    public function testApiKeyResetChecksClientPermission(): void
    {
        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());

        $profileServiceMock = $this->createMock(\Box\Mod\Profile\Service::class);
        $profileServiceMock->expects($this->once())
            ->method('resetApiKey')
            ->with($client)
            ->willReturn('new-api-key');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_api_keys');

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('Client', 1)
            ->willReturn($client);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            default => throw new \RuntimeException('Unexpected module service request: ' . $name),
        });

        $adminApi = new \Box\Mod\Profile\Api\Admin();
        $adminApi->setDi($di);
        $adminApi->setService($profileServiceMock);

        $result = $adminApi->api_key_reset(['id' => 1]);
        $this->assertSame('new-api-key', $result);
    }

    private function getAllowedRateLimiter(): object
    {
        return new class {
            public function consumeOrThrow(string $policy, string $subject, int $tokens = 1): \FOSSBilling\Security\RateLimitResult
            {
                return new \FOSSBilling\Security\RateLimitResult($policy, false, 10, 9);
            }
        };
    }
}
