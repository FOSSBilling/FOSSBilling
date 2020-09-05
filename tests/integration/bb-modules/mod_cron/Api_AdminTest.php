<?php
/**
 * @group Core
 */
class Api_AdminTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';

    public function testRun()
    {
        $array = $this->api_system->cron_info();
        $this->assertIsArray($array);
        
        $bool = $this->api_system->cron_run();
        $this->assertTrue($bool);
    }
}