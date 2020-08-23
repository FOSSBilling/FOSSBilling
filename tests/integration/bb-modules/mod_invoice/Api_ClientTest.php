<?php
/**
 * @group Core
 */
class Box_Mod_Invoice_Api_ClientTest extends BBModTestCase
{
    protected $_mod = 'invoice';
    protected $_initialSeedFile = 'mod_invoice.xml';
    
    public function testUpdate()
    {
        $data = array(
            'hash'          =>  'hash2',
        );
        $array = $this->api_client->invoice_get($data);
        $this->assertIsArray($array);
        
        $data['gateway_id'] = 1;
        $bool = $this->api_client->invoice_update($data);
        $this->assertTrue($bool);
        
        $array = $this->api_client->invoice_get($data);
        $this->assertEquals(1, $array['gateway_id']);
    }
}