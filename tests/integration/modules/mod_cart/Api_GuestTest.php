<?php
/**
 * @group Core
 */
class Box_Mod_Cart_Api_GuestTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_cart.xml';

    public function testGet()
    {
        $cart = $this->api_guest->cart_get();
        $this->assertIsArray($cart);
    }

    public function testCurrency()
    {
        $data = array('currency'=>'USD');
        $bool = $this->api_guest->cart_set_currency($data);
        $this->assertTrue($bool);

        $c = $this->api_guest->cart_get_currency();
        $this->assertIsArray($c);
    }

    public function testReset()
    {
        $bool = $this->api_guest->cart_reset();
        $this->assertTrue($bool);
    }

    public function testAddCustomProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  1,
        );
        $bool = $this->api_guest->cart_add_item($data);

        $this->assertTrue($bool);
    }

    public function testAddAddons()
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = array(
            'id'        => $pid,
            'addons'    => array(
                5  =>  array(
                    'selected'  =>  1,
                    'period'    =>  '1Y',
                    'quantity'  =>  2,
                ),
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $cart = $this->api_guest->cart_get();
        
        //main product must be first in cart
        $this->assertEquals($pid, $cart['items'][0]['product_id']);
        
        $this->assertEquals(2, count($cart['items']));
        $this->assertEquals(2, $cart['items'][1]['quantity']);
    }

    public function testAddAddons_AddonPeriodNotEnabled()
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = array(
            'id'        => $pid,
            'addons'    => array(
                5  =>  array(
                    'selected'  =>  1,
                    'period'    =>  '1W',
                    'quantity'  =>  2,
                ),
            ),
        );

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Selected billing period is not valid for addon');

        $bool = $this->api_guest->cart_add_item($data);
    }

    public function testAddAddons_MissingPeriodParameter()
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = array(
            'id'        => $pid,
            'addons'    => array(
                5  =>  array(
                    'selected'  =>  1,
                    'quantity'  =>  2,
                ),
            ),
        );

        $this->expectException(\Box_Exception::class);
        $this->expectExceptionMessage('Addon period parameter not passed');

        $bool = $this->api_guest->cart_add_item($data);
    }

    public function testAddAddons_AddonNotFoundById()
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = array(
            'id'        => $pid,
            'addons'    => array(
                31  =>  array(
                    'selected'  =>  1,
                    'quantity'  =>  2,
                ),
            ),
        );
        $return = $this->api_guest->cart_add_item($data);

        $this->assertTrue($return);
    }

    /**
     * @expectedException \Box_Exception
     */
    public function testAddDisabledPeriod()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  17,
            'period'    =>  '1W',
        );
        $this->api_guest->cart_add_item($data);
    }

    public function testAddDomainRegisterProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'                    =>  10,
            'action'                =>  'register',
            'register_tld'          =>  '.com',
            'register_sld'          =>  'mytestdomain',
            'register_years'        =>  5,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddDomainTransferProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'                    =>  10,
            'action'                =>  'transfer',
            'transfer_tld'          =>  '.com',
            'transfer_sld'          =>  'domaintotransfer',
            'transfer_code'         =>  5,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $item = $cart['items'][0];
        $this->assertEquals('transfer', $item['action']);
        $this->assertEquals('year', $item['unit']);
    }

    public function testAddLicenseProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  6,
            'period'    =>  '3M',
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }
    
    public function testAddDownloadableProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  7,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }
    
    public function testAddSolusvmProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'            =>  15,
            'period'        =>  '3M',
            'server_id'     =>  1,
            'plan_id'       =>  1,
            'hostname'      =>  'mydomain.com',
            'template'      =>  'centos-6-x86',
            'root_password' =>  'optional', //if not passed then auto generated
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddResellerHostingProduct()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  9,
            'period'        =>  '1Y',
            'domain'    =>  array(
                'action'=>  'register',
                'register_years'=>  2,
                'register_tld'=>  '.com',
                'register_sld'=>  'resellerdomain',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }
    
    public function testAddHostingProductWithDomainRegistration()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  8,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'register',
                'register_years'=>  2,
                'register_tld'=>  '.com',
                'register_sld'=>  'example',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 2);
    }
    
    public function testAddHostingProductWithFreeDomainRegistration()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  11,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'register',
                'register_years'=>  2,
                'register_tld'=>  '.com',
                'register_sld'=>  'example',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue($cart['items'][1]['free_domain']);
        $this->assertEquals(10, $cart['items'][1]['discount'], 'Could not set free domain for hosting product');
    }
    
    public function testAddHostingProductWithFreeDomainTransfer()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  11,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'transfer',
                'transfer_code'=>  '123',
                'transfer_tld'=>  '.com',
                'transfer_sld'=>  'example',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue($cart['items'][1]['free_domain']);
        $this->assertEquals(15, $cart['items'][1]['discount'], 'Could not set free transfer for hosting product');
    }

    public function testAddHostingProductWithDomainTransfer()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  8,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'transfer',
                'transfer_code'=>  2,
                'transfer_tld'=>  '.com',
                'transfer_sld'=>  'example',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 2);
    }

    public function testAddHostingProductWithOwnDomainWhenExists()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  8,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'owndomain',
                'owndomain_tld'=>  '.com',
                'owndomain_sld'=>  'exist',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddHostingProductWithOwnDomain()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        =>  8,
            'period'    =>  '3M',
            'domain'    =>  array(
                'action'=>  'owndomain',
                'owndomain_tld'=>  '.com',
                'owndomain_sld'=>  'example',
            ),
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 1);
    }

    public function testFreeSetupPromo()
    {
        $this->api_admin->currency_set_default(array('code'=>'USD'));
        $this->api_guest->cart_reset();
        $this->api_guest->cart_set_currency(array('currency'=>'USD'));
        
        // test custom products
        $data = array(
            'id'       =>  13,
            'quantity'  =>  1,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $cart_before_promo = $this->api_guest->cart_get();
        $this->assertNull($cart_before_promo['promocode']);
        
        $data = array('promocode'=>'FREE_SETUP');
        $bool = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);
        
        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertNotNull($cart_after_promo['promocode']);
        $this->assertEquals(0, $cart_after_promo['items'][0]['discount_price']);
        $this->assertEquals(20, $cart_after_promo['items'][0]['discount_setup']);
        $this->assertEquals(20, $cart_after_promo['items'][0]['discount']);
        
        $result = $this->api_client->cart_checkout();
        $order = $this->api_client->order_get(array('id'=>$result['order_id']));
        $this->assertEquals(2, $order['promo_id']);
        
        $invoice = $this->api_client->invoice_get(array('hash'=>$result['invoice_hash']));
        
        $this->assertEquals(50, $invoice['total']); // normal price
        $this->assertEquals(50, $invoice['lines'][0]['price']); // normal price
        $this->assertEquals(0, $invoice['lines'][1]['price']); // setup price
    }
    
    public function testApplyPromo()
    {
        $this->api_guest->cart_reset();

        // test custom products
        $data = array(
            'id'       =>  1,
            'type'      =>  'custom',
            'period'    =>  '1M',
            'quantity'  =>  1,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();
        $data = array('promocode'=>'PHPUNIT');
        $bool = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertTrue($cart_before_promo['total'] != $cart_after_promo['total'], 'Could not apply promo to cart');

        $bool = $this->api_guest->cart_remove_promo($data);
        $this->assertTrue($bool);
    }

    public function testRemoveItem()
    {
        $cart = $this->api_guest->cart_get();
        foreach($cart['items'] as $item) {
            $bool = $this->api_guest->cart_remove_item(array('id'=>$item['id']));
            $this->assertTrue($bool);
        }
    }

    public function testRemoveItemWithAddons()
    {
        $this->api_guest->cart_reset();

        $data = array(
            'id'        => 1,
            'multiple' => true
        );
        $bool = $this->api_guest->cart_add_item($data);
        $cart = $this->api_guest->cart_get();

        $this->assertEquals(1, count($cart['items']));


        $data = array(
            'id'        => 2,
            'multiple' => true,
            'addons'    => array(
                3  =>  array(
                    'selected'  =>  1,
                    'period'    =>  '1Y',
                    'quantity'  =>  2,
                ),
                4  =>  array(
                    'selected'  =>  1,
                    'period'    =>  '1Y',
                ),
            ),
        );


        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();

        $this->assertEquals(4, count($cart['items']));

        $this->api_guest->cart_remove_item(array('id'=> $cart['items'][1]['id'])); //removing second item from cart. Should remove it's addons as well
        $cart = $this->api_guest->cart_get();

        $this->assertEquals(1, count($cart['items']));

    }
    
    public function testMultiple()
    {
        $res = $this->api_admin->invoice_get_list();
        $this->assertEquals(0, $res['total'], 'Invoice exists in fixture?');
        
        $this->api_guest->cart_reset();
        
        $data = array(
            'id'       =>  1,
            'type'      =>  'custom',
            'period'    =>  '1M',
            'quantity'  =>  2,
            'multiple'  =>  true,
        );
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
        
        $cart = $this->api_guest->cart_get();
        $this->assertEquals(2, count($cart['items']));
        
        $res = $this->api_client->cart_checkout();
        
        //one invoice for two products after checkout
        $res = $this->api_admin->invoice_get_list();
        $this->assertEquals(1, $res['total'], 'Generated more than one invoice');
    }


    public function testApplyPromoForClientProvider()
    {
        $ids = array(1, 2);

        return array(
            array(50, 5, $ids, true), //should throw exception because client group does not match ones set in Promo
            array(50, 2, $ids, false),//Client group ID is same as set for Promo, su discount must be applied
            array(50, 5, array(), false),//Promo does not have any client group set, so it is valid for any client and should apply discount
            array(50, null, array(), false),//Client group ID is not set and there are no group IDs in Promo, should apply discount
            array(50, null, $ids, true),//Client group ID is not set however there are group IDs in Promo, should throw an exception
        );
    }

    /**
     * @dataProvider testApplyPromoForClientProvider
     */
    public function testApplyPromoForClient($discount, $clientGroupId, $ids, $shouldThrowException)
    {
        if ($shouldThrowException) {
            $this->expectException(\Box_Exception::class);
        }
        $this->api_guest->cart_reset();

        $data = array(
            'code'          => '50OFF',
            'type'          => 'percentage',
            'value'         => $discount,
            'active'        => 1,
            'client_groups' => $ids
        );
        $id   = $this->api_admin->product_promo_create($data);
        $this->assertTrue(is_numeric($id));

        $client                  = $this->di['loggedin_client'];
        $client->client_group_id = $clientGroupId;

        $data = array(
            'id'       => 1,
            'type'     => 'custom',
            'period'   => '1M',
            'quantity' => 1,
        );

        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();
        $data              = array('promocode' => '50OFF');
        $bool              = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertTrue($cart_before_promo['total'] - ($cart_before_promo['total'] * $discount / 100) == $cart_after_promo['total'], 'Could not apply promo to cart');

    }

}