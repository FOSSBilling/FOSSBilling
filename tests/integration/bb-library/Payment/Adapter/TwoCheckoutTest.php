<?php

/**
 * @group Core
 */
class Payment_Adapter_TwoCheckoutTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'gateway_TwoCheckout.xml';

    public function testGateway()
    {
        $config  = array(
            'vendor_nr'    => 'test',
            'secret'       => 'test',
            'test_mode'    => true,
            'redirect_url' => 'http://www.google.com?q=redirect',
            'single_page'  => true,
            'subscription' =>true,

        );
        $data    = array(
            'get'  => array(),
            'post' => array(
                'message_type' => 'ORDER_CREATED',
                'subscription' =>true,
                'order_number' => 1,
                'total' => 500
            )
        );
        $adapter = new Payment_Adapter_TwoCheckout($config);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1, false);
        $adapter->processTransaction($this->api_admin, 1, $data, 1);
    }
}