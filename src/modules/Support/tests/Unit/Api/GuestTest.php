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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketCreateForGuest')->atLeast()->once()
            ->andReturn(bin2hex(random_bytes(random_int(100, 127))));

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketCreateForGuest')
            ->andReturn(sha1(uniqid()));

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicFindOneByHash')->atLeast()->once()
            ->andReturn(new \Model_SupportPTicket());
        $serviceMock->shouldReceive('publicToApiArray')->atLeast()->once()
            ->andReturn([]);

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicFindOneByHash')->atLeast()->once()
            ->andReturn(new \Model_SupportPTicket());
        $serviceMock->shouldReceive('publicCloseTicket')->atLeast()->once()
            ->andReturn(true);

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicFindOneByHash')->atLeast()->once()
            ->andReturn(new \Model_SupportPTicket());
        $serviceMock->shouldReceive('publicTicketReplyForGuest')->atLeast()->once()
            ->andReturn(sha1(uniqid()));

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

        $supportService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $supportService
    ->shouldReceive('kbSearchArticles')
    ->atLeast()->once()
    ->andReturn($willReturn);
        $guestApi->setService($supportService);

        $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
        $pagerMock
    ->shouldReceive('getDefaultPerPage')
    ->atLeast()->once()
    ->andReturn(100);
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

        $supportService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $supportService
    ->shouldReceive('kbFindActiveArticleById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticle());
        $supportService->shouldReceive("kbFindActiveArticleBySlug")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $supportService->shouldReceive('kbHitView')->atLeast()->once();
        $supportService
    ->shouldReceive('kbToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
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

        $supportService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $supportService->shouldReceive("kbFindActiveArticleById")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $supportService
    ->shouldReceive('kbFindActiveArticleBySlug')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticle());
        $supportService->shouldReceive('kbHitView')->atLeast()->once();
        $supportService
    ->shouldReceive('kbToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
        $guestApi->setService($supportService);

        $data = [
            'slug' => 'article-slug',
        ];
        $result = $guestApi->kb_article_get($data);
        expect($result)->toBeArray();
    });

    test('kb article get id and slug not set exception', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbFindActiveArticleById")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $kbService->shouldReceive("kbFindActiveArticleBySlug")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $kbService->shouldReceive("kbHitView")->never()
        ;
        $kbService->shouldReceive("kbToApiArray")->never()
            ->andReturn([]);
        $guestApi->setService($kbService);

        expect(fn () => $guestApi->kb_article_get([]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb article get not found by id', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbFindActiveArticleById')
    ->atLeast()->once()
    ->andReturn(null);
        $kbService->shouldReceive("kbFindActiveArticleBySlug")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $kbService->shouldReceive("kbHitView")->never()
        ;
        $kbService->shouldReceive("kbToApiArray")->never()
            ->andReturn([]);
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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbFindActiveArticleById")->never()
            ->andReturn(new \Model_SupportKbArticle());
        $kbService
    ->shouldReceive('kbFindActiveArticleBySlug')
    ->atLeast()->once()
    ->andReturn(null);
        $kbService->shouldReceive("kbHitView")->never()
        ;
        $kbService->shouldReceive("kbToApiArray")->never()
            ->andReturn([]);
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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbCategoryGetSearchQuery')
    ->atLeast()->once()
    ->andReturn(['String', []]);

        $pager = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();

        $pager
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($willReturn);

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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbCategoryGetPairs')
    ->atLeast()->once()
    ->andReturn($expected);
        $guestApi->setService($kbService);

        $result = $guestApi->kb_category_get_pairs([]);
        expect($result)->toBeArray();
        expect($result)->toEqual($expected);
    });

    test('kb category get with id', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbFindCategoryById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService->shouldReceive("kbFindCategoryBySlug")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService
    ->shouldReceive('kbCategoryToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbFindCategoryById")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService
    ->shouldReceive('kbFindCategoryBySlug')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService
    ->shouldReceive('kbCategoryToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbFindCategoryById")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService->shouldReceive("kbFindCategoryBySlug")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService->shouldReceive("kbCategoryToApiArray")->never()
            ->andReturn([]);
        $guestApi->setService($kbService);

        expect(fn () => $guestApi->kb_category_get([]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get not found by id', function () {
        $guestApi = new \Box\Mod\Support\Api\Guest();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
            ->shouldReceive('kbFindCategoryById')
            ->andThrow(new \FOSSBilling\Exception('Knowledge Base category not found'));
        $kbService->shouldReceive("kbFindCategoryBySlug")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService->shouldReceive("kbCategoryToApiArray")->never()
            ->andReturn([]);
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

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbFindCategoryById")->never()
            ->andReturn(new \Model_SupportKbArticleCategory());
        $kbService
    ->shouldReceive('kbFindCategoryBySlug')
    ->atLeast()->once()
    ->andReturn(null);
        $kbService->shouldReceive("kbCategoryToApiArray")->never()
            ->andReturn([]);
        $guestApi->setService($kbService);

        $data = [
            'slug' => 'article-slug',
        ];

        $di = container();

        $guestApi->setDi($di);

        expect(fn () => $guestApi->kb_category_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

