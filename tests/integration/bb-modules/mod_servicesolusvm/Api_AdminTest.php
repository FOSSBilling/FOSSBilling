<?php
class Api_Admin_ServiceSolusvmTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'servicesolusvm.xml';

    public function testConfigs()
    {
        $array = $this->api_admin->servicesolusvm_cluster_config();
        $this->assertInternalType('array',$array);
        $this->assertEquals('api_id', $array['id']);
        $this->assertEquals('api_key', $array['key']);
        $this->assertEquals('123.123.123.123', $array['ipaddress']);
        
        $bool = $this->api_admin->servicesolusvm_cluster_config_update(array('id'=>'api_id_new', 'key'=>'api_key_new', 'ipaddress'=>'1.1.1.1', 'secure'=>false, 'port'=>''));
        $this->assertTrue($bool);
        
        $array = $this->api_admin->servicesolusvm_cluster_config();
        $this->assertInternalType('array',$array);
        $this->assertEquals('api_id_new', $array['id']);
        $this->assertEquals('api_key_new', $array['key']);
        $this->assertEquals('1.1.1.1', $array['ipaddress']);
        
        $array = $this->api_admin->servicesolusvm_get_virtualization_types();
        $this->assertInternalType('array',$array);
    }
    
    public function testServiceUpdate()
    {
        $data = array(
            'order_id'    =>  12,
            'password'    =>  'newpassword',
            'plan'        =>  'new_plan',
            'hostname'    =>  'hostname.com',
            'template'    =>  'ubuntu',
        );
        
        $bool = $this->api_admin->servicesolusvm_update($data);
        $this->assertTrue($bool);
    }
    
    public function testService()
    {
        $data = array(
            'order_id'    =>  12,
            'password'    =>  'newpassword',
            'plan'        =>  'new_plan',
            'hostname'    =>  'hostname.com',
            'template'    =>  'ubuntu',
        );
        
        $bool = $this->api_admin->servicesolusvm_test_connection(array('server_id'=>1));
        $this->assertTrue($bool);
        
        $array = $this->api_admin->servicesolusvm_get_nodes($data);
        $this->assertInternalType('array',$array);
        
        $array = $this->api_admin->servicesolusvm_get_plans($data);
        $this->assertInternalType('array',$array);
        
        $array = $this->api_admin->servicesolusvm_get_templates($data);
        $this->assertInternalType('array',$array);
        
        $bool = $this->api_admin->servicesolusvm_reboot($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_boot($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_shutdown($data);
        $this->assertTrue($bool);
        
        $text = $this->api_admin->servicesolusvm_status($data);
        $this->assertEquals('online',$text);
        
        $array = $this->api_admin->servicesolusvm_info($data);
        $this->assertInternalType('array',$array);
        
        $bool = $this->api_admin->servicesolusvm_set_root_password($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_set_plan($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_set_hostname($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_rebuild($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_addip($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_network_disable($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_network_enable($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_tun_disable($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_tun_enable($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_pae_disable($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->servicesolusvm_pae_enable($data);
        $this->assertTrue($bool);
        
        $array = $this->api_admin->servicesolusvm_client_list($data);
        $this->assertInternalType('array',$array);
        
        $array = $this->api_admin->servicesolusvm_node_virtualservers($data);
        $this->assertInternalType('array',$array);
    }
    
    public function testImporters()
    {
        $this->assertTrue(true);

        //$bool = $this->api_admin->servicesolusvm_import_servers($data);
        //$this->assertTrue($bool);
        
        //$bool = $this->api_admin->servicesolusvm_import_clients($data);
        //$this->assertTrue($bool);
    }
}