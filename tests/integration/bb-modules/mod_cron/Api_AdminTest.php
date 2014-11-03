<?php
/**
 * @group Core
 */
class Api_Admin_CronTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'orders.xml';

    public function testRun()
    {
        $array = $this->api_admin->cron_info();
        $this->assertInternalType('array', $array);
        
        $bool = $this->api_admin->cron_run();
        $this->assertTrue($bool);
    }
}