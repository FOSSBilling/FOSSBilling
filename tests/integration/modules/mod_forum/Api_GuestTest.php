<?php
/**
 * @group Core
 */
class Api_Guest_ForumTest extends BBDbApiTestCase
{

    protected $_initialSeedFile = 'mod_forum.xml';


    public function testForum()
    {
        $data = array(
            'page'      => 1,
            'per_page'  => 10,
        );
        $array = $this->api_guest->forum_get_list($data);
        $this->assertIsArray($array);

        $array = $this->api_guest->forum_get_categories($data);
        $this->assertIsArray($array);

        $data = array(
            'id'    => 1,
        );
        $array = $this->api_guest->forum_get($data);
        $this->assertIsArray($array);

        $data = array(
            'slug'    => 'discuss-about-everything',
        );
        $array = $this->api_guest->forum_get($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_id'    => 1,
        );
        $array = $this->api_guest->forum_get_topic_list($data);
        $this->assertIsArray($array);

        $data = array(
            'id'    => 1,
        );
        $array = $this->api_guest->forum_get_topic($data);
        $this->assertIsArray($array);

        $data = array(
            'slug'    => 'read-before-posting',
        );
        $array = $this->api_guest->forum_get_topic($data);
        $this->assertIsArray($array);

        $data = array(
            'forum_topic_id'    => 2,
        );
        $array = $this->api_guest->forum_get_topic_message_list($data);
        $this->assertIsArray($array);

        $array = $this->api_guest->forum_members_list($data);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $this->assertIsArray($array['list']);

        $data = array(
            'q' => 'BoxBilling'
        );
        $array = $this->api_guest->forum_search($data);
        $this->assertIsArray($array);
        $this->assertArrayHasKey('list', $array);
        $this->assertIsArray($array['list']);
    }

    public function testForumGetList()
    {
        $data  = array(
            'page'     => 1,
            'per_page' => 10,
        );
        $array = $this->api_guest->forum_get_list($data);
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