<?php
/**
 * @group Core
 */
class Api_Admin_ClientTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'initial.xml';

    public function testClient()
    {
        $data = array(
            'id'    =>  1,
        );
        $array = $this->api_admin->client_get($data);
        $this->assertInternalType('array', $array);
        
        $array = $this->api_admin->client_login($data);
        $this->assertInternalType('array', $array);

        $data['email'] = 'new@gmail.com';
        $data['first_name'] = 'phpunit';
        $data['last_name'] = 'same';
        $data['status'] = 'active';
        $bool = $this->api_admin->client_update($data);
        $this->assertTrue($bool);
        
        $data['password'] = 'new';
        $data['password_confirm'] = 'new';
        $bool = $this->api_admin->client_change_password($data);
        $this->assertTrue($bool);

        $array = $this->api_admin->client_get_statuses($data);
        $this->assertInternalType('array', $array);

        $bool = $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit'));
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list($data);
        $this->assertInternalType('array', $array);
        $this->assertEquals(count($array['list']), 1);

        $bool = $this->api_admin->client_balance_delete(array('id' => 1));
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list($data);
        $this->assertInternalType('array', $array);
        $this->assertEquals(count($array['list']), 0);
        
        
        $data = array(
            'id'    => 1,
        );
        $bool = $this->api_admin->client_login_history_delete($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->client_batch_expire_password_reminders();
        $this->assertTrue($bool);
    }

    public function testGetPairs()
    {
        $data = array(
            'search'    =>  'de',
        );

        $array = $this->api_admin->client_get_pairs($data);
        $this->assertInternalType('array', $array);
    }
    
    public function testGroups()
    {
        $data = array(
            'title'    =>  'testers',
        );

        $id = $this->api_admin->client_group_create($data);
        $this->assertTrue(is_numeric($id));
        
        $data['id'] = $id;
        $bool = $this->api_admin->client_group_update($data);
        $this->assertTrue($bool);
        
        $array = $this->api_admin->client_group_get($data);
        $this->assertInternalType('array', $array);

        $array = $this->api_admin->client_group_get_pairs($data);
        $this->assertInternalType('array', $array);
        
        $bool = $this->api_admin->client_group_delete($data);
        $this->assertTrue($bool);
    }
    
    public function testGet()
    {
        $data = array(
            'id'    =>  1,
        );
        $array = $this->api_admin->client_get($data);
        $this->assertInternalType('array', $array);
        $this->assertNull($array['auth_type']);
    }
    
    public function testCreate()
    {
        $data = array(
            'email'    =>  'tester@gmail.com',
            'first_name'    =>  'Client',
            'password'    =>  'password',
        );

        $id = $this->api_admin->client_create($data);
        $this->assertTrue($id > 1);
        
        $bool = $this->api_admin->client_delete(array('id'=>$id));
        $this->assertTrue($bool);
    }

    public function testLoginHistoryGetList()
    {
        $array = $this->api_admin->client_login_history_get_list(array());
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

            $this->assertArrayHasKey('client', $item);
            $staff = $item['client'];
            $this->assertInternalType('array', $staff);
            $this->assertArrayHasKey('id', $staff);
            $this->assertArrayHasKey('first_name', $staff);
            $this->assertArrayHasKey('last_name', $staff);
            $this->assertArrayHasKey('email', $staff);
        }
    }

    public function testClientGetList()
    {
        $array = $this->api_admin->client_get_list(array());
        $this->assertInternalType('array', $array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertInternalType('array', $list);

        if (count($list)) {
            $item = $list[0];
            $this->assertInternalType('array', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('aid', $item);
            $this->assertArrayHasKey('email', $item);
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('group_id', $item);
            $this->assertArrayHasKey('company', $item);
            $this->assertArrayHasKey('company_vat', $item);
            $this->assertArrayHasKey('company_number', $item);
            $this->assertArrayHasKey('first_name', $item);
            $this->assertArrayHasKey('last_name', $item);
            $this->assertArrayHasKey('gender', $item);
            $this->assertArrayHasKey('birthday', $item);
            $this->assertArrayHasKey('phone_cc', $item);
            $this->assertArrayHasKey('phone', $item);
            $this->assertArrayHasKey('address_1', $item);
            $this->assertArrayHasKey('address_2', $item);
            $this->assertArrayHasKey('city', $item);
            $this->assertArrayHasKey('state', $item);
            $this->assertArrayHasKey('postcode', $item);
            $this->assertArrayHasKey('country', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('notes', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('balance', $item);
            $this->assertArrayHasKey('auth_type', $item);
            $this->assertArrayHasKey('api_token', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('tax_exempt', $item);
            $this->assertArrayHasKey('group', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }

    public function testClientBalanceGetList()
    {
        $bool = $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit'));
        $this->assertTrue($bool);

        $array = $this->api_admin->client_balance_get_list(array());
        $this->assertInternalType('array', $array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertInternalType('array', $list);
        if (count($list)) {
            $item = $list[0];
            $this->assertInternalType('array', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('amount', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('created_at', $item);

        }
    }
}