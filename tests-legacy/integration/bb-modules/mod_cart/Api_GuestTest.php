<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Cart_Api_GuestTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_cart.xml';

    public function testGet(): void
    {
        $cart = $this->api_guest->cart_get();
        $this->assertIsArray($cart);
    }

    public function testCurrency(): void
    {
        $data = ['currency' => 'USD'];
        $bool = $this->api_guest->cart_set_currency($data);
        $this->assertTrue($bool);

        $c = $this->api_guest->cart_get_currency();
        $this->assertIsArray($c);
    }

    public function testReset(): void
    {
        $bool = $this->api_guest->cart_reset();
        $this->assertTrue($bool);
    }

    public function testAddCustomProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 1,
        ];
        $bool = $this->api_guest->cart_add_item($data);

        $this->assertTrue($bool);
    }

    public function testAddAddons(): void
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = [
            'id' => $pid,
            'addons' => [
                5 => [
                    'selected' => 1,
                    'period' => '1Y',
                    'quantity' => 2,
                ],
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();

        // main product must be first in cart
        $this->assertEquals($pid, $cart['items'][0]['product_id']);

        $this->assertEquals(2, count($cart['items']));
        $this->assertEquals(2, $cart['items'][1]['quantity']);
    }

    public function testAddAddonsAddonPeriodNotEnabled(): void
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = [
            'id' => $pid,
            'addons' => [
                5 => [
                    'selected' => 1,
                    'period' => '1W',
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Selected billing period is invalid for the selected add-on');

        $bool = $this->api_guest->cart_add_item($data);
    }

    public function testAddAddonsMissingPeriodParameter(): void
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = [
            'id' => $pid,
            'addons' => [
                5 => [
                    'selected' => 1,
                    'quantity' => 2,
                ],
            ],
        ];

        $this->expectException(FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Addon period parameter not passed');

        $bool = $this->api_guest->cart_add_item($data);
    }

    public function testAddAddonsAddonNotFoundById(): void
    {
        $this->api_guest->cart_reset();
        $pid = 1;
        $data = [
            'id' => $pid,
            'addons' => [
                31 => [
                    'selected' => 1,
                    'quantity' => 2,
                ],
            ],
        ];
        $return = $this->api_guest->cart_add_item($data);

        $this->assertTrue($return);
    }

    /**
     * @expectedException \FOSSBilling\Exception
     */
    public function testAddDisabledPeriod(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 17,
            'period' => '1W',
        ];
        $this->api_guest->cart_add_item($data);
    }

    public function testAddDomainRegisterProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 10,
            'action' => 'register',
            'register_tld' => '.com',
            'register_sld' => 'mytestdomain',
            'register_years' => 5,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddDomainTransferProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 10,
            'action' => 'transfer',
            'transfer_tld' => '.com',
            'transfer_sld' => 'domaintotransfer',
            'transfer_code' => 5,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $item = $cart['items'][0];
        $this->assertEquals('transfer', $item['action']);
        $this->assertEquals('year', $item['unit']);
    }

    public function testAddLicenseProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 6,
            'period' => '3M',
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddDownloadableProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 7,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    /* @TODO: Handle tests for external modules better
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
    */

    public function testAddResellerHostingProduct(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 9,
            'period' => '1Y',
            'domain' => [
                'action' => 'register',
                'register_years' => 2,
                'register_tld' => '.com',
                'register_sld' => 'resellerdomain',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddHostingProductWithDomainRegistration(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 8,
            'period' => '3M',
            'domain' => [
                'action' => 'register',
                'register_years' => 2,
                'register_tld' => '.com',
                'register_sld' => 'example',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 2);
    }

    public function testAddHostingProductWithFreeDomainRegistration(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 11,
            'period' => '3M',
            'domain' => [
                'action' => 'register',
                'register_years' => 2,
                'register_tld' => '.com',
                'register_sld' => 'example',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue($cart['items'][1]['free_domain']);
        $this->assertEquals(10, $cart['items'][1]['discount'], 'Could not set free domain for hosting product');
    }

    public function testAddHostingProductWithFreeDomainTransfer(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 11,
            'period' => '3M',
            'domain' => [
                'action' => 'transfer',
                'transfer_code' => '123',
                'transfer_tld' => '.com',
                'transfer_sld' => 'example',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue($cart['items'][1]['free_domain']);
        $this->assertEquals(15, $cart['items'][1]['discount'], 'Could not set free transfer for hosting product');
    }

    public function testAddHostingProductWithDomainTransfer(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 8,
            'period' => '3M',
            'domain' => [
                'action' => 'transfer',
                'transfer_code' => 2,
                'transfer_tld' => '.com',
                'transfer_sld' => 'example',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 2);
    }

    public function testAddHostingProductWithOwnDomainWhenExists(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 8,
            'period' => '3M',
            'domain' => [
                'action' => 'owndomain',
                'owndomain_tld' => '.com',
                'owndomain_sld' => 'exist',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);
    }

    public function testAddHostingProductWithOwnDomain(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 8,
            'period' => '3M',
            'domain' => [
                'action' => 'owndomain',
                'owndomain_tld' => '.com',
                'owndomain_sld' => 'example',
            ],
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertTrue(count($cart['items']) == 1);
    }

    public function testFreeSetupPromo(): void
    {
        $this->api_admin->currency_set_default(['code' => 'USD']);
        $this->api_guest->cart_reset();
        $this->api_guest->cart_set_currency(['currency' => 'USD']);

        // test custom products
        $data = [
            'id' => 13,
            'quantity' => 1,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();
        $this->assertNull($cart_before_promo['promocode']);

        $data = ['promocode' => 'FREE_SETUP'];
        $bool = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertNotNull($cart_after_promo['promocode']);
        $this->assertEquals(0, $cart_after_promo['items'][0]['discount_price']);
        $this->assertEquals(20, $cart_after_promo['items'][0]['discount_setup']);
        $this->assertEquals(20, $cart_after_promo['items'][0]['discount']);

        $result = $this->api_client->cart_checkout();
        $order = $this->api_client->order_get(['id' => $result['order_id']]);
        $this->assertEquals(2, $order['promo_id']);

        $invoice = $this->api_client->invoice_get(['hash' => $result['invoice_hash']]);

        $this->assertEquals(50, $invoice['total']); // normal price
        $this->assertEquals(50, $invoice['lines'][0]['price']); // normal price
        $this->assertEquals(0, $invoice['lines'][1]['price']); // setup price
    }

    public function testApplyPromo(): void
    {
        $this->api_guest->cart_reset();

        // test custom products
        $data = [
            'id' => 1,
            'type' => 'custom',
            'period' => '1M',
            'quantity' => 1,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();
        $data = ['promocode' => 'PHPUNIT'];
        $bool = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertTrue($cart_before_promo['total'] != $cart_after_promo['total'], 'Could not apply promo to cart');

        $bool = $this->api_guest->cart_remove_promo($data);
        $this->assertTrue($bool);
    }

    public function testRemoveItem(): void
    {
        $cart = $this->api_guest->cart_get();
        foreach ($cart['items'] as $item) {
            $bool = $this->api_guest->cart_remove_item(['id' => $item['id']]);
            $this->assertTrue($bool);
        }
    }

    public function testRemoveItemWithAddons(): void
    {
        $this->api_guest->cart_reset();

        $data = [
            'id' => 1,
            'multiple' => true,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $cart = $this->api_guest->cart_get();

        $this->assertEquals(1, count($cart['items']));

        $data = [
            'id' => 2,
            'multiple' => true,
            'addons' => [
                3 => [
                    'selected' => 1,
                    'period' => '1Y',
                    'quantity' => 2,
                ],
                4 => [
                    'selected' => 1,
                    'period' => '1Y',
                ],
            ],
        ];

        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();

        $this->assertEquals(4, count($cart['items']));

        $this->api_guest->cart_remove_item(['id' => $cart['items'][1]['id']]); // removing second item from cart. Should remove its addons as well
        $cart = $this->api_guest->cart_get();

        $this->assertEquals(1, count($cart['items']));
    }

    public function testMultiple(): void
    {
        $res = $this->api_admin->invoice_get_list();
        $this->assertEquals(0, $res['total'], 'Invoice exists in fixture?');

        $this->api_guest->cart_reset();

        $data = [
            'id' => 1,
            'type' => 'custom',
            'period' => '1M',
            'quantity' => 2,
            'multiple' => true,
        ];
        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart = $this->api_guest->cart_get();
        $this->assertEquals(2, count($cart['items']));

        $res = $this->api_client->cart_checkout();

        // one invoice for two products after checkout
        $res = $this->api_admin->invoice_get_list();
        $this->assertEquals(1, $res['total'], 'Generated more than one invoice');
    }

    public static function testApplyPromoForClientProvider()
    {
        $ids = [1, 2];

        return [
            [50, 5, $ids, true], // should throw exception because client group does not match ones set in Promo
            [50, 2, $ids, false], // Client group ID is same as set for Promo, su discount must be applied
            [50, 5, [], false], // Promo does not have any client group set, so it is valid for any client and should apply discount
            [50, null, [], false], // Client group ID is not set and there are no group IDs in Promo, should apply discount
            [50, null, $ids, true], // Client group ID is not set however there are group IDs in Promo, should throw an exception
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('testApplyPromoForClientProvider')]
    public function testApplyPromoForClient($discount, $clientGroupId, $ids, $shouldThrowException): void
    {
        if ($shouldThrowException) {
            $this->expectException(FOSSBilling\Exception::class);
        }
        $this->api_guest->cart_reset();

        $data = [
            'code' => '50OFF',
            'type' => 'percentage',
            'value' => $discount,
            'active' => 1,
            'client_groups' => $ids,
        ];
        $id = $this->api_admin->product_promo_create($data);
        $this->assertTrue(is_numeric($id));

        $client = $this->di['loggedin_client'];
        $client->client_group_id = $clientGroupId;

        $data = [
            'id' => 1,
            'type' => 'custom',
            'period' => '1M',
            'quantity' => 1,
        ];

        $bool = $this->api_guest->cart_add_item($data);
        $this->assertTrue($bool);

        $cart_before_promo = $this->api_guest->cart_get();
        $data = ['promocode' => '50OFF'];
        $bool = $this->api_guest->cart_apply_promo($data);
        $this->assertTrue($bool);

        $cart_after_promo = $this->api_guest->cart_get();
        $this->assertTrue($cart_after_promo['total'] == $cart_before_promo['total'] - ($cart_before_promo['total'] * $discount / 100), 'Could not apply promo to cart');
    }
}
