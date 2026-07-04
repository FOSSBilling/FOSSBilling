<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Doctrine\ORM\QueryBuilder;

use function Tests\Helpers\container;

function guestSupportKbCategoryFixture(): KbArticleCategory
{
    return (new KbArticleCategory())
        ->setTitle('category-title')
        ->setSlug('category-slug');
}

function guestSupportKbArticleFixture(): KbArticle
{
    return (new KbArticle())
        ->setCategory(guestSupportKbCategoryFixture())
        ->setTitle('Title')
        ->setSlug('article-slug');
}

function guestSupportServiceMock(): Mockery\MockInterface
{
    $service = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $service->shouldReceive('kbEnabled')->byDefault()->andReturn(true);
    $service->shouldReceive('kbSuggestionsEnabled')->byDefault()->andReturn(true);
    $service->shouldReceive('kbArticleViewsEnabled')->byDefault()->andReturn(true);

    return $service;
}

test('ticket create', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $serviceMock = guestSupportServiceMock();
    $serviceMock->shouldReceive('ticketCreateForGuest')->atLeast()->once()
        ->andReturn(bin2hex(random_bytes(random_int(100, 127))));

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'subject' => 'Subject',
        'content' => 'Message',
    ];
    $result = $guestApi->ticket_create($data);

    expect($result)->toBeString();
    expect(strlen($result))->toBeGreaterThan(0);
});

test('ticket create message too short exception', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $serviceMock = guestSupportServiceMock();
    $serviceMock->shouldReceive('ticketCreateForGuest')
        ->andReturn(sha1(uniqid()));

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'subject' => 'Subject',
        'content' => '',
    ];

    expect(fn (): string => $guestApi->ticket_create($data))->toThrow(FOSSBilling\Exception::class);
});

test('ticket get', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $serviceMock = guestSupportServiceMock();
    $serviceMock->shouldReceive('findOneByHash')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'hash' => sha1(uniqid()),
    ];
    $result = $guestApi->ticket_get($data);

    expect($result)->toBeArray();
});

test('ticket close', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $serviceMock = guestSupportServiceMock();
    $serviceMock->shouldReceive('findOneByHash')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);
    $guestApi->setIdentity(new Model_Guest());

    $data = [
        'hash' => sha1(uniqid()),
    ];
    $result = $guestApi->ticket_close($data);

    expect($result)->toBeTrue();
});

test('ticket reply', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $serviceMock = guestSupportServiceMock();
    $serviceMock->shouldReceive('findOneByHash')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $guestApi->setDi($di);

    $guestApi->setService($serviceMock);

    $data = [
        'hash' => sha1(uniqid()),
        'content' => 'Message',
    ];
    $result = $guestApi->ticket_reply($data);

    expect($result)->toBeInt();
});

/*
* Knowledge Base Tests.
*/

test('kb article get list', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $willReturn = [
        'pages' => 5,
        'page' => 2,
        'per_page' => 2,
        'total' => 10,
        'list' => [],
    ];

    $qb = Mockery::mock(QueryBuilder::class);
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->with(KbArticle::ACTIVE, null, null)
        ->andReturn($qb);

    $supportService = guestSupportServiceMock();
    $supportService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($supportService);

    $pager = Mockery::mock(FOSSBilling\Pagination::class);
    $pager->shouldReceive('paginateDoctrineQuery')
        ->once()
        ->with($qb, Mockery::type(FOSSBilling\PaginationOptions::class), null, false, true)
        ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pager;

    $guestApi->setDi($di);
    $result = $guestApi->kb_article_get_list([]);
    expect($result)->toBeArray();
    expect($result)->toEqual($willReturn);
});

test('kb article views enabled', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();
    $service = guestSupportServiceMock();
    $service->shouldReceive('kbArticleViewsEnabled')->once()->andReturn(false);
    $guestApi->setService($service);

    expect($guestApi->kb_article_views_enabled())->toBeFalse();
});

test('kb article get hides views when disabled', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $di = container();
    $guestApi->setDi($di);

    $article = guestSupportKbArticleFixture()->setViews(12);
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('findOneActiveById')
        ->once()
        ->with(1)
        ->andReturn($article);
    $repo->shouldReceive('incrementViews')
        ->once()
        ->with($article);

    $supportService = guestSupportServiceMock();
    $supportService->shouldReceive('kbArticleViewsEnabled')->once()->andReturn(false);
    $supportService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($supportService);

    $result = $guestApi->kb_article_get(['id' => 1]);
    expect($result)->not->toHaveKey('views');
});

