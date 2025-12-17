<?php

declare(strict_types=1);

namespace Box\Mod\Client\Api;

#[PHPUnit\Framework\Attributes\Group('Core')]
final class GuestTest extends \BBTestCase
{
    public function testGetDi(): void
    {
        $di = new \Pimple\Container();
        $client = new Guest();
        $client->setDi($di);
        $getDi = $client->getDi();
        $this->assertEquals($di, $getDi);
    }

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

        $serviceMock = $this->createMock("Box\Mod\Client\Service::class");
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

        $validatorMock = $this->createMock("FOSSBilling\Validate::class");
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

        $toolsMock = $this->createMock("FOSSBilling\Tools::class");
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name) => $configArr);
        $di['validator'] = $validatorMock;
        $di['tools'] = $toolsMock;

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

        $serviceMock = $this->createMock("Box\Mod\Client\Service::class");
        $serviceMock->expects($this->atLeastOnce())
            ->method('clientAlreadyExists')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkExtraRequiredFields');
        $serviceMock->expects($this->atLeastOnce())
            ->method('checkCustomFields');

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $validatorMock = $this->createMock("FOSSBilling\Validate::class");
        $validatorMock->expects($this->atLeastOnce())->method('isPasswordStrong');

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name) => $configArr);
        $di['validator'] = $validatorMock;

        $toolsMock = $this->createMock("FOSSBilling\Tools::class");
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $client = new Guest();
        $client->setDi($di);
        $client->setService($serviceMock);

        $this->expectException("FOSSBilling\Exception::class");
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
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name) => $configArr);
        $client->setDi($di);

        $this->expectException("FOSSBilling\Exception::class");
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
        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name) => $configArr);
        $client->setDi($di);

        $this->expectException("FOSSBilling\Exception::class");
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

        $serviceMock = $this->createMock("Box\Mod\Client\Service::class");
        $serviceMock->expects($this->atLeastOnce())
            ->method('authorizeClient')
            ->with($data['email'], $data['password'])
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toSessionArray')
            ->willReturn([]);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $sessionMock = $this->getMockBuilder('\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $sessionMock->expects($this->atLeastOnce())
            ->method('set');

        $cartServiceMock = $this->createMock("Box\Mod\Cart\Service::class");
        $cartServiceMock->expects($this->once())
            ->method('transferFromOtherSession')
            ->willReturn(true);

        $toolsMock = $this->createMock("FOSSBilling\Tools::class");
        // $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['events_manager'] = $eventMock;
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn () => $cartServiceMock);

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

        $emailServiceMock = $this->createMock("Box\Mod\Email\Service::class");
        $emailServiceMock->expects($this->atLeastOnce())->method('sendTemplate');

        $toolsMock = $this->createMock("FOSSBilling\Tools::class");
        $toolsMock->expects($this->once())
            ->method('validateAndSanitizeEmail')->willReturn($data['email']);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['mod_service'] = $di->protect(fn ($name) => $emailServiceMock);
        $di['logger'] = new \Box_Log();
        $di['tools'] = $toolsMock;

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

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;

        $toolsMock = $this->createMock("FOSSBilling\Tools::class");
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

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
            'password' => 'newPassword',
            'password_confirm' => 'newPassword',
        ];

        // Mocks for dependent services and classes
        $dbMock = $this->createMock('\Box_Database');

        $modelClient = new \Model_Client();
        $modelClient->loadBean(new \DummyBean());

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

        $passwordMock = $this->createMock("FOSSBilling\PasswordManager::class");
        $passwordMock->expects($this->once())
            ->method('hashIt');

        $emailServiceMock = $this->createMock("Box\Mod\Email\Service::class");
        $emailServiceMock->expects($this->once())
            ->method('sendTemplate');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['password'] = $passwordMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn ($name) => $emailServiceMock);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->update_password($data);
        $this->assertTrue($result);
    }

    public function testUpdatePasswordResetNotFound(): void
    {
        $data = [
            'hash' => 'hashedString',
            'password' => 'newPassword',
            'password_confirm' => 'newPassword',
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
        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;

        $client = new Guest();
        $client->setDi($di);

        // Expect a FOSSBilling\Exception to be thrown with a specific message
        $this->expectException("FOSSBilling\Exception::class");
        $this->expectExceptionMessage('The link has expired or you have already reset your password.');
        $client->update_password($data);
    }

    public function testRequired(): void
    {
        $configArr = [];

        $di = new \Pimple\Container();
        $di['mod_config'] = $di->protect(fn ($name) => $configArr);

        $client = new Guest();
        $client->setDi($di);

        $result = $client->required();
        $this->assertIsArray($result);
    }
}