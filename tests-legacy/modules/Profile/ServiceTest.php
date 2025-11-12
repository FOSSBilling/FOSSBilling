<?php

namespace Box\Tests\Mod\Profile;

use Box\Mod\Profile\Service;

class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new Service();
        $di = new \Pimple\Container();
        $service->setDi($di);
        $getDi = $service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetAdminIdentityArray(): void
    {
        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $result = $service->getAdminIdentityArray($model);
        $this->assertIsArray($result);
    }

    public function testUpdateAdmin(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        $data = [
            'signature' => 'new signature',
            'email' => 'example@gmail.com',
            'name' => 'Admin',
        ];

        $service = new Service();
        $service->setDi($di);
        $result = $service->updateAdmin($model, $data);
        $this->assertTrue($result);
    }

    public function testGenerateNewApiKey(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['tools'] = new \FOSSBilling\Tools();

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $service->setDi($di);

        $result = $service->generateNewApiKey($model);
        $this->assertTrue($result);
    }

    public function testChangeAdminPassword(): void
    {
        $password = 'new_pass';
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($password);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['password'] = $passwordMock;

        $model = new \Model_Admin();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $service->setDi($di);

        $result = $service->changeAdminPassword($model, $password);
        $this->assertTrue($result);
    }

    public function testUpdateClient(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'disable_change_email' => 0,
            ]);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(false);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['tools'] = $toolsMock;

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $data = [
            'email' => 'email@example.com',
            'first_name' => 'string',
            'last_name' => 'string',
            'gender' => 'string',
            'birthday' => '1981-01-01',
            'company' => 'string',
            'company_vat' => 'string',
            'company_number' => 'string',
            'type' => 'string',
            'address_1' => 'string',
            'address_2' => 'string',
            'phone_cc' => random_int(10, 300),
            'phone' => random_int(10000, 90000),
            'country' => 'string',
            'postcode' => 'string',
            'city' => 'string',
            'state' => 'string',
            'document_type' => 'string',
            'document_nr' => random_int(100000, 900000),
            'lang' => 'string',
            'notes' => 'string',
            'custom_1' => 'string',
            'custom_2' => 'string',
            'custom_3' => 'string',
            'custom_4' => 'string',
            'custom_5' => 'string',
            'custom_6' => 'string',
            'custom_7' => 'string',
            'custom_8' => 'string',
            'custom_9' => 'string',
            'custom_10' => 'string',
        ];

        $service = new Service();
        $service->setDi($di);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testUpdateClientEmailChangeNotAllowedException(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(true);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'disable_change_email' => 1,
            ]);

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientServiceMock->expects($this->never())->
        method('emailAlreadyRegistered')->willReturn(false);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $data = [
            'email' => 'email@example.com',
        ];

        $service = new Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testUpdateClientEmailAlreadyRegisteredException(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->never())
            ->method('store')
            ->willReturn(true);

        $modMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $modMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([
                'disable_change_email' => 0,
            ]);

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $clientServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $clientServiceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(true);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $clientServiceMock);
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $modMock);
        $di['tools'] = $toolsMock;

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $data = [
            'email' => 'email@example.com',
        ];

        $service = new Service();
        $service->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $service->updateClient($model, $data);
        $this->assertTrue($result);
    }

    public function testResetApiKey(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;
        $di['tools'] = new \FOSSBilling\Tools();
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->resetApiKey($model);
        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 32);
    }

    public function testChangeClientPassword(): void
    {
        $emMock = $this->getMockBuilder('\Box_EventManager')
            ->getMock();
        $emMock->expects($this->atLeastOnce())
            ->method('fire')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')
            ->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->willReturn(true);

        $password = 'new password';

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($password);

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['events_manager'] = $emMock;
        $di['db'] = $dbMock;
        $di['password'] = $passwordMock;

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->changeClientPassword($model, $password);
        $this->assertTrue($result);
    }

    public function testLogoutClient(): void
    {
        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $sessionMock->expects($this->atLeastOnce())
            ->method('destroy');

        $di = new \Pimple\Container();
        $di['logger'] = new \Box_Log();
        $di['session'] = $sessionMock;

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $service = new Service();
        $service->setDi($di);
        $result = $service->logoutClient();
        $this->assertTrue($result);
    }
}
