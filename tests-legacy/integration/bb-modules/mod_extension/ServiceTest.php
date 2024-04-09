<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Extension_ServiceTest extends BBDbApiTestCase
{
    public function testEvents(): void
    {
        $event = $this->_bb_event = new Box_Event(null, 'name', [], $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $service = new Box\Mod\Extension\Service();
        $bool = $service->onBeforeAdminCronRun($event);
        $this->assertTrue($bool);
    }
}
