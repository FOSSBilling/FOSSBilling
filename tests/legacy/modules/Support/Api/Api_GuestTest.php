<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Support\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class Api_GuestTest extends \BBTestCase
{
    protected ?\Box\Mod\Support\Api\Guest $guestApi;

    public function setUp(): void
    {
        $this->guestApi = new \Box\Mod\Support\Api\Guest();
    }

    public function testTicketCreate(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForGuest'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForGuest')
            ->willReturn(bin2hex(random_bytes(random_int(15, 30))));

        $di = $this->getDi();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'name' => 'Name',
            'email' => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => 'Message',
        ];
        $result = $this->guestApi->ticket_create($data);

        $this->assertIsString($result);
        $this->assertGreaterThan(0, strlen($result));
    }

    public function testTicketCreateMessageTooShortException(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForGuest'])->getMock();
        $serviceMock->expects($this->never())->method('ticketCreateForGuest')
            ->willReturn(sha1(uniqid()));

        $di = $this->getDi();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'name' => 'Name',
            'email' => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => '',
        ];

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $this->guestApi->ticket_create($data);

        $this->assertIsString($result);
        $this->assertEquals(40, strlen($result));
    }

    public function testTicketGet(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'hash' => sha1(uniqid()),
        ];
        $result = $this->guestApi->ticket_get($data);

        $this->assertIsArray($result);
    }

    public function testTicketClose(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicCloseTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->willReturn(true);

        $di = $this->getDi();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);
        $this->guestApi->setIdentity(new \Model_Guest());

        $data = [
            'hash' => sha1(uniqid()),
        ];
        $result = $this->guestApi->ticket_close($data);

        $this->assertTrue($result);
    }

    public function testTicketReply(): void
    {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicTicketReplyForGuest'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReplyForGuest')
            ->willReturn(sha1(uniqid()));

        $di = $this->getDi();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'hash' => sha1(uniqid()),
            'message' => 'Message',
        ];
        $result = $this->guestApi->ticket_reply($data);

        $this->assertIsString($result);
        $this->assertEquals(40, strlen($result));
    }

    /*
    * Knowledge Base Tests.
    */

    public function testKbArticleGetList(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $willReturn = [
            'pages' => 5,
            'page' => 2,
            'per_page' => 2,
            'total' => 10,
            'list' => [],
        ];

        $supportService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbSearchArticles'])->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('kbSearchArticles')
            ->willReturn($willReturn);
        $guestApi->setService($supportService);

        $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getDefaultPerPage'])
        ->disableOriginalConstructor()
        ->getMock();
        $pagerMock->expects($this->atLeastOnce())
            ->method('getDefaultPerPage')
            ->willReturn(100);
        $di = $this->getDi();
        $di['pager'] = $pagerMock;

        $guestApi->setDi($di);
        $result = $guestApi->kb_article_get_list([]);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKbArticleGetWithId(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = $this->getDi();

        $guestApi->setDi($di);

        $supportService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'])->getMock();
        $supportService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleById')
            ->willReturn(new \Model_SupportKbArticle());
        $supportService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->willReturn(new \Model_SupportKbArticle());
        $supportService->expects($this->atLeastOnce())
            ->method('kbHitView')
        ;
        $supportService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->willReturn([]);
        $guestApi->setService($supportService);

        $data = [
            'id' => 1,
        ];
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKbArticleGetWithSlug(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = $this->getDi();

        $guestApi->setDi($di);

        $supportService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'])->getMock();
        $supportService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->willReturn(new \Model_SupportKbArticle());
        $supportService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleBySlug')
            ->willReturn(new \Model_SupportKbArticle());
        $supportService->expects($this->atLeastOnce())
            ->method('kbHitView')
        ;
        $supportService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->willReturn([]);
        $guestApi->setService($supportService);

        $data = [
            'slug' => 'article-slug',
        ];
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKbArticleGetIdAndSlugNotSetException(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'])->getMock();
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->willReturn(new \Model_SupportKbArticle());
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->willReturn(new \Model_SupportKbArticle());
        $kbService->expects($this->never())
            ->method('kbHitView')
        ;
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get([]);
        $this->assertIsArray($result);
    }

    public function testKbArticleGetNotFoundById(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleById')
            ->willReturn(null);
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleBySlug')
            ->willReturn(new \Model_SupportKbArticle());
        $kbService->expects($this->never())
            ->method('kbHitView')
        ;
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        $di = $this->getDi();

        $guestApi->setDi($di);
        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKbArticleGetNotFoundBySlug(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindActiveArticleById', 'kbHitView', 'kbToApiArray', 'kbFindActiveArticleBySlug'])->getMock();
        $kbService->expects($this->never())
            ->method('kbFindActiveArticleById')
            ->willReturn(new \Model_SupportKbArticle());
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindActiveArticleBySlug')
            ->willReturn(null);
        $kbService->expects($this->never())
            ->method('kbHitView')
        ;
        $kbService->expects($this->never())
            ->method('kbToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'slug' => 'article-slug',
        ];

        $di = $this->getDi();

        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_article_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetList(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $willReturn = [
            'pages' => 5,
            'page' => 2,
            'per_page' => 2,
            'total' => 10,
            'list' => [],
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetSearchQuery'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetSearchQuery')
            ->willReturn(['String', []]);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $guestApi->setDi($di);
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_list([]);
        $this->assertIsArray($result);
        $this->assertEquals($result, $willReturn);
    }

    public function testKbCategoryGetPairs(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $expected = [
            1 => 'First Category',
            2 => 'Second Category',
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetPairs'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetPairs')
            ->willReturn($expected);
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_pairs([]);
        $this->assertIsArray($result);
        $this->assertEquals($result, $expected);
    }

    public function testKbCategoryGetWithId(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryById')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        $di = $this->getDi();

        $guestApi->setDi($di);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetWithSlug(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'])->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryBySlug')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $di = $this->getDi();

        $guestApi->setDi($di);

        $data = [
            'slug' => 'category-slug',
        ];
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetIdAndSlugNotSetException(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'])->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get([]);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetNotFoundById(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryById')
            ->willThrowException(new \FOSSBilling\Exception('Knowledge Base category not found'));
        $kbService->expects($this->never())
            ->method('kbFindCategoryBySlug')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        $di = $this->getDi();

        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }

    public function testKbCategoryGetNotFoundBySlug(): void
    {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbFindCategoryById', 'kbHitView', 'kbCategoryToApiArray', 'kbFindCategoryBySlug'])->getMock();
        $kbService->expects($this->never())
            ->method('kbFindCategoryById')
            ->willReturn(new \Model_SupportKbArticleCategory());
        $kbService->expects($this->atLeastOnce())
            ->method('kbFindCategoryBySlug')
            ->willReturn(null);
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'slug' => 'article-slug',
        ];

        $di = $this->getDi();

        $guestApi->setDi($di);

        $this->expectException(\FOSSBilling\Exception::class);
        $result = $guestApi->kb_category_get($data);
        $this->assertIsArray($result);
    }
}