test('kb article get with id', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $di = container();

    $guestApi->setDi($di);

    $article = guestSupportKbArticleFixture();
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('findOneActiveById')
        ->once()
        ->with(1)
        ->andReturn($article);
    $repo->shouldReceive('incrementViews')
        ->once()
        ->with($article);

    $supportService = guestSupportServiceMock();
    $supportService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($supportService);

    $data = [
        'id' => 1,
    ];
    $result = $guestApi->kb_article_get($data);
    expect($result)->toBeArray();
});

test('kb article get with slug', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $di = container();

    $guestApi->setDi($di);

    $article = guestSupportKbArticleFixture();
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('findOneActiveBySlug')
        ->once()
        ->with('article-slug')
        ->andReturn($article);
    $repo->shouldReceive('incrementViews')
        ->once()
        ->with($article);

    $supportService = guestSupportServiceMock();
    $supportService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($supportService);

    $data = [
        'slug' => 'article-slug',
    ];
    $result = $guestApi->kb_article_get($data);
    expect($result)->toBeArray();
});

test('kb article get id and slug not set exception', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $guestApi->setService(guestSupportServiceMock());

    expect(fn (): array => $guestApi->kb_article_get([]))->toThrow(FOSSBilling\Exception::class);
});

test('kb article get list disabled exception', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $service = guestSupportServiceMock();
    $service->shouldReceive('kbEnabled')->andReturn(false);
    $guestApi->setService($service);

    expect(fn (): array => $guestApi->kb_article_get_list([]))->toThrow(FOSSBilling\Exception::class);
});

test('kb article get not found by id', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('findOneActiveById')
        ->once()
        ->with(1)
        ->andReturn(null);

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $data = [
        'id' => 1,
    ];

    $di = container();

    $guestApi->setDi($di);
    expect(fn (): array => $guestApi->kb_article_get($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb article get not found by slug', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('findOneActiveBySlug')
        ->once()
        ->with('article-slug')
        ->andReturn(null);

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $data = [
        'slug' => 'article-slug',
    ];

    $di = container();

    $guestApi->setDi($di);

    expect(fn (): array => $guestApi->kb_article_get($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb category get list', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $willReturn = [
        'pages' => 5,
        'page' => 2,
        'per_page' => 2,
        'total' => 10,
        'list' => [],
    ];

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->andReturn(Mockery::mock(QueryBuilder::class));

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);

    $pager = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();

    $pager
    ->shouldReceive('paginateDoctrineQuery')
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

test('kb category get with id', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(guestSupportKbCategoryFixture());

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $data = [
        'id' => 1,
    ];

    $di = container();

    $guestApi->setDi($di);
    $result = $guestApi->kb_category_get($data);
    expect($result)->toBeArray();
});

test('kb category get with slug', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('findOneBySlug')
        ->once()
        ->with('category-slug')
        ->andReturn(guestSupportKbCategoryFixture());

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $di = container();

    $guestApi->setDi($di);

    $data = [
        'slug' => 'category-slug',
    ];
    $result = $guestApi->kb_category_get($data);
    expect($result)->toBeArray();
});

test('kb category get id and slug not set exception', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $guestApi->setService(guestSupportServiceMock());

    expect(fn (): array => $guestApi->kb_category_get([]))->toThrow(FOSSBilling\Exception::class);
});

test('kb category get not found by id', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $data = [
        'id' => 1,
    ];

    $di = container();

    $guestApi->setDi($di);

    expect(fn (): array => $guestApi->kb_category_get($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb category get not found by slug', function (): void {
    $guestApi = new Box\Mod\Support\Api\Guest();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('findOneBySlug')
        ->once()
        ->with('category-slug-not-found')
        ->andReturn(null);

    $kbService = guestSupportServiceMock();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $guestApi->setService($kbService);

    $data = [
        'slug' => 'category-slug-not-found',
    ];

    $di = container();

    $guestApi->setDi($di);

    expect(fn (): array => $guestApi->kb_category_get($data))->toThrow(FOSSBilling\Exception::class);
});
