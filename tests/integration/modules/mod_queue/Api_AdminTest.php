<?php
/**
 * @group Core
 */
class Box_Mod_Queue_Api_AdminTest extends BBModTestCase
{
    protected $_mod = 'queue';
    protected $_initialSeedFile = 'mod_queue.xml';
    
    public function testQueue()
    {
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit', 'mod'=>'queue', 'handler'=>'dummy', 'params'=>'value'));
        $this->assertIsInt($int);
        
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit', 'mod'=>'queue', 'handler'=>'dummy', 'params'=>'value2'));
        $this->assertIsInt($int);
        
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit2', 'mod'=>'queue', 'handler'=>'dummy', 'params'=>array('value2', 'lll'=>'ss')));
        $this->assertIsInt($int);
        
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit2', 'mod'=>'queue', 'handler'=>'dummy', 'params'=>array('value2')));
        $this->assertIsInt($int);
        
        $bool = $this->api_admin->queue_message_delete(array('id'=>$int));
        $this->assertTrue($bool);
        
        $array = $this->api_admin->queue_get(array('queue'=>'phpunit2'));
        $this->assertIsArray($array);
        $this->assertTrue(isset($array['messages_count']));
        $this->assertEquals(1, $array['messages_count']);
        
        $array = $this->api_admin->queue_get_list();
        $this->assertIsArray($array);
        $this->assertEquals(2, $array['total']);
        
        $bool = $this->api_admin->queue_execute(array('queue'=>'phpunit'));
        $this->assertTrue($bool);
    }
    
    public function testLater()
    {
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit', 'mod'=>'queue', 'handler'=>'dummy', 'params'=>'right now'));
        $this->assertIsInt($int);
        
        $int = $this->api_admin->queue_message_add(array('queue'=>'phpunit', 'mod'=>'queue', 'handler'=>'dummy', 'execute_at'=>date('Y-m-d H:i:s', strtotime('+10 seconds')), 'params'=>'later'));
        $this->assertIsInt($int);
        
        $bool = $this->api_admin->queue_execute(array('queue'=>'phpunit'));
        $this->assertTrue($bool);
    }

    public function testQueueGetList()
    {
        $int = $this->api_admin->queue_message_add(array('queue' => 'phpunit', 'mod' => 'queue', 'handler' => 'dummy', 'params' => 'right now'));
        $this->assertIsInt($int);

        $array = $this->api_admin->queue_get_list();
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['total']);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        $item = $list[0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('module', $item);
        $this->assertArrayHasKey('timeout', $item);
        $this->assertArrayHasKey('iteration', $item);
        $this->assertArrayHasKey('timeout', $item);
        $this->assertArrayHasKey('created_at', $item);
        $this->assertArrayHasKey('updated_at', $item);
        $this->assertArrayHasKey('messages_count', $item);
    }
}