<?php
/**
 * @group Core
 */
class Api_Client_ProfileTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_client.xml';
    
    public function testProfile()
    {
        $array = $this->api_client->profile_get();
        $this->assertIsArray($array);

        $data = array(
            'email'             =>  'email@test.com',
            'first_name'        =>  'First name',
            'last_name'         =>  'Last Name',
            'address_1'         =>  'address 1',
            'address_2'         =>  'address 2',
            'country'           =>  'country',
            'city'              =>  'city',
            'state'             =>  'n/a',
            'postcode'          =>  '123456',
            'phone_cc'          =>  1234,
            'phone'             =>  1212121212121,
        );
        $bool = $this->api_client->profile_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_client->profile_update($data);
        $this->assertTrue($bool);
    }

    public function testPassword()
    {
        $data = array(
            'password' =>  'demo11AA1111',
            'password_confirm' =>  'demo11AA1111',
        );
        $bool = $this->api_client->profile_change_password($data);
        $this->assertTrue($bool);
    }

    public function testApi()
    {
        $string = $this->api_client->profile_api_key_reset();
        $this->assertIsString($string);
        
        $string = $this->api_client->profile_api_key_get();
        $this->assertIsString($string);
    }
    
    public function testBalance()
    {
        $array = $this->api_client->client_balance_get_list();
        $this->assertIsArray($array);
    }

    public function testLogout()
    {
        $bool = $this->api_client->profile_logout();
        $this->assertTrue($bool);
    }
    
    public function testEmailChange()
    {
        //enable email change
        $config = array(
            'ext'                  =>  'mod_client',
            'allow_change_email'   =>  true,
        );
        $bool = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->profile_update(array('email'=>'test@email.com'));
        $this->assertTrue($bool);
        
        //disable email change
        $config = array(
            'ext'                  =>  'mod_client',
            'allow_change_email'   =>  false,
        );
        $bool = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);
        
        try {
            $this->api_client->profile_update(array('email'=>'new@email.com'));
            $this->fail('Email should not changed due to setting');
        } catch(Exception $e) {
            //ok
        }
    }
}