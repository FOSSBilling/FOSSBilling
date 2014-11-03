<?php
/**
 * @group Core
 */
class Box_Mod_Cart_Api_ClientTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_cart.xml';
    
    public function testCheckout()
    {
        $this->api_guest->cart_set_currency(array('currency'=>'USD'));
        
        // test custom products
        $data = array(
            'id'       =>  1,
            'period'    =>  '1M',
            'quantity'  =>  2,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();

        $bool = $this->api_guest->cart_apply_promo(array('promocode'=>'PHPUNIT'));
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();

        $this->assertTrue($cart_before_promo['total'] != $cart_after_promo['total'], 'Could not apply promo to cart');

        $array = $this->api_client->cart_checkout();
        $this->assertInternalType('array',$array);
        $this->assertTrue(isset($array['invoice_hash']));
        $this->assertTrue(isset($array['order_id']));
    }

    public function testCheckout_EmptyCart()
    {
        $this->api_guest->cart_set_currency(array('currency'=>'USD'));

        // test custom products
        $data = array(
            'id'       =>  1,
            'period'    =>  '1M',
            'quantity'  =>  2,
        );

        $this->setExpectedException('\Box_Exception', 'Can not checkout empty cart.');
        $this->api_client->cart_checkout();
    }
    
    /**
     * If client order total amount is 0 then activate it after placement
     */
    public function testPromoOrderActivation()
    {
        $this->api_guest->cart_set_currency(array('currency'=>'USD'));
        
        // add custom product
        $data = array(
            'id'        =>  1,
            'period'    =>  '1M',
            'quantity'  =>  1,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_guest->cart_apply_promo(array('promocode'=>'TOTAL_DISCOUNT'));
        $this->assertTrue($bool);
        
        $d = $this->api_client->cart_checkout();
        
        $order = $this->api_client->order_get(array('id'=>$d['order_id']));
        
        $this->assertEquals(0, $order['total'] - $order['discount']);
        $this->assertEquals('active', $order['status'], 'Order should be activated if total amount is 0 after discount and product setup is after_payment');
    }
    
    /**
     * Test if promo code can be applied only once per client 
     */
    public function testPromoOncePerClient()
    {
        $this->api_guest->cart_set_currency(array('currency'=>'USD'));
        
        // test custom products
        $data = array(
            'id'       =>  1,
            'period'    =>  '1M',
            'quantity'  =>  2,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_guest->cart_apply_promo(array('promocode'=>'ONCE_PER_CLIENT'));
        $this->assertTrue($bool);
        
        try {
            $this->api_client->cart_checkout();
            $this->fail('Promo code was aplied multiple times. Promo can only be applied once.');
        } catch(Exception $e) {
            $this->assertEquals(9874, $e->getCode());
        }
    }
}