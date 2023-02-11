<?php
/**
 * @group Core
 */
class Api_Admin_ServiceHostingTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';
    
    public function testAdminServiceHosting()
    {
        $array = $this->api_admin->servicehosting_manager_get_pairs();
        $this->assertIsArray($array);
    }
    
    public function testAccountManagement()
    {
        $data = array(
            'order_id'  =>  8,
            'sld'    =>  'example',
            'tld'    =>  '.com',
        );
        
        $bool = $this->api_admin->servicehosting_change_domain($data);
        $this->assertTrue($bool);
        
        $data['plan_id'] = 2;
        $bool = $this->api_admin->servicehosting_change_plan($data);
        $this->assertTrue($bool);
        
        $data['username'] = 'new-username';
        $bool = $this->api_admin->servicehosting_change_username($data);
        $this->assertTrue($bool);
        
        $data['ip'] = 'kitoks';
        $bool = $this->api_admin->servicehosting_change_ip($data);
        $this->assertTrue($bool);
        
        $data['password'] = 'qwerty123';
        $data['password_confirm'] = 'qwerty123';
        $bool = $this->api_admin->servicehosting_change_password($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicehosting_sync($data);
        $this->assertTrue($bool);
        
        $data['username'] = 'username123';
        $bool = $this->api_admin->servicehosting_update($data);
        $this->assertTrue($bool);
    }

    public function testHp()
    {
        $array = $this->api_admin->servicehosting_hp_get_list();
        $this->assertIsArray($array);
        
        $array = $this->api_admin->servicehosting_hp_get_pairs();
        $this->assertIsArray($array);
        
        $data = array(
            'name'  =>  'test',
        );
        $id = $this->api_admin->servicehosting_hp_create($data);
        $this->assertTrue(is_numeric($id));
        
        $data['id'] = $id;
        $array = $this->api_admin->servicehosting_hp_get($data);
        $this->assertIsArray($array);
        
        $data['quota'] = 12345;
        $bool = $this->api_admin->servicehosting_hp_update($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicehosting_hp_delete($data);
        $this->assertTrue($bool);
    }
    
    public function testServer()
    {
        $array = $this->api_admin->servicehosting_server_get_pairs();
        $this->assertIsArray($array);
        
        $data = array(
            'name'  =>  'test',
            'ip'  =>  '127.15.15.1',
            'manager'  =>  'Custom',
        );
        $id = $this->api_admin->servicehosting_server_create($data);
        $this->assertTrue(is_numeric($id));
        
        $data['id'] = $id;
        $array = $this->api_admin->servicehosting_server_get($data);
        $this->assertIsArray($array);
        
        $bool = $this->api_admin->servicehosting_server_test_connection($data);
        $this->assertTrue($bool);
        
        $data['quota'] = 12345;
        $bool = $this->api_admin->servicehosting_server_update($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicehosting_server_delete($data);
        $this->assertTrue($bool);
    }
    
    public function testServerIps()
    {
        $data = array(
            'id'    =>  1,
        );
        $array = $this->api_admin->servicehosting_server_get($data);
        $this->assertIsArray($array);
        $this->assertIsArray($array['assigned_ips']);

        $data['assigned_ips'] = '127.85.81.156'.PHP_EOL.'189.156.45.78'.PHP_EOL;
        $bool = $this->api_admin->servicehosting_server_update($data);
        $this->assertTrue($bool);
        
        $array = $this->api_admin->servicehosting_server_get($data);
        $this->assertIsArray($array['assigned_ips']);
        $this->assertEquals(2, count($array['assigned_ips']));
    }

    public function testServicehostingServerGetList()
    {
        $array = $this->api_admin->servicehosting_server_get_list(array());
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('hostname', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('ns1', $item);
            $this->assertArrayHasKey('ns2', $item);
            $this->assertArrayHasKey('ns3', $item);
            $this->assertArrayHasKey('ns4', $item);
            $this->assertArrayHasKey('cpanel_url', $item);
            $this->assertArrayHasKey('reseller_cpanel_url', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('active', $item);
            $this->assertArrayHasKey('secure', $item);
            $this->assertArrayHasKey('assigned_ips', $item);
            $this->assertIsArray($item['assigned_ips']);
            $this->assertArrayHasKey('status_url', $item);
            $this->assertArrayHasKey('max_accounts', $item);
            $this->assertArrayHasKey('manager', $item);
            $this->assertArrayHasKey('username', $item);
            $this->assertArrayHasKey('password', $item);
            $this->assertArrayHasKey('accesshash', $item);
            $this->assertArrayHasKey('port', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }

    public function testServicehostingHpGetList()
    {
        $array = $this->api_admin->servicehosting_hp_get_list(array());
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        $item = $list[0];
        $this->assertIsArray($item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('bandwidth', $item);
        $this->assertArrayHasKey('quota', $item);
        $this->assertArrayHasKey('max_ftp', $item);
        $this->assertArrayHasKey('max_sql', $item);
        $this->assertArrayHasKey('max_pop', $item);
        $this->assertArrayHasKey('max_sub', $item);
        $this->assertArrayHasKey('max_park', $item);
        $this->assertArrayHasKey('max_addon', $item);
        $this->assertArrayHasKey('config', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
    }
}