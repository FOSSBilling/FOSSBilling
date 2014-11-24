<?php
/**
 * @group Core
 */
class Payment_Adapter_TwoCheckoutTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'gateway_TwoCheckout.xml';
    
    public function testGateway()
    {
        $config = array(
            'vendor_nr'     =>  'test',
            'secret'        =>  'test',
        );
        $adapter = new Payment_Adapter_TwoCheckout($config);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1);
        $adapter->getHtml($this->api_admin, 1, true);
        $adapter->processTransaction($this->api_admin, 1);
    }
}