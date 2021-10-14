<?php

/**
 * @group Core
 */
class Api_Guest_NewsTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'extension_news.xml';

    public function testNews()
    {
        $data  = array(
            'page'     => 1,
            'per_page' => 1,
        );
        $array = $this->api_guest->news_get_list($data);
        $this->assertIsArray($array);

        $data  = array('id' => 1);
        $array = $this->api_guest->news_get($data);
        $this->assertIsArray($array);

        $data  = array('slug' => 'boxbilling-is-customizable');
        $array = $this->api_guest->news_get($data);
        $this->assertIsArray($array);
    }

    public function testNewsgetList()
    {
        $array = $this->api_admin->news_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('content', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('image', $item);
            $this->assertArrayHasKey('section', $item);
            $this->assertArrayHasKey('publish_at', $item);
            $this->assertArrayHasKey('published_at', $item);
            $this->assertArrayHasKey('expires_at', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('excerpt', $item);
            $this->assertArrayHasKey('author', $item);

            $author = $item['author'];
            $this->assertIsArray($author);

            $this->assertArrayHasKey('name', $author);
            $this->assertArrayHasKey('email', $author);
        }

    }
}