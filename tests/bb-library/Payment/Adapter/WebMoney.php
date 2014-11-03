<?php
/**
 * @group Core
 */
class Payment_Adapter_WebMoneyTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'transactions.xml';
    
    public function testGateway()
    {
        $config = array(
            'purse'        =>  'test',
        );
        $adapter = new Payment_Adapter_WebMoney($config);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1);
        
        $data['post'] = array(
            'INVOICE_ID'            =>  '1',
            'LMI_PAYMENT_AMOUNT'    =>  '10',
        );
        $adapter->processTransaction($this->api_admin, 1, $data);
    }
}