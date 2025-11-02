<?php

namespace Box\Tests\Mod\Client\Api;

class AdminTest extends \BBTestCase
{
    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $getDi = $admin_Client->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList(): void
    {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);
        $admin_Client->setDi($di);
        $data = [];

        $result = $admin_Client->get_list($data);
        $this->assertIsArray($result);
    }

    public function testGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getPairs')->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $data = ['id' => 1];
        $result = $admin_Client->get_pairs($data);
        $this->assertIsArray($result);
    }

    public function testget(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('get')->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->get([]);
        $this->assertIsArray($result);
    }

    public function testlogin(): void
    {
        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $sessionArray = [
            'id' => 1,
            'email' => 'email@example.com',
            'name' => 'John Smith',
            'role' => 'client',
        ];
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('toSessionArray')->willReturn($sessionArray);

        $sessionMock = $this->getMockBuilder('\\' . \FOSSBilling\Session::class)->disableOriginalConstructor()->getMock();
        $sessionMock->expects($this->atLeastOnce())->
        method('set');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['session'] = $sessionMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $data = ['id' => 1];
        $result = $admin_Client->login($data);
        $this->assertIsArray($result);
    }

    public function testCreate(): void
    {
        $data = [
            'email' => 'email@example.com',
            'first_name' => 'John',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())->
        method('adminCreateClient')->willReturn(1);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())->method('checkRequiredParamsForArray');

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['events_manager'] = $eventMock;
        $di['tools'] = $toolsMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->create($data);

        $this->assertIsInt($result, 'create() returned: ' . $result);
    }

    public function testCreateEmailRegisteredException(): void
    {
        $data = [
            'email' => 'email@example.com',
            'first_name' => 'John',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['tools'] = $toolsMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $admin_Client->create($data);
    }

    public function testdelete(): void
    {
        $data = ['id' => 1];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(['remove'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('remove');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);
        $result = $admin_Client->delete($data);
        $this->assertTrue($result);
    }

    public function testupdate(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John',
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
            'country' => 'Moon',
            'postcode' => 'IL-11123',
            'city' => 'Chicaco',
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);
        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(false);
        $serviceMock->expects($this->atLeastOnce())->
        method('canChangeCurrency')->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['events_manager'] = $eventMock;
        $di['validator'] = $validatorMock;
        $di['logger'] = new \Box_Log();

        $di['tools'] = $toolsMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $result = $admin_Client->update($data);
        $this->assertTrue($result);
    }

    public function testupdateEmailALreadyRegistered(): void
    {
        $data = [
            'id' => 1,
            'first_name' => 'John',
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
            'country' => 'Moon',
            'postcode' => 'IL-11123',
            'city' => 'Chicaco',
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

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('emailAlreadyRegistered')->willReturn(true);
        $serviceMock->expects($this->never())->
        method('canChangeCurrency')->willReturn(true);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->never())->
        method('fire');

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['events_manager'] = $eventMock;
        $di['validator'] = $validatorMock;
        $di['logger'] = new \Box_Log();

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('This email address is already registered.');
        $admin_Client->update($data);
    }

    public function testUpdateIdException(): void
    {
        $data = [];
        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $di = new \Pimple\Container();

        $di['validator'] = new \FOSSBilling\Validate();
        $admin_Client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Id required');
        $admin_Client->update($data);
    }

    public function testchangePassword(): void
    {
        $data = [
            'id' => 1,
            'password' => 'strongPass',
            'password_confirm' => 'strongPass',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $eventMock = $this->getMockBuilder('\Box_EventManager')->getMock();
        $eventMock->expects($this->atLeastOnce())->
        method('fire');

        $passwordMock = $this->getMockBuilder(\FOSSBilling\PasswordManager::class)->getMock();
        $passwordMock->expects($this->atLeastOnce())
            ->method('hashIt')
            ->with($data['password']);

        $profileService = $this->getMockBuilder('\\' . \Box\Mod\Profile\Service::class)->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['password'] = $passwordMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $profileService);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->change_password($data);
        $this->assertTrue($result);
    }

    public function testchangePasswordPasswordMismatch(): void
    {
        $data = [
            'id' => 1,
            'password' => 'strongPass',
            'password_confirm' => 'NotIdentical',
        ];
        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $admin_Client->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Passwords do not match');
        $admin_Client->change_password($data);
    }

    public function testbalanceGetList(): void
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

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\ServiceBalance::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['pager'] = $pagerMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_get_list($data);
        $this->assertIsArray($result);
    }

    public function testbalanceDelete(): void
    {
        $data = [
            'id' => 1,
        ];

        $model = new \Model_ClientBalance();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_delete($data);
        $this->assertTrue($result);
    }

    public function testbalanceAddFunds(): void
    {
        $data = [
            'id' => 1,
            'amount' => '1.00',
            'description' => 'testDescription',
        ];

        $model = new \Model_Client();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('addFunds');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->balance_add_funds($data);
        $this->assertTrue($result);
    }

    public function testbatchExpirePasswordReminders(): void
    {
        $expiredArr = [
            new \Model_ClientPasswordReset(),
        ];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getExpiredPasswordReminders')->willReturn($expiredArr);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
        $di['logger'] = new \Box_Log();

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->batch_expire_password_reminders();
        $this->assertTrue($result);
    }

    public function testloginHistoryGetList(): void
    {
        $data = [];
        $pagerResultSet = [
            'list' => [],
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getHistorySearchQuery')
            ->willReturn(['String', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($pagerResultSet);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->login_history_get_list($data);
        $this->assertIsArray($result);
    }

    public function testgetStatuses(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('counter')->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->get_statuses([]);
        $this->assertIsArray($result);
    }

    public function testgroupGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('getGroupPairs')->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgroupCreate(): void
    {
        $data['title'] = 'test Group';

        $newGroupId = 1;
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())->
        method('createGroup')
            ->willReturn($newGroupId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setService($serviceMock);
        $admin_Client->setDi($di);
        $result = $admin_Client->group_create($data);

        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testgroupUpdate(): void
    {
        $data['id'] = '2';
        $data['title'] = 'test Group updated';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('store')->willReturn(1);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_update($data);

        $this->assertTrue($result);
    }

    public function testgroupDelete(): void
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);
        $dbMock->expects($this->once())
            ->method('find')->with('Client', 'client_group_id = :group_id', [':group_id' => $data['id']])
            ->willReturn([]); // Return an empty array to simulate no clients assigned to the group

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Service::class)
            ->onlyMethods(['deleteGroup'])
            ->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->willReturn(true);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);
        $admin_Client->setService($serviceMock);

        $result = $admin_Client->group_delete($data);

        $this->assertTrue($result);
    }

    public function testgroupGet(): void
    {
        $data['id'] = '2';

        $model = new \Model_ClientGroup();
        $model->loadBean(new \DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')->willReturn($model);

        $dbMock->expects($this->atLeastOnce())
            ->method('toArray')->willReturn([]);

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;

        $admin_Client = new \Box\Mod\Client\Api\Admin();
        $admin_Client->setDi($di);

        $result = $admin_Client->group_get($data);

        $this->assertIsArray($result);
    }

    public function testloginHistoryDelete(): void
    {
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ActivityClientHistory());
        $dbMock->expects($this->atLeastOnce())
            ->method('trash');

        $admin_Client = new \Box\Mod\Client\Api\Admin();

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $admin_Client->setDi($di);

        $data = ['id' => 1];
        $result = $admin_Client->login_history_delete($data);
        $this->assertTrue($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Api\Admin::class)->onlyMethods(['delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }

    public function testBatchDeleteLog(): void
    {
        $activityMock = $this->getMockBuilder('\\' . \Box\Mod\Client\Api\Admin::class)->onlyMethods(['login_history_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('login_history_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_log(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}
