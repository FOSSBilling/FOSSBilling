<?php
namespace Box\Tests\Mod\Kb;

class ServiceTest extends \BBTestCase
{
    public function testSearchArticles()
    {
        $service = new \Box\Mod\Kb\Service();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );

        $di = new \Box_Di();

        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getSimpleResultSet')
            ->will($this->returnValue($willReturn));


        $client      = new \Model_Client();
        $client->loadBean(new \RedBeanPHP\OODBBean());
        $client->id  = 5;
        $di['pager'] = $pager;
        $service->setDi($di);

        $result = $service->searchArticles('active', 'keyword', 'category');
        $this->assertIsArray($result);

        $this->assertArrayHasKey('list', $result);
        $this->assertIsArray($result['list']);

        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total', $result);
    }

    public function testfindActiveArticleById()
    {
        $service = new \Box\Mod\Kb\Service();

        $model = new \Model_KbArticle();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->findActiveArticleById(5);
        $this->assertInstanceOf('Model_KbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testfindActiveArticleBySlug()
    {
        $service = new \Box\Mod\Kb\Service();

        $model = new \Model_KbArticle();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->findActiveArticleBySlug('slug');
        $this->assertInstanceOf('Model_KbArticle', $result);
        $this->assertEquals($result, $model);
    }

    public function testFindActive()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array()));
        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->findActive();
        $this->assertIsArray($result);
    }

    public function testHitView()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue(5));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $modelKb        = new \Model_KbArticle();
        $modelKb->loadBean(new \RedBeanPHP\OODBBean());
        $modelKb->views = 10;

        $result = $service->hitView($modelKb);
        $this->assertNull($result);
    }

    public function testRm()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('trash')
            ->will($this->returnValue(null));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $modelKb        = new \Model_KbArticle();
        $modelKb->loadBean(new \RedBeanPHP\OODBBean());
        $modelKb->id    = 1;
        $modelKb->views = 10;


        $result = $service->rm($modelKb);
        $this->assertNull($result);
    }

    public function toApiArrayProvider()
    {
        $model                         = new \Model_KbArticle();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id                     = rand(1, 100);
        $model->slug                   = 'article-slug';
        $model->title                  = "Title";
        $model->views                  = rand(1, 100);
        $model->content                = 'Content';
        $model->created_at             = '2013-01-01 12:00:00';
        $model->updated_at             = '2014-01-01 12:00:00';
        $model->status                 = 'active';
        $model->kb_article_category_id = rand(1, 100);

        $category        = new \Model_KbArticleCategory();
        $category->loadBean(new \RedBeanPHP\OODBBean());
        $category->id    = rand(1, 100);
        $category->slug  = 'category-slug';
        $category->title = 'category-title';

        return array(
            array(
                $model,
                array(
                    'id'         => $model->id,
                    'slug'       => $model->slug,
                    'title'      => $model->title,
                    'views'      => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'status'                 => $model->status,
                ),
                false,
                null,
                $category
            ),
            array(
                $model,
                array(
                    'id'         => $model->id,
                    'slug'       => $model->slug,
                    'title'      => $model->title,
                    'views'      => $model->views,
                    'created_at' => $model->created_at,
                    'updated_at' => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'content'    => $model->content,
                    'status'                 => $model->status,
                ),
                true,
                null,
                $category
            ),
            array(
                $model,
                array(
                    'id'                     => $model->id,
                    'slug'                   => $model->slug,
                    'title'                  => $model->title,
                    'views'                  => $model->views,
                    'created_at'             => $model->created_at,
                    'updated_at'             => $model->updated_at,
                    'category'   => array(
                        'id'    => $category->id,
                        'slug'  => $category->slug,
                        'title' => $category->title,
                    ),
                    'content'                => $model->content,
                    'status'                 => $model->status,
                    'kb_article_category_id' => $model->kb_article_category_id
                ),
                true,
                new \Model_Admin(),
                $category
            ),

        );
    }

    /**
     * @dataProvider toApiArrayProvider
     */
    public function testToApiArray($model, $expected, $deep, $identity, $category)
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($category));
        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result  = $service->toApiArray($model, $deep, $identity);
        $this->assertEquals($result, $expected);
    }

    public function testCreateArticle()
    {
        $service = new \Box\Mod\Kb\Service();
        $randId  = rand(1, 100);
        $db      = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $model = new \Model_KbArticle();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($model));

        $tools = $this->getMockBuilder('Box_Tools')->setMethods(array('slug'))->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->will($this->returnValue('article-slug'));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['tools']  = $tools;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->createArticle(rand(1, 100), 'Title', 'Active', 'Content');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);
    }

    public function testUpdateArticle()
    {
        $service = new \Box\Mod\Kb\Service();
        $randId  = rand(1, 100);

        $model = new \Model_KbArticle();
        $model->loadBean(new \RedBeanPHP\OODBBean());

        $kb_article_category_id = rand(1, 100);
        $title                  = 'Title';
        $slug                   = 'article-slug';
        $status                 = 'active';
        $content                = 'content';
        $views                  = rand(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di);
        $result = $service->updateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
   
    public function testUpdateArticleNotFoundException()
    {
        $service = new \Box\Mod\Kb\Service();
        $randId  = rand(1, 100);


        $kb_article_category_id = rand(1, 100);
        $title                  = 'Title';
        $slug                   = 'article-slug';
        $status                 = 'active';
        $content                = 'content';
        $views                  = rand(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue(false));
        $db->expects($this->never())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();

        $service->setDi($di); 
        $this->expectException(\Box_Exception::class);
        $result = $service->updateArticle($randId, $kb_article_category_id, $title, $slug, $status, $content, $views);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function categoryGetSearchQueryProvider()
    {
        return array(
            array(
                array(),
                '
                SELECT kac.*
                FROM kb_article_category kac
                LEFT JOIN kb_article ka ON kac.id  = ka.kb_article_category_id ORDER BY kac.id DESC',
                array(),
            ),
            array(
                array(
                    'article_status' => "active"
                ),
                'SELECT kac.*
                 FROM kb_article_category kac
                 LEFT JOIN kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status ORDER BY kac.id DESC',
                array(
                    ':status' => 'active',
                ),
            ),
            array(
                array(
                    'q' => "search query"
                ),
                'SELECT kac.*
                 FROM kb_article_category kac
                 LEFT JOIN kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE (ka.title LIKE :title OR ka.content LIKE :content) ORDER BY kac.id DESC',
                array(
                    ':title'   => '%search query%',
                    ':content' => '%search query%',
                ),
            ),
            array(
                array(
                    'q'              => "search query",
                    'article_status' => "active"
                ),
                'SELECT kac.*
                 FROM kb_article_category kac
                 LEFT JOIN kb_article ka ON kac.id  = ka.kb_article_category_id
                 WHERE ka.status = :status AND (ka.title LIKE :title OR ka.content LIKE :content) ORDER BY kac.id DESC',
                array(
                    ':title'   => '%search query%',
                    ':content' => '%search query%',
                    ':status'  => 'active',
                ),
            ),

        );
    }

    /**
     * @dataProvider categoryGetSearchQueryProvider
     */
    public function testCategoryGetSearchQuery($data, $query, $bindings)
    {
        $service = new \Box\Mod\Kb\Service();

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $service->setDi($di);

        $result = $service->categoryGetSearchQuery($data);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);

        $this->assertEquals(trim(preg_replace('/\s+/', '', str_replace("\n", " ", $result[0]))), trim(preg_replace('/\s+/', '', str_replace("\n", " ", $query))));
        $this->assertEquals($result[1], $bindings);

    }

    public function testCategoryFindAll()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAll')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->categoryFindAll();
        $this->assertIsArray($result);
    }

    public function testCategoryGetPairs()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->categoryGetPairs();
        $this->assertIsArray($result);
    }

    public function testCategoryRm()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(0));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model            = new \Model_KbArticleCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id        = rand(1, 100);
        $model->KbArticle = new \Model_KbArticleCategory();
        $model->KbArticle->loadBean(new \RedBeanPHP\OODBBean());

        $result = $service->categoryRm($model);
        $this->assertTrue($result);
    }

    public function testCategoryRmHasArticlesException()
    {
        $service = new \Box\Mod\Kb\Service();

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getCell')
            ->will($this->returnValue(1));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model            = new \Model_KbArticleCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id        = rand(1, 100);
        $model->KbArticle = new \Model_KbArticle();
        $model->KbArticle->loadBean(new \RedBeanPHP\OODBBean());

        $this->expectException(\Box_Exception::class);
        $result = $service->categoryRm($model);
        $this->assertNull($result);
    }

    public function testCategoryToApiArray()
    {
        $article        = new \Model_KbArticle();
        $article->loadBean(new \RedBeanPHP\OODBBean());
        $article->id    = rand(1, 100);
        $article->slug  = 'category-slug';
        $article->title = 'category-title';

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('toArray')
            ->will($this->returnValue(array()));
        $db->expects($this->atLeastOnce())
            ->method('find')
            ->will($this->returnValue(array($article)));

        $serviceMock = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('toApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $serviceMock->setDi($di);

        $model            = new \Model_KbArticleCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $result = $serviceMock->categoryToApiArray($model);
        $this->assertIsArray($result);
    }

    public function testCreateCategory()
    {
        $service = new \Box\Mod\Kb\Service();

        $randId = rand(1, 100);

        $db = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));
        $articleCategoryModel = new \Model_KbArticleCategory();
        $articleCategoryModel->loadBean(new \RedBeanPHP\OODBBean());

        $db->expects($this->atLeastOnce())
            ->method('dispense')
            ->will($this->returnValue($articleCategoryModel));

        $tools = $this->getMockBuilder('Box_Tools')->setMethods(array('slug'))->getMock();
        $tools->expects($this->atLeastOnce())
            ->method('slug')
            ->will($this->returnValue('article-slug'));


        $systemService = $this->getMockBuilder('Box\Mod\System\Service')->getMock();
        $systemService->expects($this->atLeastOnce())
            ->method('checkLimits')
            ->will($this->returnValue(true));

        $di                = new \Box_Di();
        $di['db']          = $db;
        $di['tools']       = $tools;
        $di['logger']      = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $di['mod_service'] = $di->protect(function () use ($systemService) {
            return $systemService;
        });
        $service->setDi($di);

        $result = $service->createCategory('Title', 'Description');
        $this->assertIsInt($result);
        $this->assertEquals($result, $randId);

    }

    public function testUpdateCategory()
    {
        $service = new \Box\Mod\Kb\Service();
        $randId  = rand(1, 100);
        $db      = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('store')
            ->will($this->returnValue($randId));

        $di           = new \Box_Di();
        $di['db']     = $db;
        $di['logger'] = $di['logger'] = $this->getMockBuilder('Box_Log')->getMock();
        $service->setDi($di);

        $model     = new \Model_KbArticleCategory();
        $model->loadBean(new \RedBeanPHP\OODBBean());
        $model->id = rand(1, 100);

        $result = $service->updateCategory($model, 'New Title', 'new-title', 'Description');
        $this->assertTrue($result);
    }

    public function testfindCategoryById()
    {
        $service = new \Box\Mod\Kb\Service();

        $model = new \Model_KbArticleCategory();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->findCategoryById(5);
        $this->assertInstanceOf('Model_KbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }

    public function testfindCategoryBySlug()
    {
        $service = new \Box\Mod\Kb\Service();

        $model = new \Model_KbArticleCategory();
        $db    = $this->getMockBuilder('Box_Database')->getMock();
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->will($this->returnValue($model));

        $di       = new \Box_Di();
        $di['db'] = $db;
        $service->setDi($di);

        $result = $service->findCategoryBySlug('slug');
        $this->assertInstanceOf('Model_KbArticleCategory', $result);
        $this->assertEquals($result, $model);
    }
}