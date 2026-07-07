<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\CannedResponse;
use Box\Mod\Support\Entity\CannedResponseCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Repository\CannedResponseCategoryRepository;
use Box\Mod\Support\Repository\CannedResponseRepository;
use Box\Mod\Support\Repository\HelpdeskRepository;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Doctrine\ORM\QueryBuilder;

use function Tests\Helpers\container;

function adminSupportKbCategoryFixture(): KbArticleCategory
{
    return (new KbArticleCategory())
        ->setTitle('category-title')
        ->setSlug('category-slug');
}

function adminSupportKbArticleFixture(): KbArticle
{
    return (new KbArticle())
        ->setCategory(adminSupportKbCategoryFixture())
        ->setTitle('Title')
        ->setSlug('article-slug');
}

function adminSupportSetEntityId(object $entity, int $id): void
{
    $property = new ReflectionProperty($entity, 'id');
    $property->setValue($entity, $id);
}

function adminSupportCannedCategoryFixture(): CannedResponseCategory
{
    $category = (new CannedResponseCategory())
        ->setTitle('Category 1');
    adminSupportSetEntityId($category, 1);

    return $category;
}

function adminSupportCannedResponseFixture(): CannedResponse
{
    $response = (new CannedResponse())
        ->setCategory(adminSupportCannedCategoryFixture())
        ->setTitle('Title')
        ->setContent('Content');
    adminSupportSetEntityId($response, 1);

    return $response;
}

function adminHelpdeskFixture(): Helpdesk
{
    $helpdesk = (new Helpdesk())
        ->setName('General')
        ->setEmail('support@example.com')
        ->setCanReopen(true)
        ->setCloseAfter(24)
        ->setSignature('Signature');
    adminSupportSetEntityId($helpdesk, 1);

    return $helpdesk;
}

function adminSupportCannedCategoryWithResponsesFixture(): CannedResponseCategory
{
    $category = adminSupportCannedCategoryFixture();
    $response = (new CannedResponse())
        ->setCategory($category)
        ->setTitle('Title')
        ->setContent('Content');
    adminSupportSetEntityId($response, 1);

    $responses = new ReflectionProperty($category, 'responses');
    $responses->getValue($category)->add($response);

    return $category;
}

test('ticket get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];
    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock
    ->shouldReceive('paginateMappedQuery')
    ->atLeast()->once()
    ->andReturn($simpleResultArr);

    $qb = Mockery::mock(QueryBuilder::class);
    $repo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $repo->shouldReceive('getSearchQueryBuilder')->andReturn($qb);

    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(SupportTicket::class)->andReturn($repo);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSupportTicketRepository')->andReturn($repo);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['em'] = $em;

    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->ticket_get_list($data);

    expect($result)->toBeArray();
});

test('ticket get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_get($data);

    expect($result)->toBeArray();
});

test('ticket update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('ticketUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_update($data);

    expect($result)->toBeTrue();
});

test('ticket message update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketMessageById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicketMessage());
    $serviceMock->shouldReceive('ticketMessageUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
        'content' => 'Content',
    ];
    $result = $api->ticket_message_update($data);

    expect($result)->toBeTrue();
});

test('ticket message history get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $message = new Box\Mod\Support\Entity\SupportTicketMessage();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketMessageById')->atLeast()->once()
        ->andReturn($message);
    $serviceMock->shouldReceive('getMessageHistory')->atLeast()->once()
        ->with($message)
        ->andReturn([['id' => 1, 'content' => 'Old content']]);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_message_history_get_list($data);

    expect($result)->toBe([['id' => 1, 'content' => 'Old content']]);
});

test('ticket delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('rm')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_delete($data);

    expect($result)->toBeTrue();
});

test('ticket reply', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
        'content' => 'Content',
    ];
    $result = $api->ticket_reply($data);

    expect($result)->toBeInt();
});

test('ticket close', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_close($data);

    expect($result)->toBeTrue();
});

test('ticket close already closed', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);
    $ticket->setStatus(SupportTicket::STATUS_CLOSED);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('closeTicket');

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_close($data);

    expect($result)->toBeTrue();
});

