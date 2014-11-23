<?php
/**
 * @group Core
 */
class Payment_Adapter_CustomTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'gateway_Custom.xml';
    
    public function testCustom()
    {
        $config = array(
            'single'        =>  'test',
            'recurrent'     =>  'test',
        );
        $adapter = new Payment_Adapter_Custom($config);
        $adapter->setDi($this->di);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1);
        $adapter->process();
    }
}