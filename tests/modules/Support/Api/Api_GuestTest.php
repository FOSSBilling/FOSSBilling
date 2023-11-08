<?php

namespace Box\Tests\Mod\Support\Api;

class Api_GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Support\Api\Guest
     */
    protected $guestApi = null;

    public function setup(): void
    {
        $this->guestApi = new \Box\Mod\Support\Api\Guest();
    }

    public function testTicket_create()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketCreateForGuest'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForGuest')
            ->will($this->returnValue(sha1(uniqid())));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'name'    => 'Name',
            'email'   => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => 'Message',
        );
        $result = $this->guestApi->ticket_create($data);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testTicket_createMessageTooShortException()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('ticketCreateForGuest'))->getMock();
        $serviceMock->expects($this->never())->method('ticketCreateForGuest')
            ->will($this->returnValue(sha1(uniqid())));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'name'    => 'Name',
            'email'   => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => '',
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->ticket_create($data);

        $this->assertIsString($result);
        $this->assertEquals(strlen($result), 40);
    }

    public function testTicket_get()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicFindOneByHash', 'publicToApiArray'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash' => sha1(uniqid()),
        );
        $result = $this->guestApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testTicket_close()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicFindOneByHash', 'publicCloseTicket'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash' => sha1(uniqid()),
        );
        $result = $this->guestApi->ticket_close($data);

        $this->assertIsArray($result);
    }

    public function testTicket_reply()
    {
        $serviceMock = $this->getMockBuilder('\Box\Mod\Support\Service')
            ->onlyMethods(array('publicFindOneByHash', 'publicTicketReplyForGuest'))->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->will($this->returnValue(new \Model_SupportPTicket()));
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReplyForGuest')
            ->will($this->returnValue(array()));

        $validatorMock = $this->getMockBuilder('\FOSSBilling\Validate')->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray')
            ->will($this->returnValue(null));


        $di              = new \Pimple\Container();
        $di['validator'] = $validatorMock;
        $this->guestApi ->setDi($di);

        $this->guestApi ->setService($serviceMock);

        $data   = array(
            'hash'    => sha1(uniqid()),
            'message' => 'Message'
        );
        $result = $this->guestApi->ticket_reply($data);

        $this->assertIsArray($result);
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKb_article_get_list()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $willReturn = array(
            "pages"    => 5,
            "page"     => 2,
            "per_page" => 2,
            "total"    => 10,
            "list"     => array(),
        );

        $supportService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbSearchArticles'))->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('kbSearchArticles')
            ->will($this->returnValue($willReturn));
        $guestApi->setService($supportService);

        $pagerMock = $this->getMockBuilder('Box_Pagination')->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getPer_page')
            ->willReturn(100);
        $di = new \Pimple\Container();
        $di['pager'] = $pagerMock;

        $guestApi->setDi($di);
        $result = $guestApi->kb_article_get_list(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKb_article_getWithId()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $supportService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'))->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleById')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->atLeastOnce())
            ->method('kbHitView')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($supportService);

        $data   = array(
            'id' => rand(1, 100)
        );
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_article_getWithSlug()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $supportService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'))->getMock();
        $supportService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->atLeastOnce())
            ->method('kbHitView')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $supportService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($supportService);

        $data   = array(
            'slug' => 'article-slug'
        );
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_article_getIdAndSlugNotSetException()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbHitView')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get(array());
        $this->assertIsArray($result);
    }

    public function testKb_Article_getNotFoundById()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleById')
            ->will($this->returnValue(false));
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbHitView')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'id' => rand(1, 100)
        );

        $di = new \Pimple\Container();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_article_getNotFoundBySlug()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleBySlug')
            ->will($this->returnValue(null));
        $kbService->expects($this->never())
            ->method('kbHitView')
            ->will($this->returnValue(new \Model_SupportKbArticle()));
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $data = array(
            'slug' => 'article-slug'
        );

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_get_list()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

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

        $di          = new \Pimple\Container();
        $di['pager'] = $pager;

        $guestApi->setDi($di);

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryGetSearchQuery'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetSearchQuery')
            ->will($this->returnValue(true));
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_list(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKb_category_get_pairs()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $expected = array(
            1 => "First Category",
            2 => "Second Category"
        );

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbCategoryGetPairs'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetPairs')
            ->will($this->returnValue($expected));
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_pairs(array());
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testKb_category_getWithId()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryById')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data   = array(
            'id' => rand(1, 100)
        );

        $di = new \Pimple\Container();

        $guestApi->setDi($di);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_getWithSlug()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $data   = array(
            'slug' => 'category-slug'
        );
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_getIdAndSlugNotSetException()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get(array());
        $this->assertIsArray($result);
    }

    public function testKb_category_getNotFoundById()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'))->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryById')
            ->will($this->returnValue(false));
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'id' => rand(1, 100)
        );

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKb_category_getNotFoundBySlug()
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder('Box\Mod\Support\Service')->onlyMethods(array('kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'))->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->will($this->returnValue(new \Model_SupportKbArticleCategory()));
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryBySlug')
            ->will($this->returnValue(null));
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->will($this->returnValue(array()));
        $guestApi->setService($kbService);

        $data = array(
            'slug' => 'article-slug'
        );

        $di = new \Pimple\Container();

        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

}