test('ticket create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();

    $helpdeskModel = adminHelpdeskFixture();

    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($helpdeskModel);

    $randID = 1;
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class);
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('ticketCreateForAdmin')->atLeast()->once()
        ->andReturn($randID);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'client_id' => 1,
        'content' => 'Content',
        'subject' => 'Subject',
        'support_helpdesk_id' => 1,
    ];
    $result = $api->ticket_create($data);

    expect($result)->toBeInt();
    expect($result)->toEqual($randID);
});

test('batch ticket auto close', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getExpired')->atLeast()->once()
        ->andReturn([['id' => 1], ['id' => 2]]);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('autoClose')->atLeast()->once()
        ->andReturn(true);

    $api->setService($serviceMock);
    $di = container();
    $di['logger'] = $this->createStub('\Box_Log');
    $api->setDi($di);

    $result = $api->batch_ticket_auto_close([]);

    expect($result)->toBeTrue();
});

test('batch ticket auto close not closed', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getExpired')->atLeast()->once()
        ->andReturn([['id' => 1], ['id' => 2]]);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('autoClose')->atLeast()->once();

    $api->setService($serviceMock);
    $di = container();
    $di['logger'] = $this->createStub('\Box_Log');
    $api->setDi($di);

    $result = $api->batch_ticket_auto_close([]);

    expect($result)->toBeTrue();
});

test('ticket get statuses', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $statuses = [
        SupportTicket::STATUS_OPEN => 'Open',
        SupportTicket::STATUS_ONHOLD => 'On hold',
        SupportTicket::STATUS_CLOSED => 'Closed',
    ];
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStatuses')
        ->andReturn($statuses);
    $serviceMock->shouldReceive('counter')->atLeast()->once()
        ->andReturn($statuses);

    $api->setService($serviceMock);

    $result = $api->ticket_get_statuses([]);

    expect($result)->toEqual($statuses);
});

test('ticket get statuses titles set', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $statuses = [
        SupportTicket::STATUS_OPEN => 'Open',
        SupportTicket::STATUS_ONHOLD => 'On hold',
        SupportTicket::STATUS_CLOSED => 'Closed',
    ];
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getStatuses')->atLeast()->once()
        ->andReturn($statuses);
    $serviceMock->shouldReceive('counter')
        ->andReturn($statuses);

    $api->setService($serviceMock);

    $data = [
        'titles' => true,
    ];
    $result = $api->ticket_get_statuses($data);

    expect($result)->toEqual($statuses);
});

test('helpdesk get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $qbMock = Mockery::mock(QueryBuilder::class);
    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock
    ->shouldReceive('paginateDoctrineQuery')
    ->atLeast()->once()
    ->andReturn([]);
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('getSearchQueryBuilder')
        ->atLeast()->once()
        ->andReturn($qbMock);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $di['pager'] = $paginatorMock;

    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->helpdesk_get_list($data);

    expect($result)->toBeArray();
});

test('helpdesk get pairs', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('getPairs')->atLeast()->once()
        ->andReturn([]);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->helpdesk_get_pairs($data);

    expect($result)->toBeArray();
});

test('helpdesk get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminHelpdeskFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_get($data);

    expect($result)->toBeArray();
});

test('helpdesk update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminHelpdeskFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('helpdeskUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_update($data);

    expect($result)->toBeTrue();
});

test('helpdesk create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('helpdeskCreate')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_create($data);

    expect($result)->toBeInt();
});

test('helpdesk delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminHelpdeskFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('helpdeskRm')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 'General',
    ];
    $result = $api->helpdesk_delete($data);

    expect($result)->toBeTrue();
});

test('canned get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $resultSet = [
        'list' => [
            0 => ['id' => 1],
        ],
    ];
    $qbMock = Mockery::mock(QueryBuilder::class);
    $paginatorMock = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock
    ->shouldReceive('paginateDoctrineQuery')
    ->atLeast()->once()
    ->andReturn($resultSet);
    $repoMock = Mockery::mock(CannedResponseRepository::class);
    $repoMock->shouldReceive('getSearchQueryBuilder')
        ->atLeast()->once()
        ->andReturn($qbMock);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $di['pager'] = $paginatorMock;

    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [];
    $result = $api->canned_get_list($data);

    expect($result)->toBeArray();
});

