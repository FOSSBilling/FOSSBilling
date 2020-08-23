<?php
/**
 * @group Core
 */
class Box_Mod_Cart_Api_AdminTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_cart.xml';

    public function testCarts()
    {
        $array = $this->api_admin->cart_get_list();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)){
            $item = $list[0];
            $this->assertArrayHasKey('promocode', $item);
            $this->assertArrayHasKey('discount', $item);
            $this->assertArrayHasKey('total', $item);
            $this->assertArrayHasKey('items', $item);
            $this->assertArrayHasKey('currency', $item);
            $currency = $item['currency'];
            $this->assertIsArray($currency);
            $this->assertArrayHasKey("code", $currency);
            $this->assertArrayHasKey("title", $currency);
            $this->assertArrayHasKey("conversion_rate", $currency);
            $this->assertArrayHasKey("format", $currency);
            $this->assertArrayHasKey("price_format", $currency);
            $this->assertArrayHasKey("default", $currency);
        }
    }

    public function testGet()
    {
        $data = array(
            'id' => 1
        );
        $cartArr = $this->api_admin->cart_get($data);
        $this->assertIsArray($cartArr);
    }

    public function testExpire()
    {
        $bool = $this->api_admin->cart_batch_expire();
        $this->assertTrue($bool);
    }
}