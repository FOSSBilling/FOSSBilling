<?php
namespace Box\Tests\Mod\Kb\Api;

class Api_GuestTest extends \BBTestCase
{
    public function testArticle_get_list()
    {

        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );


        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('searchArticles'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('searchArticles')
            ->will($this->returnValue($willReturn));
        $guestApi->setService($kbService);

        $pagerMock = $this->getMockBuilder('Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPer_page')
            ->willReturn(100);
        $di = new \Box_Di();
        $di['pager'] = $pagerMock;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);
        $result = $guestApi->article_get_list(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);

    }

    public function testArticle_getWithId()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findActiveArticleById', 'hitView', 'toApiArray', 'findActiveArticleBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('findActiveArticleById')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('findActiveArticleBySlug')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('hitView')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $guestApi->article_get($data);
        $this->assertIsArray($result);
    }

    public function testArticle_getWithSlug()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findActiveArticleById', 'hitView', 'toApiArray', 'findActiveArticleBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findActiveArticleById')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('findActiveArticleBySlug')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('hitView')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data   = array(
            'slug' => 'article-slug'
        );
        $result = $guestApi->article_get($data);
        $this->assertIsArray($result);
    }

 
    public function testArticle_getIdAndSlugNotSetException()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findActiveArticleById', 'hitView', 'toApiArray', 'findActiveArticleBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findActiveArticleById')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('findActiveArticleBySlug')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('hitView')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('toApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $this->expectException(\Box_Exception::class);
        $result = $guestApi->article_get(array());
        $this->assertIsArray($result);
    }
   
    public function testArticle_getNotFoundById()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findActiveArticleById', 'hitView', 'toApiArray', 'findActiveArticleBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('findActiveArticleById')
            ->will($this->returnValue(false));
        $kbService->expects($this->never())
            ->method('findActiveArticleBySlug')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('hitView')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('toApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'id' => rand(1, 100)
        );

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);
        $this->expectException(\Box_Exception::class);
        $result = $guestApi->article_get($data);
        $this->assertIsArray($result);
    }

    public function testArticle_getNotFoundBySlug()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findActiveArticleById', 'hitView', 'toApiArray', 'findActiveArticleBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findActiveArticleById')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('findActiveArticleBySlug')
            ->will($this->returnValue(null));
        $kbService->expects($this->never())
            ->method('hitView')
            ->will($this->returnValue(new \Model_KbArticle()));
        $kbService->expects($this->never())
            ->method('toApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $data = array(
            'slug' => 'article-slug'
        );

        $this->expectException(\Box_Exception::class);
        $result = $guestApi->article_get($data);
        $this->assertIsArray($result);
    }

    public function testCategory_get_list()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );

        $pager = $this->getMockBuilder('Box_Pagination')->getMock();
        $pager->expects($this->atLeastOnce())
            ->method('getAdvancedResultSet')
            ->will($this->returnValue($willReturn));

        $di          = new \Box_Di();
        $di['pager'] = $pager;
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('categoryGetSearchQuery'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('categoryGetSearchQuery')
            ->will($this->returnValue(true));
        $guestApi->setService($kbService);

        $result = $guestApi->category_get_list(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testCategory_get_pairs()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $expected = array(
            1 => "First Category",
            2 => "Second Category"
        );

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('categoryGetPairs'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('categoryGetPairs')
            ->will($this->returnValue($expected));
        $guestApi->setService($kbService);

        $result = $guestApi->category_get_pairs(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testCategory_getWithId()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findCategoryById', 'hitView', 'categoryToApiArray', 'findCategoryBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('findCategoryById')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->never())
            ->method('findCategoryBySlug')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('categoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data   = array(
            'id' => rand(1, 100)
        );

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);
        $result = $guestApi->category_get($data);
        $this->assertIsArray($result);
    }

    public function testCategory_getWithSlug()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findCategoryById', 'hitView', 'categoryToApiArray', 'findCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findCategoryById')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('findCategoryBySlug')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('categoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $data   = array(
            'slug' => 'category-slug'
        );
        $result = $guestApi->category_get($data);
        $this->assertIsArray($result);
    }

    public function testCategory_getIdAndSlugNotSetException()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findCategoryById', 'hitView', 'categoryToApiArray', 'findCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findCategoryById')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->never())
            ->method('findCategoryBySlug')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->never())
            ->method('categoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $this->expectException(\Box_Exception::class);
        $result = $guestApi->category_get(array());
        $this->assertIsArray($result);
    }

    public function testCategory_getNotFoundById()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findCategoryById', 'hitView', 'categoryToApiArray', 'findCategoryBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('findCategoryById')
            ->will($this->returnValue(false));
        $kbService->expects($this->never())
            ->method('findCategoryBySlug')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->never())
            ->method('categoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'id' => rand(1, 100)
        );

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $guestApi->category_get($data);
        $this->assertIsArray($result);
    }

    public function testCategory_getNotFoundBySlug()
    {
        $guestApi = new \Box\Mod\Kb\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Kb\Service')->setMethods(array('findCategoryById', 'hitView', 'categoryToApiArray', 'findCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('findCategoryById')
            ->will($this->returnValue(new \Model_KbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('findCategoryBySlug')
            ->will($this->returnValue(null));
        $kbService->expects($this->never())
            ->method('categoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'slug' => 'article-slug'
        );

        $di = new \Box_Di();
        $di['array_get'] = $di->protect(function (array $array, $key, $default = null) use ($di) {
            return isset ($array[$key]) ? $array[$key] : $default;
        });
        $guestApi->setDi($di);

        $this->expectException(\Box_Exception::class);
        $result = $guestApi->category_get($data);
        $this->assertIsArray($result);
    }


}