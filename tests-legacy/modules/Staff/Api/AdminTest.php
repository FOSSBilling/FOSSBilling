<?php

namespace Box\Mod\Staff\Api;

class AdminTest extends \BBTestCase
{
    /**
     * @var Admin
     */
    protected $api;

    public function setup(): void
    {
        $this->api = new Admin();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetList(): void
    {
        $data = [];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => [
                ['id' => 1],
            ],
        ];
        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($adminModel);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        $this->assertIsArray($result);
    }

    public function testget(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testupdate(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testdelete(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testchangePassword(): void
    {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test!23A',
        ];

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('isPasswordStrong');
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('changePassword')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->change_password($data);

        $this->assertTrue(true);
    }

    public function testchangePasswordPasswordDonotMatch(): void
    {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test',
        ];

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Passwords do not match');
        $this->api->change_password($data);
    }

    public function testcreate(): void
    {
        $data = [
            'admin_group_id' => '1',
            'password' => 'test!23A',
            'email' => 'okey@example.com',
            'name' => 'OkeyTest',
        ];

        $newStaffId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newStaffId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $toolsMock = $this->getMockBuilder('\\' . \FOSSBilling\Tools::class)->getMock();
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newStaffId, $result);
    }

    public function testpermissionsGet(): void
    {
        $data['id'] = 1;

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \DummyBean());

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_get($data);
        $this->assertIsArray($result);
    }

    public function testpermissionsUpdate(): void
    {
        $data = [
            'id' => '1',
            'permissions' => 'default',
        ];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('setPermissions')
            ->willReturn(true);

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \DummyBean());

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgroupGetPairs(): void
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupPair')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->group_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testgroupGetList(): void
    {
        $data = [];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get_list($data);
        $this->assertIsArray($result);
    }

    public function testgroupCreate(): void
    {
        $data['name'] = 'Prime Group';
        $newGroupId = 1;

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('createGroup')
            ->willReturn($newGroupId);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testgroupGet(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toAdminGroupApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get($data);
        $this->assertIsArray($result);
    }

    public function testgroupDelete(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testgroupUpdate(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateGroup')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testloginHistoryGetList(): void
    {
        $data = [];

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getActivityAdminHistorySearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => ['id' => 1],
        ];
        $pagerMock = $this->getMockBuilder('\\' . \FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $model = new \Model_ActivityAdminHistory();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->method('getExistingModelById')
            ->willReturn($model);

        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get_list($data);
        $this->assertIsArray($result);
    }

    public function testloginHistoryGet(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ActivityAdminHistory());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get($data);
        $this->assertIsArray($result);
    }

    public function testloginHistoryDelete(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Staff\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteLoginHistory')
            ->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ActivityAdminHistory());

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testBatchDelete(): void
    {
        $activityMock = $this->getMockBuilder('\\' . Admin::class)->onlyMethods(['login_history_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('login_history_delete')->willReturn(true);

        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_logs(['ids' => [1, 2, 3]]);
        $this->assertTrue($result);
    }
}
