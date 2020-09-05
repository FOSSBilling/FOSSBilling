<?php
/**
 * @group Core
 */
class Api_Guest_ClientTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'initial.xml';

    public function testCreate()
    {
        $this->api_admin->extension_config_save(array(
            'ext' => 'mod_client',
            'required' => array(),
        ));

        $e = rand(5, 56666).'@gmail.com';
        $pass = 'testA1sssss';

        $data = array(
            'aid'               =>  '2',
            'email'             =>  $e,
            'first_name'        =>  'John',
            'last_name'         =>  'Doe',
            'address_1'         =>  'Palo Alto',
            'country'           =>  'USA',
            'company_vat'       =>  'LS-2qwddwqdsd12',
            'city'              =>  'California',
            'tel_cc'            =>  '211',
            'phone'             =>  '11212156485451',
            'password'          =>  $pass,
            'password_confirm'  =>  $pass,
        );
        $id = $this->api_guest->client_create($data);
        $this->assertIsInt($id);
        $client = $this->di['db']->load('Client', $id);

        $this->assertNotEquals($data['password'], $client->pass);
        $this->assertTrue($this->di['password']->verify($data['password'], $client->pass));
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testRequiredFields()
    {
        $this->api_admin->extension_config_save(array(
            'ext' => 'mod_client',
            'required' => array(
                'last_name'
            ),
        ));

        $pass = 'testA222sssww';
        $data = array(
            'email'             =>  'test@example.com',
            'first_name'        =>  'John',
            'password'          =>  $pass,
            'password_confirm'  =>  $pass,
        );
        $id = $this->api_guest->client_create($data);
    }

    public function testPasswordReset()
    {
        $data = array(
            'email' =>  'client@boxbilling.com',
        );
        $bool = $this->api_guest->client_reset_password($data);
        $this->assertTrue($bool);

        $data = array(
            'hash' =>  'hash',
        );
        $bool = $this->api_guest->client_confirm_reset($data);
        $this->assertTrue($bool);
    }

    public function testVat()
    {
        $data = array(
            'country'   => 'GB',
            'vat'       => 'GB999 9999 73',
        );
        $bool = $this->api_guest->client_is_vat($data);
        //$this->assertTrue($bool);
    }
    
    public function testClientLogin()
    {
        $data = array(
            'email'     =>  'client@boxbilling.com',
            'password'  =>  'demo',
            'remember'  => true
        );
        $array = $this->api_guest->client_login($data);
        $this->assertIsArray($array);
        $this->assertTrue(is_numeric($this->session->get('client_id')), 'Client id is not integer');

        $bool = $this->api_client->client_logout($data);
        $this->assertNull($this->session->get('client_id'));
                
        $this->assertTrue($bool);
        $this->assertNull($this->session->get('admin'));
    }

    public function testRequired()
    {
        $array = $this->api_guest->client_required();
        $this->assertIsArray($array);
    }

    public function testCreateWithCustom()
    {
        $this->api_admin->extension_config_save(array(
            'ext'           => 'mod_client',
            'required'      => array(
                'last_name'
            ),
            'custom_fields' => array(
                'custom_5' => array(
                    'active'   => true,
                    'required' => true,
                    'title'    => 'Your username'
                )
            )
        ));


        $e    = rand(5, 56666) . '@gmail.com';
        $pass = 'testA1sssss';

        $data = array(
            'aid'              => '2',
            'email'            => $e,
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'address_1'        => 'Palo Alto',
            'country'          => 'USA',
            'company_vat'      => 'LS-2qwddwqdsd12',
            'city'             => 'California',
            'tel_cc'           => '211',
            'phone'            => '11212156485451',
            'password'         => $pass,
            'password_confirm' => $pass,
            'custom_5'         => 'JohnUsername'

        );
        $id   = $this->api_guest->client_create($data);
        $this->assertIsInt($id);
        $client = $this->di['db']->load('Client', $id);

        $this->assertEquals($data['custom_5'], $client->custom_5);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testCreateWithCustomRequiredException()
    {
        $this->api_admin->extension_config_save(array(
            'ext'           => 'mod_client',
            'required'      => array(
                'last_name'
            ),
            'custom_fields' => array(
                'custom_5' => array(
                    'active'   => true,
                    'required' => true,
                    'title'    => 'Your username'
                )
            )
        ));

        $e    = rand(5, 56666) . '@gmail.com';
        $pass = 'testA1sssss';

        $data = array(
            'aid'              => '2',
            'email'            => $e,
            'first_name'       => 'John',
            'last_name'        => 'Doe',
            'address_1'        => 'Palo Alto',
            'country'          => 'USA',
            'company_vat'      => 'LS-2qwddwqdsd12',
            'city'             => 'California',
            'tel_cc'           => '211',
            'phone'            => '11212156485451',
            'password'         => $pass,
            'password_confirm' => $pass,

        );
        $id   = $this->api_guest->client_create($data);
    }
}