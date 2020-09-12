<?php
/**
 * @group Core
 */
class Api_Client_ForumTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_forum.xml';
    
    public function testFavorites()
    {
        $array = $this->api_client->forum_favorites();
        $this->assertIsArray($array);
        $this->assertEquals(0, count($array));
        
        $data = array(
            'id'    =>  1,
        );
        
        $bool = $this->api_client->forum_favorite_add($data);
        $this->assertTrue($bool);
        
        $array = $this->api_client->forum_favorites();
        $this->assertEquals(1, count($array));
        
        $bool = $this->api_client->forum_is_favorite($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->forum_favorite_remove($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->forum_is_favorite($data);
        $this->assertFalse($bool);
    }
    
    public function testSubscriptions()
    {
        $data['id'] = 1;
        $bool = $this->api_client->forum_subscribe($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->forum_is_subscribed($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->forum_unsubscribe($data);
        $this->assertTrue($bool);
        
        $bool = $this->api_client->forum_is_subscribed($data);
        $this->assertFalse($bool);
    }
    
    public function testNotification()
    {
        $bool = $this->api_client->forum_subscribe(array('id'=>1));
        $this->assertTrue($bool);
        
        $data = array(
            'forum_topic_id'    => 1,
            'message'    => 'this is my reply message',
        );
        $int = $this->api_client->forum_post_message($data);
        $this->assertIsInt($int);
    }
    
    public function testForum()
    {
        $data = array(
            'page'      => 1,
            'per_page'  => 10,
        );
        $array = $this->api_client->forum_get_list($data);
        $this->assertIsArray($array);
        
        $array = $this->api_client->forum_get_categories($data);
        $this->assertIsArray($array);

        $data = array(
            'id'    => 1,
        );
        $array = $this->api_client->forum_get($data);
        $this->assertIsArray($array);

        $data = array(
            'slug'    => 'discuss-about-everything',
        );
        $array = $this->api_client->forum_get($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_id'  => '1',
            'topic'     => 'phpunit topic' . uniqid(),
            'message'   => 'message in this topic',
        );
        $int = $this->api_client->forum_start_topic($data);
        $this->assertIsInt($int);

        $data = array(
            'forum_id'    => 1,
        );
        $array = $this->api_client->forum_get_topic_list($data);
        $this->assertIsArray($array);

        $data = array(
            'id'    => 1,
        );
        $array = $this->api_client->forum_get_topic($data);
        $this->assertIsArray($array);

        $data = array(
            'slug'    => 'read-before-posting',
        );
        $array = $this->api_client->forum_get_topic($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_topic_id'    => 2,
        );
        $array = $this->api_client->forum_get_topic_message_list($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_topic_id'    => 1,
            'message'    => 'this is my reply message',
        );
        $int = $this->api_client->forum_post_message($data);
        $this->assertIsInt($int);
    }

    public function testProfile()
    {
        $int = $this->api_client->forum_profile();
        $this->assertIsArray($int);
    }

    protected function _callOnService($method, $data)
    {
        $m = "forum_".$method;
        return $this->api_guest->{$m}($data);
    }

    public function testForumGetList()
    {
        $data  = array(
            'page'     => 1,
            'per_page' => 10,
        );
        $array = $this->api_client->forum_get_list($data);
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('category', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('priority', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('stats', $item);
            $stats = $item['stats'];
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('topics_count', $stats);
            $this->assertArrayHasKey('posts_count', $stats);
            $this->assertArrayHasKey('views_count', $stats);
        }
    }

}