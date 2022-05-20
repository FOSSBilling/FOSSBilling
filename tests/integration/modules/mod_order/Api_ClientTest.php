<?php
/**
 * @group Core
 */
class Api_Client_OrderTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';

    public function testOrders()
    {
        $data = array(
            'page'       =>  1,
            'per_page'   =>  10,
        );
        $array = $this->api_client->order_get_list($data);
        $this->assertIsArray($array);

        $data = array(
            'page'       =>  1,
            'per_page'   =>  10,
            'expiring'   =>  1,
        );
        $array = $this->api_client->order_get_list($data);
        $this->assertIsArray($array);
        
        $data = array(
            'id'       =>  1,
        );
        $array = $this->api_client->order_get($data);
        $this->assertIsArray($array);

        $array = $this->api_client->order_addons($data);
        $this->assertIsArray($array);
    }

    public function testDelete()
    {
        $data = array(
            'id'    =>  9,
        );

        $bool = $this->api_client->order_delete($data);
        $this->assertTrue($bool);
    }

    public function testService()
    {
        $data = array(
            'id' => 8,
        );

        $expected = array(
            'id' => 1,
            'client_id' => 1,
            'plugin' => 'Example',
            'updated_at' => null,
            'created_at' => null,
        );


        $result = $this->api_client->order_service($data);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $this->assertEquals($expected, $result);
    }

    public function testupgradables_NoUpgradablesFound()
    {
        $data = array(
            'id' => 8,
        );

        $result = $this->api_client->order_upgradables($data);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
