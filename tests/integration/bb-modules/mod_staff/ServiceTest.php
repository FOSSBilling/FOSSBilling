<?php
/**
 * @group Core
 */
class Box_Mod_Staff_ServiceTest extends ApiTestCase
{
    public function testEvents()
    {
        $params = array(
            'id' => 1,
        );
        $event = $this->_bb_event = new Box_Event(null, 'name', $params, $this->api_admin, $this->api_guest);
        $event->setDi($this->di);
        $service = new \Box\Mod\Staff\Service();
        $bool = $service->onAfterClientSignUp($event);
        $this->assertTrue($bool);
    }

}