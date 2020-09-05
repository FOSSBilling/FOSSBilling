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

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Can not checkout empty cart.');

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

    /**
     * checkout cart with product with addon and client has insufficient funds to cover
     */
    public function testAddonActivatedWhileOrderInvoiceUnpaid()
    {

        $this->api_guest->cart_reset();
        $data = array(
            'id'       =>  11,
            'period'    =>  '6M',
            'domain' => array(
                'action' => "register",
                'owndomain_sld' =>"",
                'owndomain_tld' =>".com",
                'register_sld' =>"newdomainregister",
                'register_tld' =>".com",
                'register_years' => "1",
            ),
            'multiple' => 1,

        );
        $this->api_guest->cart_add_item($data);
        $this->api_client->cart_checkout();

        $masterOrder = $this->di['db']->findOne('ClientOrder', 'group_master = 1 ORDER BY id desc');
        $addonOrder = $this->di['db']->findOne('ClientOrder', 'group_id = ? and group_master = 0', array($masterOrder->group_id));

        $invoiceModel = $this->di['db']->load('Invoice', $masterOrder->unpaid_invoice_id);

        $this->assertInstanceOf('Model_ClientOrder', $masterOrder);
        $this->assertInstanceOf('Model_ClientOrder', $addonOrder);
        $this->assertInstanceOf('Model_Invoice', $invoiceModel);

        $this->assertNotNull($masterOrder->unpaid_invoice_id);
        $this->assertEquals(Model_ClientOrder::STATUS_PENDING_SETUP, $masterOrder->status);
        $this->assertEquals(Model_ClientOrder::STATUS_PENDING_SETUP, $addonOrder->status);
        $this->assertEquals(Model_Invoice::STATUS_UNPAID, $invoiceModel->status);
    }


    /**
     * checkout cart with product with addon and client has sufficient funds to cover
     */
    public function testOrderWithAddonCoverFromAccountBalance()
    {
        $this->api_guest->cart_reset();
        $data = array(
            'id'       =>  11,
            'period'    =>  '6M',
            'domain' => array(
                'action' => "register",
                'owndomain_sld' =>"",
                'owndomain_tld' =>".com",
                'register_sld' =>"newdomainregister2",
                'register_tld' =>".com",
                'register_years' => "1",
            ),
            'multiple' => 1,

        );

        $this->api_admin->client_balance_add_funds(array('id' => 1, 'amount' => 100, 'description' => 'Added from PHPUnit'));
        $this->api_guest->cart_add_item($data);
        $this->api_client->cart_checkout();

        $masterOrder = $this->di['db']->findOne('ClientOrder', 'group_master = 1 ORDER BY id desc');
        $addonOrder = $this->di['db']->findOne('ClientOrder', 'group_id = ? and group_master = 0', array($masterOrder->group_id));

        $invoiceItemModel = $this->di['db']->findOne('InvoiceItem', "type = 'order' and rel_id = ?", array($masterOrder->id));
        $invoiceModel = $this->di['db']->load('Invoice', $invoiceItemModel->invoice_id);

        $this->assertInstanceOf('Model_ClientOrder', $masterOrder);
        $this->assertInstanceOf('Model_ClientOrder', $addonOrder);
        $this->assertInstanceOf('Model_Invoice', $invoiceModel);

        $this->assertNull($masterOrder->unpaid_invoice_id);
        $this->assertEquals(Model_ClientOrder::STATUS_ACTIVE, $masterOrder->status);
        $this->assertEquals(Model_ClientOrder::STATUS_ACTIVE, $addonOrder->status);
        $this->assertEquals(Model_Invoice::STATUS_PAID, $invoiceModel->status);
    }

}