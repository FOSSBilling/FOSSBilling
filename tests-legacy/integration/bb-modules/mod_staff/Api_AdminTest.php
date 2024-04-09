<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Admin_StaffTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'admins.xml';

    public function testStaff(): void
    {
        $array = $this->api_admin->staff_get_list(['status' => 'active']);
        $this->assertIsArray($array);

        $data = [
            'admin_group_id' => 1,
            'email' => random_int(5, 8_787_878) . '_test@admin.com',
            'password' => 'dem2123123AAo',
            'name' => 'John Doe',
            'signature' => 'this is test sig',
            'status' => 'active',
        ];
        $id = $this->api_admin->staff_create($data);
        $this->assertIsInt($id);

        $staffModel = $this->di['db']->load('Admin', $id);
        $this->assertNotEquals($data['password'], $staffModel->pass);

        $data['id'] = $id;
        $array = $this->api_admin->staff_get($data);
        $this->assertIsArray($array);

        $data['id'] = $id;
        $data['email'] = 'new@email.com';
        $bool = $this->api_admin->staff_update($data);
        $this->assertTrue($bool);

        $data['password'] = 'new123123';
        $data['password_confirm'] = 'new123123';
        $bool = $this->api_admin->staff_change_password($data);
        $this->assertTrue($bool);

        $staffModel = $this->di['db']->load('Admin', $id);
        $this->assertNotEquals($data['password'], $staffModel->pass);

        $bool = $this->api_admin->staff_delete($data);
        $this->assertTrue($bool);

        $staffModel = $this->di['db']->load('Admin', $id);
        $this->isNull();
    }

    public function testChangePasswordException(): void
    {
        $this->expectException(FOSSBilling\Exception::class);

        $data = [
            'id' => 1,
            'password' => 'new123123',
            'password_confirm' => 'wrong_confirmation',
        ];

        $data['password'] = 'new123123';
        $data['password_confirm'] = 'wrong_confirmation';
        $bool = $this->api_admin->staff_change_password($data);
        $this->assertTrue($bool);
    }

    public function testPermissions(): void
    {
        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->staff_permissions_get($data);
        $this->assertIsArray($array);

        $perms = [];
        $perms['activity'] = 1;

        $data = [
            'id' => 1,
            'permissions' => $perms,
        ];
        $bool = $this->api_admin->staff_permissions_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->staff_permissions_get($data);
        $this->assertIsArray($array);
        $this->assertEquals($perms, $array);
    }

    public function testGroups(): void
    {
        $data = [
            'name' => 'Support',
        ];
        $id = $this->api_admin->staff_group_create($data);
        $this->assertTrue(is_numeric($id));

        $data = [
            'id' => $id,
            'name' => 'Staff',
        ];
        $bool = $this->api_admin->staff_group_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->staff_group_get_list($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->staff_group_get_pairs($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->staff_group_get($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->staff_group_delete($data);
        $this->assertTrue($bool);
    }

    public function testHistory(): void
    {
        $array = $this->api_admin->staff_login_history_get_list();
        $this->assertIsArray($array);

        $data = [
            'id' => 1,
        ];
        $array = $this->api_admin->staff_login_history_get($data);
        $this->assertIsArray($array);

        $bool = $this->api_admin->staff_login_history_delete($data);
        $this->assertTrue($bool);
    }

    public function testStaffActivityHistoryList(): void
    {
        $array = $this->api_admin->staff_login_history_get_list(['status' => 'active']);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('staff', $item);
            $staff = $item['staff'];
            $this->assertIsArray($staff);
            $this->assertArrayHasKey('id', $staff);
            $this->assertArrayHasKey('name', $staff);
            $this->assertArrayHasKey('email', $staff);
        }
    }

    public function testStaffGroupGetList(): void
    {
        $array = $this->api_admin->staff_group_get_list([]);
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        $item = $list[0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
    }

    public function testBatchDelete(): void
    {
        $array = $this->api_admin->staff_login_history_get_list([]);

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->staff_batch_delete_logs(['ids' => $ids]);
        $array = $this->api_admin->staff_login_history_get_list([]);

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }
}
