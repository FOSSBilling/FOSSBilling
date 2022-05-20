<?php
class Api_Client_ServiceSolusvmTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'servicesolusvm.xml';

    public function testservicesolusvm()
    {
        $data = array(
            'order_id'    =>  12,
            'password'    =>  'newpassword',
            'hostname'    =>  'hostname.com',
            'template'    =>  'ubuntu',
        );
        
        $bool = $this->api_client->servicesolusvm_reboot($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_boot($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_shutdown($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_set_root_password($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_set_hostname($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_change_password($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->servicesolusvm_rebuild($data);
        $this->assertTrue($bool);
        
        $text = $this->api_client->servicesolusvm_status($data);
        $this->assertEquals('online',$text);
        
        $array = $this->api_client->servicesolusvm_info($data);
        $this->assertIsArray($array);
    }
}