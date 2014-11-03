<?php

/**
 * @group Core
 */
class Box_Mod_Notification_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'notification';
    protected $_initialSeedFile = 'mod_notification.xml';

    public function testActions()
    {
        $this->api_admin->extension_activate(array('id' => 'notification', 'type' => 'mod'));

        $int = $this->api_admin->notification_add(array('message' => 'Test message'));
        $this->assertInternalType('int', $int);

        $array = $this->api_admin->notification_get(array('id' => $int));
        $this->assertInternalType('array', $array);

        $array = $this->api_admin->notification_get_list();
        $this->assertInternalType('array', $array);
        $this->assertEquals(1, $array['total']);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $item = $list[0];
        $this->assertInternalType('array', $item);
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('client_id', $item);
        $this->assertArrayHasKey('extension', $item);
        $this->assertArrayHasKey('rel_type', $item);
        $this->assertArrayHasKey('rel_id', $item);
        $this->assertArrayHasKey('meta_key', $item);
        $this->assertArrayHasKey('meta_value', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);


        $bool = $this->api_admin->notification_delete(array('id' => $array['list'][0]['id']));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->notification_delete_all();
        $this->assertTrue($bool);
    }
}