test('canned pairs', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseRepository::class);
    $repoMock->shouldReceive('getGroupedPairs')
        ->atLeast()->once()
        ->andReturn(['Category' => [1 => 'Title']]);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseRepository')
        ->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $data = [];
    $result = $api->canned_pairs();

    expect($result)->toBeArray();
});

test('canned get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedResponseFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_get($data);

    expect($result)->toBeArray();
});

test('canned delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedResponseFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('cannedRm')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_delete($data);

    expect($result)->toBeTrue();
});

test('canned create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('cannedCreate')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'title' => 'Title',
        'category_id' => random_int(1, 100),
        'content' => 'Content',
    ];
    $result = $api->canned_create($data);

    expect($result)->toBeInt();
});

test('canned update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedResponseFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('cannedUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 'Title',
    ];
    $result = $api->canned_update($data);

    expect($result)->toBeTrue();
});

test('canned category pairs', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $repoMock->shouldReceive('getPairs')
        ->atLeast()->once()
        ->andReturn([1 => 'Category 1']);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseCategoryRepository')
        ->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $api->setDi($di);
    $api->setService($serviceMock);

    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 'Title',
    ];
    $result = $api->canned_category_pairs($data);

    expect($result)->toBeArray();
});

test('canned category get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedCategoryWithResponsesFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseCategoryRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_category_get($data);

    expect($result)->toBeArray();
    expect($result['responses'])->toHaveCount(1);
});

test('canned category update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedCategoryFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseCategoryRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('cannedCategoryUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();

    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
        'title' => 'Updated Title',
    ];
    $result = $api->canned_category_update($data);

    expect($result)->toBeTrue();
});

test('canned category delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $repoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(adminSupportCannedCategoryFixture());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getCannedResponseCategoryRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('cannedCategoryRm')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_category_delete($data);

    expect($result)->toBeTrue();
});

test('canned category create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('cannedCategoryCreate')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'title' => 'Title',
    ];
    $result = $api->canned_category_create($data);

    expect($result)->toBeInt();
});

test('note create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('noteCreate')->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'ticket_id' => 1,
        'note' => 'Note',
    ];
    $result = $api->note_create($data);

    expect($result)->toBeInt();
});

test('note delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('noteRm')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('getTicketNoteById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicketNote());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(new Model_Admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->note_delete($data);

    expect($result)->toBeTrue();
});

test('task complete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('ticketTaskComplete')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $api->task_complete($data);

    expect($result)->toBeTrue();
});

test('batch delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $activityMock = Mockery::mock(Box\Mod\Support\Api\Admin::class)->makePartial();
    $activityMock->shouldReceive('ticket_delete')->atLeast()->once()->andReturn(true);

    $di = container();
    $activityMock->setDi($di);

    $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
    expect($result)->toBeTrue();
});

/*
* Knowledge Base Tests.
*/

test('kb article get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $di = container();

    $adminApi = new Box\Mod\Support\Api\Admin();
    $adminApi->setDi($di);

    $data = [
        'status' => 'status',
        'search' => 'search',
        'cat' => 'category',
    ];

    $qb = Mockery::mock(QueryBuilder::class);
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('getSearchQueryBuilder')
        ->once()
        ->with('status', 'search', 'category')
        ->andReturn($qb);

    $pager = Mockery::mock(FOSSBilling\Pagination::class);
    $pager->shouldReceive('paginateDoctrineQuery')
        ->once()
        ->with($qb, Mockery::type(FOSSBilling\PaginationOptions::class), null)
        ->andReturn(['list' => []]);
    $di['pager'] = $pager;
    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);

    $adminApi->setService($kbService);

    $result = $adminApi->kb_article_get_list($data);
    expect($result)->toBeArray();
});

test('kb article get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(adminSupportKbArticleFixture());

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $admin->id = 5;

    $di = container();
    $di['loggedin_admin'] = $admin;

    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $adminApi->setService($kbService);

    $result = $adminApi->kb_article_get($data);
    expect($result)->toBeArray();
});

test('kb article get not found exception', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);

    $di = container();

    $adminApi->setDi($di);
    $adminApi->setService($kbService);
    expect(fn (): array => $adminApi->kb_article_get($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb article create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $data = [
        'kb_article_category_id' => 1,
        'title' => 'Title',
    ];

    $id = 1;

    $di = container();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbCreateArticle')
    ->atLeast()->once()
    ->andReturn($id);
    $adminApi->setService($kbService);

    $adminApi->setDi($di);

    $result = $adminApi->kb_article_create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($id);
});

