<?php
/**
 * @group Core
 */
class Box_Mod_Hook_Api_AdminTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_hook.xml';

    public function testHook()
    {
        $this->assertTrue($this->api_admin->hook_batch_connect());
        $this->assertFalse($this->api_admin->hook_call());
        $bool = $this->api_admin->hook_call(array('event'=>'onAfterAdminActivateExtension'));
        $bool = $this->api_admin->hook_call(array('event'=>'onAfterAdminActivateExtension', 'params'=>array('id'=>'2')));

        $this->assertTrue($bool);
    }

    public function testEventReturnData()
    {
        $this->api_admin->hook_batch_connect();
        $data = $this->api_admin->hook_call(array('event'=>'onBeforeGuestPublicTicketOpen', 'params'=>array('message'=>'msg')));

        $this->assertTrue(true);
    }

    public function testHookGetList()
    {
        $list = $this->api_admin->hook_get_list();
        $this->assertIsArray($list);
        $this->assertArrayHasKey('list', $list);
        $this->assertEquals(count($list['list']), $list['total']);
    }
}
