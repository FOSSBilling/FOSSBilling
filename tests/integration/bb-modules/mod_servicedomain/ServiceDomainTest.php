<?php
/**
 * @todo review
 */
class Api_Admin_ServiceDomainTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public static function orders()
    {
        return array(
            array(3),
            array(4),
        );
    }

    /**
     * @dataProvider orders
     */
    public function testDomain($id)
    {
        $this->api_admin->order_renew(array('id'=>$id));

        $data = array(
            'order_id'    =>  $id,
            'ns1'   =>  'ns1.1freehosting.com',
            'ns2'   =>  'ns2.1freehosting.com',
            'ns3'   =>  'ns3.1freehosting.com',
            'ns4'   =>  'ns4.1freehosting.com',
        );
        $bool = $this->api_admin->servicedomain_update_nameservers($data);
        $this->assertTrue($bool);

        $data = array(
            'order_id'    =>  $id,
            'contact' =>  array(
                'first_name'=>  'John',
                'last_name' =>  'Does',
                'email'     =>  'email@example.com  ',
                'company'   =>  'Company',
                'address1'  =>  'Adress 1',
                'address2'  =>  'Adress 2',
                'country'   =>  'US',
                'city'      =>  'San Jose',
                'state'     =>  'n/a',
                'postcode'  =>  '20506',
                'phone_cc'     =>  '1408',
                'phone'       =>  '123456',
            ),
        );
        $bool = $this->_callOnService('update_contacts', $data);
        $this->assertTrue($bool, 'Failed updating contacts');

        $bool = $this->_callOnService('get_transfer_code', $data);
        $this->assertTrue($bool, 'Getting epp code');

        $bool = $this->_callOnService('enable_privacy_protection', $data);
        $this->assertTrue($bool, 'Failed Enabling privacy protection');

        $bool = $this->_callOnService('disable_privacy_protection', $data);
        $this->assertTrue($bool, 'Failed Disabling privacy protection');

        $bool = $this->_callOnService('lock', $data);
        $this->assertTrue($bool);

        $bool = $this->_callOnService('unlock', $data);
        $this->assertTrue($bool);
    }

    public function testTld()
    {
        $array = $this->api_admin->servicedomain_tld_get_list();
        $this->assertIsArray($array);

        $data = array(
            'tld'               =>  '.ru',
            'tld_registrar_id'  =>  1,
            'price_registration'=>  1,
            'price_renew'       =>  1,
            'price_transfer'    =>  1,
        );
        $id = $this->api_admin->servicedomain_tld_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $array = $this->api_admin->servicedomain_tld_get($data);
        $this->assertIsArray($array);

        $data['price_renew'] = '15';
        $bool = $this->api_admin->servicedomain_tld_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->servicedomain_tld_delete($data);
        $this->assertTrue($bool);
    }

    public function testRegistrar()
    {

        $array = $this->api_admin->servicedomain_registrar_get_pairs();
        $this->assertIsArray($array);

        $array = $this->api_admin->servicedomain_registrar_get_available();
        $this->assertIsArray($array);

        $data = array(
            'id'    =>  1,
        );
        $id = $this->api_admin->servicedomain_registrar_copy($data);
        $this->assertTrue(is_numeric($id));
        
        $data = array(
            'id'    => $id,
        );
        $array = $this->api_admin->servicedomain_registrar_get($data);
        $this->assertIsArray($array);

        $data['title'] = 'New title';
        $bool = $this->api_admin->servicedomain_registrar_update($data);
        $this->assertTrue($bool);
    }

    public function testInstall()
    {
        $array = $this->api_admin->servicedomain_registrar_get_available();
        $this->assertIsArray($array);

        $data = array(
            'code'=>$array[0],
        );
        $bool = $this->api_admin->servicedomain_registrar_install($data);
        $this->assertTrue($bool);

        $array_after = $this->api_admin->servicedomain_registrar_get_available();
        $this->assertFalse(count($array) == count($array_after));
    }

    public function testRegistrarGetList()
    {
        $array = $this->api_admin->servicedomain_registrar_get_list();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('config', $item);
            $this->assertIsArray($item['config']);
            $this->assertArrayHasKey('form', $item);
            $this->assertArrayHasKey('test_mode', $item);
        }
    }

    public function testTldGetList()
    {
        $array = $this->api_admin->servicedomain_tld_get_list();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertIsArray($item);
            $this->assertArrayHasKey('tld', $item);
            $this->assertArrayHasKey('price_registration', $item);
            $this->assertArrayHasKey('price_renew', $item);
            $this->assertArrayHasKey('price_transfer', $item);
            $this->assertArrayHasKey('active', $item);
            $this->assertArrayHasKey('allow_register', $item);
            $this->assertArrayHasKey('allow_transfer', $item);
            $this->assertArrayHasKey('min_years', $item);
            $this->assertArrayHasKey('registrar', $item);
            $registrar = $item['registrar'];
            $this->assertIsArray($registrar);
            $this->assertArrayHasKey('id', $registrar);
            $this->assertArrayHasKey('title', $registrar);
        }
    }

    protected function _callOnService($method, $data)
    {
        $m = "serviceDomain_".$method;
        return $this->api_admin->{$m}($data);
    }
}