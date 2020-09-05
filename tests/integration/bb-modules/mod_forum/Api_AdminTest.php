<?php

/**
 * @group Core
 */
class Api_Admin_ForumTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'mod_forum.xml';

    public function testProfile()
    {
        $data  = array(
            'client_id' => 1,
        );
        $array = $this->api_admin->forum_profile_get($data);
        $this->assertIsArray($array);
    }

    public function testForum()
    {
        $data = array(
            'page'     => 1,
            'per_page' => 10,
        );

        $array = $this->api_admin->forum_get_categories($data);
        $this->assertIsArray($array);

        $array = $this->api_admin->forum_get_pairs($data);
        $this->assertIsArray($array);

        $data = array(
            'title' => 'New forum as',
        );
        $id   = $this->api_admin->forum_create($data);
        $this->assertTrue(is_numeric($id));

        $data  = array(
            'id' => $id,
        );
        $array = $this->api_admin->forum_get($data);
        $this->assertIsArray($array);

        $data['title']       = 'new forum title';
        $data['description'] = 'new forum desc';
        $bool                = $this->api_admin->forum_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->forum_delete($data);
        $this->assertTrue($bool);

        $data['priority'] = array(
            1 => 10
        );
        $bool             = $this->api_admin->forum_update_priority($data);
        $this->assertTrue($bool);
    }

    public function testForumTopic()
    {
        $data = array(
            'forum_id' => '1',
            'title'    => 'phpunit topic' . uniqid(),
            'message'  => 'message in this topic',
        );
        $id   = $this->api_admin->forum_topic_create($data);
        $this->assertIsInt($id);

        $data  = array(
            'id' => $id,
        );
        $array = $this->api_admin->forum_topic_get($data);
        $this->assertIsArray($array);

        $data  = array(
            'id' => $id,
        );
        $array = $this->api_admin->forum_topic_get($data);
        $this->assertIsArray($array);

        $data['title'] = 'new title';
        $bool          = $this->api_admin->forum_topic_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->forum_topic_delete($data);
        $this->assertTrue($bool);
    }

    public function testForumTopicMessage()
    {
        $data  = array(
            'forum_topic_id' => 1,
        );
        $array = $this->api_admin->forum_message_get_list($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_topic_id' => 1,
            'message'        => 'this is my reply message',
        );
        $int  = $this->api_admin->forum_message_create($data);
        $this->assertIsInt($int);

        $array = $this->api_admin->forum_message_get(array('id' => $int));
        $this->assertIsArray($array);

        $data['id']      = $int;
        $data['message'] = 'updated message';
        $bool            = $this->api_admin->forum_message_update($data);
        $this->assertTrue($bool);

        $data = array(
            'forum_topic_id' => 1,
            'message'        => 'this is my second message',
        );
        $int  = $this->api_admin->forum_message_create($data);
        $this->assertIsInt($int);

        $data['id'] = $int;
        $bool       = $this->api_admin->forum_message_delete($data);
        $this->assertTrue($bool);

    }

    public function testPoints()
    {

        $this->api_admin->hook_batch_connect();

        //enable points system
        $config = array(
            'ext'                 => 'mod_forum',
            'forum_points_enable' => true,
            'points'              => 1,
            'post_length'         => 5,
            'points_forums'       => array(1),
        );
        $bool   = $this->api_admin->extension_config_save($config);
        $this->assertTrue($bool);


        $client_id = 1;
        $data      = array('client_id' => $client_id);
        $profile   = $this->api_admin->forum_profile_get($data);
        $this->assertEquals(0, $profile['points']);

        $data['amount'] = 15.63;
        $bool           = $this->api_admin->forum_points_update($data);
        $this->assertTrue($bool);

        $profile = $this->api_admin->forum_profile_get($data);
        $this->assertEquals(15.63, $profile['points']);

        $data['amount'] = 0;
        $bool           = $this->api_admin->forum_points_update($data);
        $this->assertTrue($bool);

        $profile = $this->api_admin->forum_profile_get($data);
        $this->assertEquals(0, $profile['points']);

        $post = array(
            'forum_topic_id' => 1,
            'message'        => 'this is my reply message',
        );
        $int  = $this->api_client->forum_post_message($post);
        $this->assertIsInt($int);

        $profile = $this->api_admin->forum_profile_get($data);
        $this->assertEquals(1, $profile['points']);

        $profile = $this->api_admin->forum_profile_get($data);
        $this->assertEquals(1, $profile['points']);

        $post = array(
            'forum_topic_id' => 1,
            'message'        => 'this is my reply message',
        );
        $int  = $this->api_client->forum_post_message($post);
        $this->assertIsInt($int);
        $this->api_admin->forum_points_deduct(array('id' => $int));
    }

    public function testForumGetList()
    {
        $data  = array(
            'page'     => 1,
            'per_page' => 10,
        );
        $array = $this->api_admin->forum_get_list($data);
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

    public function testForumTopicGetList()
    {
        $data  = array(
            'forum_id' => 1,
        );
        $array = $this->api_admin->forum_topic_get_list($data);
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);
        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('sticky', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('forum', $item);
            $forum = $item['forum'];
            $this->assertIsArray($forum);
            $this->assertArrayHasKey('id', $forum);
            $this->assertArrayHasKey('slug', $forum);
            $this->assertArrayHasKey('title', $forum);
            $this->assertArrayHasKey('category', $forum);
            $this->assertArrayHasKey('stats', $item);
            $stats = $item['stats'];
            $this->assertIsArray($stats);
            $this->assertArrayHasKey('posts_count', $stats);
            $this->assertArrayHasKey('views_count', $stats);
        }
    }
}