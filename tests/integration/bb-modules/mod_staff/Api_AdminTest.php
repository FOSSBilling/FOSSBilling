<?php
/**
 * @group Core
 */
class Api_Admin_StaffTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'admins.xml';
    
    public function testStaff()
    {
        $array = $this->api_admin->staff_get_list(array('status'=>'active'));
        $this->assertInternalType('array', $array);

        $data = array(
            'admin_group_id' => 1,
            'email' =>  rand(5, 8787878).'_test@admin.com',
            'password' =>  'dem2123123AAo',
            'name' =>  'John Doe',
            'signature' =>  'this is test sig',
        );
        $id = $this->api_admin->staff_create($data);
        $this->assertInternalType('int', $id);

        $staffModel = $this->di['db']->load('Admin', $id);
        $this->assertNotEquals($data['password'], $staffModel->pass);

        $data['id'] = $id;
        $array = $this->api_admin->staff_get($data);
        $this->assertInternalType('array', $array);

        $data['id'] = $id;
        $data['email']  =   'new@email.com';
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
        $this->isNull($staffModel);
    }

    public function testChangePasswordException(){
        $this->setExpectedException('Box_Exception');
        $data = array(
            'id' => 1,
            'password'=> 'new123123',
            'password_confirm' => 'wrong_confirmation'
        );

        $data['password'] = 'new123123';
        $data['password_confirm'] = 'wrong_confirmation';
        $bool = $this->api_admin->staff_change_password($data);
        $this->assertTrue($bool);
    }

    public function testPermissions()
    {
        $data = array(
            'id'            =>  1,
        );
        $array = $this->api_admin->staff_permissions_get($data);
        $this->assertInternalType('array', $array);
        
        $perms = array();
        $perms['activity'] = 1;
        
        $data = array(
            'id'            =>  1,
            'permissions'            =>  $perms,
        );
        $bool = $this->api_admin->staff_permissions_update($data);
        $this->assertTrue($bool);
        
        $array = $this->api_admin->staff_permissions_get($data);
        $this->assertInternalType('array', $array);
        $this->assertEquals($perms, $array);
    }
    
    public function testGroups()
    {
        $data = array(
            'name' => 'Support',
        );
        $id = $this->api_admin->staff_group_create($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'  =>  $id,
            'name'  =>  'Staff',
        );
        $bool = $this->api_admin->staff_group_update($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->staff_group_get_list($data);
        $this->assertInternalType('array', $array);

        $array = $this->api_admin->staff_group_get_pairs($data);
        $this->assertInternalType('array', $array);

        $array = $this->api_admin->staff_group_get($data);
        $this->assertInternalType('array', $array);

        $bool = $this->api_admin->staff_group_delete($data);
        $this->assertTrue($bool);
    }
    
    public function testHistory()
    {
        $array = $this->api_admin->staff_login_history_get_list();
        $this->assertInternalType('array', $array);

        $data = array(
            'id'    => 1,
        );
        $array = $this->api_admin->staff_login_history_get($data);
        $this->assertInternalType('array', $array);

        $bool = $this->api_admin->staff_login_history_delete($data);
        $this->assertTrue($bool);
    }

    public function testStaffActivityHistoryList()
    {
        $array = $this->api_admin->staff_login_history_get_list(array('status' => 'active'));
        $this->assertInternalType('array', $array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertInternalType('array', $list);
        if (count($list)) {
            $item = $list[0];
            $this->assertInternalType('array', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('staff', $item);
            $staff = $item['staff'];
            $this->assertInternalType('array', $staff);
            $this->assertArrayHasKey('id', $staff);
            $this->assertArrayHasKey('name', $staff);
            $this->assertArrayHasKey('email', $staff);
        }
    }

    public function testStaffGroupGetList()
    {
        $array = $this->api_admin->staff_group_get_list(array());
        $this->assertInternalType('array', $array);


        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertInternalType('array', $list);
        $item = $list[0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
    }

}