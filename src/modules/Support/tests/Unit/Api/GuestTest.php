<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

beforeEach(function () {
    $this->guestApi = new \Box\Mod\Support\Api\Guest();
});

test('ticket create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForGuest'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForGuest')
            ->willReturn(bin2hex(random_bytes(random_int(100, 127))));

        $di = container();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'name' => 'Name',
            'email' => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => 'Message',
        ];
        $result = $this->guestApi->ticket_create($data);

        expect($result)->toBeString();
        expect(strlen($result))->toBeGreaterThan(0);
    });

    test('ticket create message too short exception', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForGuest'])->getMock();
        $serviceMock->expects($this->never())->method('ticketCreateForGuest')
            ->willReturn(sha1(uniqid()));

        $di = container();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'name' => 'Name',
            'email' => 'email@wxample.com',
            'subject' => 'Subject',
            'message' => '',
        ];

        expect(fn () => $this->guestApi->ticket_create($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('ticket get', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn([]);

        $di = container();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'hash' => sha1(uniqid()),
        ];
        $result = $this->guestApi->ticket_get($data);

        expect($result)->toBeArray();
    });

    test('ticket close', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicCloseTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->willReturn(true);

        $di = container();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);
        $this->guestApi->setIdentity(new \Model_Guest());

        $data = [
            'hash' => sha1(uniqid()),
        ];
        $result = $this->guestApi->ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('ticket reply', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicFindOneByHash', 'publicTicketReplyForGuest'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicFindOneByHash')
            ->willReturn(new \Model_SupportPTicket());
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReplyForGuest')
            ->willReturn(sha1(uniqid()));

        $di = container();
        $this->guestApi->setDi($di);

        $this->guestApi->setService($serviceMock);

        $data = [
            'hash' => sha1(uniqid()),
            'message' => 'Message',
        ];
        $result = $this->guestApi->ticket_reply($data);

        expect($result)->toBeString();
        expect(strlen($result))->toEqual(40);
    });

    /*
    * Knowledge Base Tests.
    */

    test('kb article get list', function () {
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
        $di = container();
        $di['pager'] = $pagerMock;

        $guestApi->setDi($di);
        $result = $guestApi->kb_article_get_list([]);
        expect($result)->toBeArray();
        expect($result)->toEqual($willReturn);
    });

    test('kb article get with id', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = container();

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
        expect($result)->toBeArray();
    });

    test('kb article get with slug', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $di = container();

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
        expect($result)->toBeArray();
    });

    test('kb article get id and slug not set exception', function () {
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

        expect(fn () => $guestApi->kb_article_get([]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb article get not found by id', function () {
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

        $di = container();

        $guestApi->setDi($di);
        expect(fn () => $guestApi->kb_article_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb article get not found by slug', function () {
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

        $di = container();

        $guestApi->setDi($di);

        expect(fn () => $guestApi->kb_article_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get list', function () {
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

        $di = container();
        $di['pager'] = $pager;

        $guestApi->setDi($di);
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_list([]);
        expect($result)->toBeArray();
        expect($result)->toEqual($willReturn);
    });

    test('kb category get pairs', function () {
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
        expect($result)->toBeArray();
        expect($result)->toEqual($expected);
    });

    test('kb category get with id', function () {
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

        $di = container();

        $guestApi->setDi($di);
        $result = $guestApi->kb_category_get($data);
        expect($result)->toBeArray();
    });

    test('kb category get with slug', function () {
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

        $di = container();

        $guestApi->setDi($di);

        $data = [
            'slug' => 'category-slug',
        ];
        $result = $guestApi->kb_category_get($data);
        expect($result)->toBeArray();
    });

    test('kb category get id and slug not set exception', function () {
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

        expect(fn () => $guestApi->kb_category_get([]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get not found by id', function () {
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

        $di = container();

        $guestApi->setDi($di);

        expect(fn () => $guestApi->kb_category_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get not found by slug', function () {
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

        $di = container();

        $guestApi->setDi($di);

        expect(fn () => $guestApi->kb_category_get($data))->toThrow(\FOSSBilling\Exception::class);
    });
