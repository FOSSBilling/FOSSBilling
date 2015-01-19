<?php
/**
 * @group Core
 */
class Payment_Adapter_WebMoneyTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'transactions.xml';

    public function testGateway()
    {
        $config  = array(
            'purse'       => 'test',
            'return_url' => 'http://www.google.com?q=success',
            'cancel_url'  => 'http://www.google.com?q=cancel',
            'notify_url'  => 'http://www.google.com?q=notify',
        );
        $adapter = new Payment_Adapter_WebMoney($config);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1);

        $data['post'] = array(
            'INVOICE_ID'            =>  '1',
            'LMI_PAYMENT_AMOUNT'    =>  '10',
            'LMI_SYS_TRANS_NO'   => '1',
            'LMI_PAYER_PURSE'    => 'WMZ'
        );
        $adapter->setDi($this->di);
        $adapter->processTransaction($this->api_admin, 1, $data, 4);
    }
}