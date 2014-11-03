<?php
/**
 * @group Core
 */
class Box_Mod_Extension_ServiceTest extends BBDbApiTestCase
{
    public function testEvents()
    {
        $event = $this->_bb_event = new Box_Event(null, 'name', array(), $this->api_admin, $this->api_guest);
        $service = new \Box\Mod\Extension\Service();
        $bool = $service->onBeforeAdminCronRun($event);
        $this->assertTrue($bool);
    }

}