<?php
/**
 * @group Core
 */
class Api_Admin_KbTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'news.xml';
    
    public function testKbCategory()
    {
        $array = $this->api_admin->kb_category_get_list();
        $this->assertIsArray($array);

        $array = $this->api_admin->kb_category_get_pairs();
        $this->assertIsArray($array);

        $data = array(
            'title'    =>      'test',
        );
        $id = $this->api_admin->kb_category_create($data);
        $this->assertTrue(is_numeric($id));

        $data = array(
            'id'    =>$id,
        );
        $array = $this->api_admin->kb_category_get($data);
        $this->assertIsArray($array);

        $data['id'] = $id;
        $data['title'] = 'new';
        $bool = $this->api_admin->kb_category_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->kb_category_delete($data);
        $this->assertTrue($bool);
    }
    
    public function testKbArticle()
    {
        $array = $this->api_admin->kb_article_get_list();
        $this->assertIsArray($array);

        $data = array(
            'id'    =>  1,
        );
        $array = $this->api_admin->kb_article_get($data);
        $this->assertIsArray($array);

        $data = array(
            'kb_article_category_id'    =>      1,
            'title'                     =>      'test',
        );
        $id = $this->api_admin->kb_article_create($data);
        $this->assertTrue(is_numeric($id));

        $data['id'] = $id;
        $data['title'] = 'new';
        $bool = $this->api_admin->kb_article_update($data);
        $this->assertTrue($bool);

        $bool = $this->api_admin->kb_article_delete($data);
        $this->assertTrue($bool);
    }

    public function testArticleGetList()
    {
        $array = $this->api_admin->kb_article_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('views', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('category', $item);

            $category = $item['category'];
            $this->assertIsArray($category);
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('slug', $category);
            $this->assertArrayHasKey('title', $category);
        }
    }

    public function testCategoryGetList()
    {
        $array = $this->api_admin->kb_category_get_list();
        $this->assertIsArray($array);

        $this->assertArrayHasKey('list', $array);
        $list = $array['list'];
        $this->assertIsArray($list);

        if (count($list)) {
            $item = $list[0];
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('slug', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);
            $this->assertArrayHasKey('articles', $item);

            $articles = $item['articles'];
            if (count($articles)){
                $article = $articles[0];
                $this->assertIsArray($article);
                $this->assertArrayHasKey('id', $article);
                $this->assertArrayHasKey('slug', $article);
                $this->assertArrayHasKey('title', $article);
            }

        }
    }
}