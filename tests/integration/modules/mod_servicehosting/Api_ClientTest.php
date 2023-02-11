<?php
/**
 * @group Core
 */
class Api_Client_ServiceHostingTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'services.xml';

    public function testLists()
    {
        $array = $this->api_client->servicehosting_hp_get_pairs();
        $this->assertIsArray($array);
    }

    public static function orders()
    {
        return array(
            array(5),
            array(6),
        );
    }

    /**
     * @dataProvider orders
     */
    public function testServiceHosting($id)
    {
        $this->api_admin->order_renew(array('id'=>$id));

        $data = array(
            'order_id'    =>  $id,
            'password'   =>  'test',
            'password_confirm'   =>  'test',
        );
        $bool = $this->_callOnService('change_password', $data);
        $this->assertTrue($bool);

        $data = array(
            'order_id'   =>  $id,
            'username'   =>  'John',
        );
        $bool = $this->_callOnService('change_username', $data);
        $this->assertTrue($bool);

        $data = array(
            'order_id'      =>  $id,
            'sld'        =>  'mynewdomain',
            'tld'        =>  '.com',
        );
        $bool = $this->_callOnService('change_domain', $data);
        $this->assertTrue($bool);
    }

    protected function _callOnService($method, $data)
    {
        $m = "serviceHosting_".$method;
        return $this->api_client->{$m}($data);
    }
}