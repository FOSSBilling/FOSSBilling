<?php

declare(strict_types=1);

namespace Box\Mod\Staff\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetList(): void
    {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => ['id' => 1],
        ];
        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $adminModel = new \Model_Admin();
        $adminModel->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($adminModel);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toModel_AdminApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testUpdate(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testDelete(): void
    {
        $data['id'] = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('delete')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testChangePassword(): void
    {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test!23A',
        ];

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())
            ->method('isPasswordStrong')
            ->willReturn(true);

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('changePassword')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Admin());

        $di = $this->getDi();
        $di['validator'] = $validatorMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);
        $result = $this->api->change_password($data);

        $this->assertTrue(true);
    }

    public function testChangePasswordPasswordDonotMatch(): void
    {
        $data = [
            'id' => '1',
            'password' => 'test!23A',
            'password_confirm' => 'test',
        ];

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Passwords do not match');
        $this->api->change_password($data);
    }

    public function testCreate(): void
    {
        $data = [
            'admin_group_id' => '1',
            'password' => 'test!23A',
            'email' => 'okey@example.com',
            'name' => 'OkeyTest',
        ];

        $newStaffId = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($newStaffId);

        $validatorMock = $this->createMock(\FOSSBilling\Validate::class);
        $validatorMock->expects($this->atLeastOnce())
            ->method('isPasswordStrong')
            ->willReturn(true);

        $di = $this->getDi();
        $di['validator'] = $validatorMock;

        $toolsMock = $this->createMock(\FOSSBilling\Tools::class);
        $toolsMock->expects($this->atLeastOnce())->method('validateAndSanitizeEmail');
        $di['tools'] = $toolsMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newStaffId, $result);
    }

    public function testPermissionsGet(): void
    {
        $data['id'] = 1;

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getPermissions')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_get($data);
        $this->assertIsArray($result);
    }

    public function testPermissionsUpdate(): void
    {
        $data = [
            'id' => '1',
            'permissions' => 'default',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('setPermissions')
            ->willReturn(true);

        $staffModel = new \Model_Admin();
        $staffModel->loadBean(new \DummyBean());

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($staffModel);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->permissions_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGroupGetPairs(): void
    {
        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupPair')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->group_get_pairs([]);
        $this->assertIsArray($result);
    }

    public function testGroupGetList(): void
    {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminGroupSearchQuery')
            ->willReturn(['sqlString', []]);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get_list($data);
        $this->assertIsArray($result);
    }

    public function testGroupCreate(): void
    {
        $data['name'] = 'Prime Group';
        $newGroupId = 1;

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('createGroup')
            ->willReturn($newGroupId);

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_create($data);
        $this->assertIsInt($result);
        $this->assertEquals($newGroupId, $result);
    }

    public function testGroupGet(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toAdminGroupApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_get($data);
        $this->assertIsArray($result);
    }

    public function testGroupDelete(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deleteGroup')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testGroupUpdate(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('updateGroup')
            ->willReturn(true);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_AdminGroup());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->group_update($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testLoginHistoryGetList(): void
    {
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getActivityAdminHistorySearchQuery')
            ->willReturn(['sqlString', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $resultSet = [
            'list' => ['id' => 1],
        ];
        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $model = new \Model_ActivityAdminHistory();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['pager'] = $pagerMock;
        $di['db'] = $dbMock;

        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get_list($data);
        $this->assertIsArray($result);
    }

    public function testLoginHistoryGet(): void
    {
        $data['id'] = '1';

        $serviceMock = $this->createMock(\Box\Mod\Staff\Service::class);
        $serviceMock->method('toActivityAdminHistoryApiArray')
            ->willReturn([]);

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_ActivityAdminHistory());

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $this->api->setIdentity(new \Model_Admin());
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->login_history_get($data);
        $this->assertIsArray($result);
    }
}
