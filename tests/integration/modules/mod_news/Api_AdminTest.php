<?php
/**
 * @group Core
 */
class Api_Admin_NewsTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'extension_news.xml';
    
    public function testNews()
    {
        $array = $this->api_admin->news_get_list();
        $this->assertIsArray($array);

        $data = array('id'=>1);
        $array = $this->api_admin->news_get($data);
        $this->assertIsArray($array);

        $data = array(
            'id'        => 1,
            'title'     => 'News Title',
            'slug'      => 'news-title',
            'status'    => 'draft',
            'content'   => 'Announcement',
        );
        $bool = $this->api_admin->news_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->news_delete($data);
        $this->assertTrue($bool);

        $data = array(
            'title' =>  'Test',
        );
        $id = $this->api_admin->news_create($data);
        $this->assertTrue(is_numeric($id));
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

    public function testNewsBatchDelete()
    {
        $array = $this->api_admin->news_get_list(array());

        foreach ($array['list'] as $value) {
            $ids[] = $value['id'];
        }
        $result = $this->api_admin->news_batch_delete(array('ids' => $ids));
        $array  = $this->api_admin->news_get_list(array());

        $this->assertEquals(0, count($array['list']));
        $this->assertTrue($result);
    }

    public function testNewsMoreTagProvider()
    {
        $this->assertTrue(true);

        return array(
            array(
                'This is blog post with<!--more--> tag',
                'This is blog post with'
            ),
            array(
                'This is blog post without more tag',
                null
            )
        );
    }

    /**
     * @dataProvider testNewsMoreTagProvider
     */
    public function testNewsMoreTag($content, $expectedExcerpt)
    {
        $data = array(
            'title'   => 'News Title',
            'slug'    => 'news-title',
            'status'  => 'draft',
            'content' => $content,
        );

        $id = $this->api_admin->news_create($data);

        $array = $this->api_admin->news_get(array('id' => $id));
        $this->assertEquals($array['excerpt'], $expectedExcerpt);
        $this->assertEquals($array['content'], $content);
    }
}