<?php
/**
 * @group Core
 */
class Payment_Adapter_CustomTest extends BBDbApiTestCase
{
    public function testCustom()
    {
        $config = array(
            'single'        =>  'test',
            'recurrent'     =>  'test',
        );
        $adapter = new Payment_Adapter_Custom($config);
        $adapter->getConfig();
        $adapter->getHtml($this->api_admin, 1);
        $adapter->processTransaction();
    }
}