test('kb article update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $data = [
        'id' => 1,
        'kb_article_category_id' => 1,
        'title' => 'Title',
        'slug' => 'article-slug',
        'status' => 'active',
        'content' => 'Content',
        'views' => 1,
    ];

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbUpdateArticle')
    ->atLeast()->once()
    ->andReturn(true);
    $di = container();

    $adminApi->setDi($di);

    $adminApi->setService($kbService);

    $result = $adminApi->kb_article_update($data);
    expect($result)->toBeTrue();
});

test('kb article delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $data = [
        'id' => 1,
    ];

    $article = adminSupportKbArticleFixture();
    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($article);

    $di = container();

    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $kbService->shouldReceive('kbRm')->once()->with($article);
    $adminApi->setService($kbService);

    $result = $adminApi->kb_article_delete($data);
    expect($result)->toBeTrue();
});

test('kb article delete not found exception', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $di = container();

    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleRepository')
        ->once()
        ->andReturn($repo);
    $kbService->shouldReceive('kbRm')->never()
    ;
    $adminApi->setService($kbService);

    expect(fn (): bool => $adminApi->kb_article_delete(['id' => 1]))->toThrow(FOSSBilling\Exception::class);
});

test('kb category get list', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

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

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
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

    $adminApi->setDi($di);
    $adminApi->setService($kbService);

    $result = $adminApi->kb_category_get_list([]);
    expect($result)->toBeArray();
    expect($result)->toEqual($willReturn);
});

test('kb category get', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(adminSupportKbCategoryFixture());

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $adminApi->setService($kbService);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->kb_category_get($data);
    expect($result)->toBeArray();
});

test('kb category get id not set exception', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_get', []))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('kb category get not found exception', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $di = container();

    $di['validator'] = new FOSSBilling\Validate();
    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $adminApi->setService($kbService);

    $data = [
        'id' => 1,
    ];

    expect(fn (): array => $adminApi->kb_category_get($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb category create', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbCreateCategory')
    ->atLeast()->once()
    ->andReturn(1);
    $adminApi->setService($kbService);

    $data = [
        'title' => 'Title',
        'description' => 'Description',
    ];

    $di = container();

    $adminApi->setDi($di);

    $result = $adminApi->kb_category_create($data);
    expect($result)->toBeInt();
});

test('kb category update', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbUpdateCategory')
    ->atLeast()->once()
    ->andReturn(true);
    $adminApi->setService($kbService);

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(adminSupportKbCategoryFixture());
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
        'title' => 'Title',
        'slug' => 'category-slug',
        'description' => 'Description',
    ];

    $result = $adminApi->kb_category_update($data);
    expect($result)->toBeTrue();
});

test('kb category update id not set', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_update', []))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('kb category update not found', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('kbUpdateCategory')->never()
        ->andReturn(true);
    $adminApi->setService($kbService);

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
        'title' => 'Title',
        'slug' => 'category-slug',
        'description' => 'Description',
    ];

    expect(fn (): bool => $adminApi->kb_category_update($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb category delete', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbCategoryRm')
    ->atLeast()->once()
    ->andReturn(true);
    $adminApi->setService($kbService);

    $category = adminSupportKbCategoryFixture();
    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($category);
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->kb_category_delete($data);
    expect($result)->toBeTrue();
});

test('kb category delete id not set', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $dispatcher = new FOSSBilling\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_delete', []))
        ->toThrow(FOSSBilling\InformationException::class);
});

test('kb category delete not found', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('kbCategoryRm')->never()
        ->andReturn(true);
    $adminApi->setService($kbService);

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);

    $di = container();
    $di['validator'] = new FOSSBilling\Validate();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];

    expect(fn (): bool => $adminApi->kb_category_delete($data))->toThrow(FOSSBilling\Exception::class);
});

test('kb category get pairs', function (): void {
    $api = new Box\Mod\Support\Api\Admin();
    $adminApi = new Box\Mod\Support\Api\Admin();

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('getPairs')
        ->once()
        ->andReturn([]);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $adminApi->setService($kbService);

    $result = $adminApi->kb_category_get_pairs([]);
    expect($result)->toBeArray();
});
