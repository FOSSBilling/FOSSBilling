<?php
/**
 * @group Core
 */
class Api_AdminTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'settings.xml';

    public function testActivity()
    {
        $bool = $this->api_admin->activity_log_delete(array('id'=>1));
        $this->assertTrue($bool);
        
        $bool = $this->api_admin->activity_log();
        $this->assertFalse($bool);
        
        $bool = $this->api_admin->activity_log(array('m'=>'this is test message to log'));
        $this->assertTrue($bool);

        $bool = $this->api_admin->activity_log_email(array('subject' => 'This is an email subject'));
        $this->assertTrue($bool);
    }

    public function testLogDeleteIdNotSetException()
    {
        $this->expectException(\Box_Exception::class);

        $this->api_admin->activity_log_delete(array());
    }

    public function testLogNotFoundException()
    {
        $this->expectException(\Box_Exception::class);
        $this->api_admin->activity_log_delete(array('id' => 100));
    }

    public function testActivityLogGetList()
    {
        $array = $this->api_admin->activity_log_get_list();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $this->assertArrayHasKey('pages', $array);
        $this->assertArrayHasKey('page', $array);
        $this->assertArrayHasKey('per_page', $array);
        $this->assertArrayHasKey('total', $array);
        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('priority', $item);
            $this->assertArrayHasKey('admin_id', $item);
            $this->assertArrayHasKey('client_id', $item);
            $this->assertArrayHasKey('message', $item);
            $this->assertArrayHasKey('ip', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
        }
    }

    public function testBatchDelete()
    {
        $array = $this->api_admin->activity_log_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->activity_batch_delete(array('ids' => $ids));
        $array  = $this->api_admin->activity_log_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }
}