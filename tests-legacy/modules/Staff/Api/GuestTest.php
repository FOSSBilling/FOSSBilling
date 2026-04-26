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
        $this->expectExceptionMessage("You are not allowed to login to admin area from {$ip} address.");

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

    public function testPasswordResetUsesActiveStatusFilter(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $admin = new \Model_Admin();
        $admin->loadBean(new \DummyBean());
        $admin->id = 7;
        $admin->email = 'staff@example.com';

        $reset = new \Model_AdminPasswordReset();
        $reset->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('Admin', 'email = ? AND status = ?', ['staff@example.com', \Model_Admin::STATUS_ACTIVE])
            ->willReturn($admin);
        $dbMock->expects($this->once())
            ->method('dispense')
            ->with('AdminPasswordReset')
            ->willReturn($reset);
        $dbMock->expects($this->once())
            ->method('store')
            ->with($reset);

        $emailServiceMock = $this->createMock(\Box\Mod\Email\Service::class);
        $emailServiceMock->expects($this->once())
            ->method('sendTemplate');

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')
            ->with('staff@example.com')
            ->willReturn('staff@example.com');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $emailServiceMock);
        $di['logger'] = $this->createMock('\Box_Log');

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setDi($di);
        $guestApi->setMod($modMock);
        $guestApi->passwordreset(['email' => 'staff@example.com']);
    }

    public function testUpdatePasswordRejectsInactiveAdmin(): void
    {
        $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)
            ->disableOriginalConstructor()
            ->getMock();
        $modMock->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $reset = new \Model_AdminPasswordReset();
        $reset->loadBean(new \DummyBean());
        $reset->created_at = date('Y-m-d H:i:s', time() - 300);

        $inactiveAdmin = new \Model_Admin();
        $inactiveAdmin->loadBean(new \DummyBean());
        $inactiveAdmin->status = \Model_Admin::STATUS_INACTIVE;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->once())
            ->method('findOne')
            ->with('AdminPasswordReset', 'hash = ?', ['hashedString'])
            ->willReturn($reset);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('Admin', $reset->admin_id, 'User not found')
            ->willReturn($inactiveAdmin);
        $dbMock->expects($this->never())
            ->method('store');
        $dbMock->expects($this->never())
            ->method('trash');

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->once())
            ->method('fire');

        $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
        $passwordMock->expects($this->never())
            ->method('hashIt');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['validator'] = new \FOSSBilling\Validate();
        $di['password'] = $passwordMock;

        $guestApi = new \Box\Mod\Staff\Api\Guest();
        $guestApi->setDi($di);
        $guestApi->setMod($modMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('The link has expired or you have already confirmed the password reset.');
        $guestApi->update_password([
            'code' => 'hashedString',
            'password' => 'StrongPass123',
            'password_confirm' => 'StrongPass123',
        ]);
    }
}
