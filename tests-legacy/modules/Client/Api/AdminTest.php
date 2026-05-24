<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Client\Api;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Response;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'view');

        $di = $this->getDi();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setService($serviceMock);
        $adminClient->setDi($di);
        $data = [];

        $result = $adminClient->get_list($data);
        $this->assertIsArray($result);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('getPairs')->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'view');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'client' => $serviceMock,
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $data = ['id' => 1];
        $result = $adminClient->get_pairs($data);
        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('get')->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->with($model, true, null, true)
            ->willReturn([]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'view');
        $staffServiceMock->expects($this->once())
            ->method('hasPermission')
            ->with(null, 'client', 'manage_api_keys')
            ->willReturn(true);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setService($serviceMock);
        $adminClient->setDi($di);

        $result = $adminClient->get([]);
        $this->assertIsArray($result);
    }

    public function testLogin(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $sessionArray = [
            'id' => 1,
            'email' => 'email@example.com',
            'name' => 'John Smith',
            'role' => 'client',
        ];
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('toSessionArray')->willReturn($sessionArray);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'impersonate_login');

        $sessionMock = $this->getMockBuilder(\FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())->method('set');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'client' => $serviceMock,
            'staff' => $staffServiceMock,
        });
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $data = ['id' => 1];
        $result = $adminClient->login($data);
        $this->assertIsArray($result);
    }

    public function testCreate(): void
    {
        $data = [
            'email' => 'email@example.com',
            'first_name' => 'John', 'password' => 'StrongPass123',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())->method('adminCreateClient')->willReturn(1);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'create');

        $eventMock = $this->createMock('\\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);

        $result = $adminClient->create($data);

        $this->assertIsInt($result, 'create() returned: ' . $result);
    }

    public function testCreateRequiresPasswordWhenWelcomeEmailDisabled(): void
    {
        $data = [
            'email' => 'email@example.com',
            'first_name' => 'John',
            'send_welcome_email' => false,
        ];

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->once())->method('emailAlreadyRegistered')->willReturn(false);
        $serviceMock->expects($this->never())->method('adminCreateClient');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'create');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->once())->method('validateAndSanitizeEmail');

        $di = $this->getDi();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('A password is required when the welcome email is disabled.');
        $adminClient->create($data);
    }

    public function testCreateEmailRegisteredException(): void
    {
        $data = [
            'email' => 'email@example.com',
            'first_name' => 'John', 'password' => 'StrongPass123',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'create');

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = $this->getDi();
        $di['tools'] = $toolsMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $adminClient->create($data);
    }

    public function testDelete(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $eventMock = $this->createMock('\\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $serviceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->onlyMethods(['remove'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('remove');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'delete');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);
        $result = $adminClient->delete($data);
        $this->assertTrue($result);
    }

    public function testUpdate(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John', 'password' => 'StrongPass123',
            'last_name' => 'Smith',
            'aid' => '0',
            'gender' => 'male',
            'birthday' => '1999-01-01',
            'company' => 'LTD Testing',
            'company_vat' => 'VAT0007',
            'address_1' => 'United States',
            'address_2' => 'Utah',
            'phone_cc' => '+1',
            'phone' => '555-345-345',
            'document_type' => 'doc',
            'document_nr' => '1',
            'notes' => 'none',
            'country' => 'US',
            'postcode' => 'IL-11123',
            'city' => 'Chicago',
            'state' => 'IL',
            'currency' => 'USD',
            'tax_exempt' => 'N/A',
            'created_at' => '2012-05-10',
            'email' => 'test@example.com',
            'group_id' => 1,
            'status' => 'test status',
            'company_number' => '1234',
            'type' => '',
            'lang' => 'en',
            'custom_1' => '',
            'custom_2' => '',
            'custom_3' => '',
            'custom_4' => '',
            'custom_5' => '',
            'custom_6' => '',
            'custom_7' => '',
            'custom_8' => '',
            'custom_9' => '',
            'custom_10' => '',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(false);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'edit_profile');

        $eventMock = $this->createMock('Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn (string $name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower($name)) {
            'staff' => $staffServiceMock,
            'client' => $serviceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);
        $result = $adminClient->update($data);
        $this->assertTrue($result);
    }

    public function testUpdateEmailAlreadyRegistered(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John', 'password' => 'StrongPass123',
            'last_name' => 'Smith',
            'aid' => '0',
            'gender' => 'male',
            'birthday' => '1999-01-01',
            'company' => 'LTD Testing',
            'company_vat' => 'VAT0007',
            'address_1' => 'United States',
            'address_2' => 'Utah',
            'phone_cc' => '+1',
            'phone' => '555-345-345',
            'document_type' => 'doc',
            'document_nr' => '1',
            'notes' => 'none',
            'country' => 'US',
            'postcode' => 'IL-11123',
            'city' => 'Chicago',
            'state' => 'IL',
            'currency' => 'USD',
            'tax_exempt' => 'N/A',
            'created_at' => '2012-05-10',
            'email' => 'test@example.com',
            'group_id' => 1,
            'status' => 'test status',
            'company_number' => '1234',
            'type' => '',
            'lang' => 'en',
            'custom_1' => '',
            'custom_2' => '',
            'custom_3' => '',
            'custom_4' => '',
            'custom_5' => '',
            'custom_6' => '',
            'custom_7' => '',
            'custom_8' => '',
            'custom_9' => '',
            'custom_10' => '',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('emailAlreadyRegistered')->willReturn(true);
        $serviceMock->expects($this->never())->method('canChangeCurrency')->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'edit_profile');

        $eventMock = $this->createMock('\\Box_EventManager');
        $eventMock->expects($this->never())->method('fire');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'client' => $serviceMock,
            'staff' => $staffServiceMock,
        });
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['validator'] = new \FOSSBilling\Validate();

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $adminClient->update($data);
    }

    public function testUpdateIdException(): void
    {
        $data = [];
        $adminClient = new \Box\Mod\Client\Api\Admin();

        $di = $this->getDi();

        $di['validator'] = new \FOSSBilling\Validate();
        $adminClient->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Client ID was not passed');
        $this->validateRequiredParams($adminClient, 'update', $data);
        $adminClient->update($data);
    }

    public function testChangePassword(): void
    {
        $data = [
            'id' => 1,
            'password' => 'strongPass',
            'password_confirm' => 'strongPass',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $eventMock = $this->createMock('\\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->method('fire');

        $passwordMock = $this->createMock(\FOSSBilling\PasswordManager::class);
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $profileService = $this->createMock(\Box\Mod\Profile\Service::class);
        $profileService->expects($this->once())
            ->method('invalidateSessions')
            ->with('client', $data['id']);
        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'change_password');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['password'] = $passwordMock;
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'profile' => $profileService,
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $result = $adminClient->change_password($data);
        $this->assertTrue($result);
    }

    public function testChangePasswordPasswordMismatch(): void
    {
        $data = [
            'id' => 1,
            'password' => 'strongPass',
            'password_confirm' => 'NotIdentical',
        ];
        $adminClient = new \Box\Mod\Client\Api\Admin();

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'change_password');

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->once())
            ->method('passwordsMatch')
            ->with($data)
            ->willThrowException(new \FOSSBilling\Exception('Passwords do not match'));

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $adminClient->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Passwords do not match');
        $adminClient->change_password($data);
    }

    public function testBalanceGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                [
                    'id' => 1,
                    'description' => 'Testing',
                    'amount' => '1.00',
                    'currency' => 'USD',
                    'created_at' => date('Y:m:d H:i:s'),
                ],
            ],
        ];

        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Client\ServiceBalance::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_balance');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
            default => $serviceMock,
        });
        $di['pager'] = $pagerMock;

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $result = $adminClient->balance_get_list($data);
        $this->assertIsArray($result);
    }

    public function testBalanceGetListRequiresPermission(): void
    {
        $data = [];

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_balance')
            ->willThrowException(new \FOSSBilling\InformationException('denied'));

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(function ($serviceName) use ($staffServiceMock) {
            if ($serviceName == 'Staff') {
                return $staffServiceMock;
            }

            return false;
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $this->expectException(\FOSSBilling\InformationException::class);
        $adminClient->balance_get_list($data);
    }

    public function testBalanceDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_balance');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $result = $adminClient->balance_delete($data);
        $this->assertTrue($result);
    }

    public function testBalanceAddFunds(): void
    {
        $data = [
            'id' => 1,
            'amount' => '1.00',
            'description' => 'testDescription',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('addFunds');

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_balance');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'client' => $serviceMock,
            'staff' => $staffServiceMock,
        });

        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $result = $adminClient->balance_add_funds($data);
        $this->assertTrue($result);
    }

    public function testExportCsvChecksPermission(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->once())
            ->method('exportCSV')
            ->with([])
            ->willReturn(new Response('csv-data'));

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'export');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->export_csv([]);
        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('csv-data', $result->getContent());
    }

    public function testBatchExpirePasswordReminders(): void
    {
        $expiredArr = [
            new \Model_ClientPasswordReset(),
        ];

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('getExpiredPasswordReminders')->willReturn($expiredArr);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'delete');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'client' => $serviceMock,
            'staff' => $staffServiceMock,
        });
        $di['logger'] = new \Box_Log();

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);

        $result = $adminClient->batch_expire_password_reminders();
        $this->assertTrue($result);
    }

    public function testLoginHistoryGetList(): void
    {
        $data = [];
        $pagerResultSet = [
            'list' => [],
        ];

        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHistorySearchQuery')
            ->willReturn(['String', []]);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'view_login_history');

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($pagerResultSet);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $adminClient = new \Box\Mod\Client\Api\Admin();
        $adminClient->setDi($di);
        $adminClient->setService($serviceMock);

        $result = $adminClient->login_history_get_list($data);
        $this->assertIsArray($result);
    }

    public function testGetStatuses(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('counter')->willReturn([]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->get_statuses([]);
        $this->assertIsArray($result);
    }

    public function testGroupGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('getGroupPairs')->willReturn([]);

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testGroupCreate(): void
    {
        $data['title'] = 'test Group';

        $newGroupId = 1;
        $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('createGroup')->willReturn($newGroupId);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_groups');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);
        $admin_Client->setDi($di);
        $result = $admin_Client->group_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testGroupUpdate(): void
    {
        $data['id'] = '2';
        $data['title'] = 'test Group updated';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_groups');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });

        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_update($data);

        $this->assertTrue($result);
    }

    public function testGroupDelete(): void
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);
        $dbMock->expects($this->once())
            ->method('find')->with('Client', 'client_group_id = :group_id', [':group_id' => $data['id']])
            ->willReturn([]); // Return an empty array to simulate no clients assigned to the group

        $serviceMock = $this->getMockBuilder(\Box\Mod\Client\Service::class)
            ->onlyMethods(['deleteGroup'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'manage_groups');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->group_delete($data);

        $this->assertTrue($result);
    }

    public function testGroupGet(): void
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get($data);

        $this->assertIsArray($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder(\Box\Mod\Client\Api\Admin::class)->onlyMethods(['delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('delete')->willReturn(true);

        $staffServiceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $staffServiceMock->expects($this->once())
            ->method('checkPermissionsAndThrowException')
            ->with('client', 'bulk_delete');

        $di = $this->getDi();
        $di['validator'] = $this->createStub(\FOSSBilling\Validate::class);
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => match (strtolower((string) $name)) {
            'staff' => $staffServiceMock,
        });
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}
