<?php
class Box_Mod_Servicedomain_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'servicedomain';
    protected $_initialSeedFile = 'mod_servicedomain.xml';
    
    public function testSync()
    {
        $bool = $this->api_admin->servicedomain_batch_sync_expiration_dates();
        $this->assertTrue($bool);
    }
}