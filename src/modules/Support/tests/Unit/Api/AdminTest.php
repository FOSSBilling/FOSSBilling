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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];
    $paginatorMock = Mockery::mock(FOSSBilling\Core\Pagination\Service::class)->makePartial();
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketMessageById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicketMessage());
    $serviceMock->shouldReceive('ticketMessageUpdate')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
        'content' => 'Content',
    ];
    $result = $api->ticket_message_update($data);

    expect($result)->toBeTrue();
});

test('ticket message history get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
        'content' => 'Content',
    ];
    $result = $api->ticket_reply($data);

    expect($result)->toBeInt();
});

test('ticket close', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->ticket_close($data);

    expect($result)->toBeTrue();
});

test('ticket close already closed', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api->setIdentity(\Tests\Helpers\admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $di['logger'] = $this->createStub(FOSSBilling\Core\Logging\Logger::class);
    $api->setDi($di);

    $result = $api->batch_ticket_auto_close([]);

    expect($result)->toBeTrue();
});

test('batch ticket auto close not closed', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $di['logger'] = $this->createStub(FOSSBilling\Core\Logging\Logger::class);
    $api->setDi($di);

    $result = $api->batch_ticket_auto_close([]);

    expect($result)->toBeTrue();
});

test('ticket get statuses', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $qbMock = Mockery::mock(QueryBuilder::class);
    $paginatorMock = Mockery::mock(FOSSBilling\Core\Pagination\Service::class)->makePartial();
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_get($data);

    expect($result)->toBeArray();
});

test('helpdesk update', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_update($data);

    expect($result)->toBeTrue();
});

test('helpdesk create', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('helpdeskCreate')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->helpdesk_create($data);

    expect($result)->toBeInt();
});

test('helpdesk delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 'General',
    ];
    $result = $api->helpdesk_delete($data);

    expect($result)->toBeTrue();
});

test('canned get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $resultSet = [
        'list' => [
            0 => ['id' => 1],
        ],
    ];
    $qbMock = Mockery::mock(QueryBuilder::class);
    $paginatorMock = Mockery::mock(FOSSBilling\Core\Pagination\Service::class)->makePartial();
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_get($data);

    expect($result)->toBeArray();
});

test('canned delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_delete($data);

    expect($result)->toBeTrue();
});

test('canned create', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('cannedCreate')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'title' => 'Title',
        'category_id' => random_int(1, 100),
        'content' => 'Content',
    ];
    $result = $api->canned_create($data);

    expect($result)->toBeInt();
});

test('canned update', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 'Title',
    ];
    $result = $api->canned_update($data);

    expect($result)->toBeTrue();
});

test('canned category pairs', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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

    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 'Title',
    ];
    $result = $api->canned_category_pairs($data);

    expect($result)->toBeArray();
});

test('canned category get', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_category_get($data);

    expect($result)->toBeArray();
    expect($result['responses'])->toHaveCount(1);
});

test('canned category update', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
        'title' => 'Updated Title',
    ];
    $result = $api->canned_category_update($data);

    expect($result)->toBeTrue();
});

test('canned category delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->canned_category_delete($data);

    expect($result)->toBeTrue();
});

test('canned category create', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('noteCreate')->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'ticket_id' => 1,
        'note' => 'Note',
    ];
    $result = $api->note_create($data);

    expect($result)->toBeInt();
});

test('note create strips html from the note content', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('noteCreate')->atLeast()->once()
        ->with(
            Mockery::on(fn ($ticket): bool => $ticket instanceof SupportTicket),
            Mockery::on(fn ($identity): bool => $identity instanceof Box\Mod\Staff\Entity\Admin),
            'Note alert(1)bold text',
        )
        ->andReturn(1);
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'ticket_id' => 1,
        'note' => 'Note <script>alert(1)</script><b>bold</b> text',
    ];
    $result = $api->note_create($data);

    expect($result)->toBeInt();
});

test('note create rejects notes that are empty after sanitization', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldNotReceive('noteCreate');
    $serviceMock->shouldReceive('getTicketById')->atLeast()->once()
        ->andReturn(new SupportTicket());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'ticket_id' => 1,
        'note' => '<b></b>',
    ];

    expect(fn (): int => $api->note_create($data))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});

test('note delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('noteRm')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('getTicketNoteById')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicketNote());

    $di = container();
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(\Tests\Helpers\admin());

    $data = [
        'id' => 1,
    ];
    $result = $api->note_delete($data);

    expect($result)->toBeTrue();
});

test('task complete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $di = container();

    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());
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
        ->with(['status' => 'status', 'search' => 'search', 'kb_article_category_id' => 'category', 'sort' => null, 'direction' => null])
        ->andReturn($qb);

    $pager = Mockery::mock(FOSSBilling\Core\Pagination\Service::class);
    $pager->shouldReceive('paginateDoctrineQuery')
        ->once()
        ->with($qb, Mockery::type(FOSSBilling\Core\Pagination\Options::class), Mockery::type('object'))
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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $data = [
        'id' => 1,
    ];

    $repo = Mockery::mock(KbArticleRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(adminSupportKbArticleFixture());

    $admin = \Tests\Helpers\admin(['id' => 5]);

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    expect(fn (): array => $adminApi->kb_article_get($data))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('kb article create preserves quotes in the title', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $data = [
        'kb_article_category_id' => 1,
        'title' => "What's new",
    ];

    $id = 1;

    $di = container();

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbCreateArticle')
    ->once()
    ->with(1, "What's new", KbArticle::DRAFT, null)
    ->andReturn($id);
    $adminApi->setService($kbService);

    $adminApi->setDi($di);

    $result = $adminApi->kb_article_create($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($id);
});

test('kb article update preserves quotes in the title', function (): void {
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $data = [
        'id' => 1,
        'kb_article_category_id' => 1,
        'title' => "What's new",
        'slug' => 'article-slug',
        'status' => 'active',
        'content' => 'Content',
        'views' => 1,
    ];

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService
    ->shouldReceive('kbUpdateArticle')
    ->once()
    ->with(1, 1, "What's new", 'article-slug', 'active', 'Content', 1)
    ->andReturn(true);
    $di = container();

    $adminApi->setDi($di);

    $adminApi->setService($kbService);

    $result = $adminApi->kb_article_update($data);
    expect($result)->toBeTrue();
});

test('kb article delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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

    expect(fn (): bool => $adminApi->kb_article_delete(['id' => 1]))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('kb category get list', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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

    $pager = Mockery::mock(FOSSBilling\Core\Pagination\Service::class)->makePartial();

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(adminSupportKbCategoryFixture());

    $di = container();
    $di['validator'] = new FOSSBilling\Core\Validation\Validator();

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $dispatcher = new FOSSBilling\Core\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_get', []))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});

test('kb category get not found exception', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $repo = Mockery::mock(KbArticleCategoryRepository::class);
    $repo->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $di = container();

    $di['validator'] = new FOSSBilling\Core\Validation\Validator();
    $adminApi->setDi($di);

    $kbService = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $kbService->shouldReceive('getKbArticleCategoryRepository')
        ->once()
        ->andReturn($repo);
    $adminApi->setService($kbService);

    $data = [
        'id' => 1,
    ];

    expect(fn (): array => $adminApi->kb_category_get($data))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('kb category create', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $di['validator'] = new FOSSBilling\Core\Validation\Validator();

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
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $dispatcher = new FOSSBilling\Core\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_update', []))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});

test('kb category update not found', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $di['validator'] = new FOSSBilling\Core\Validation\Validator();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
        'title' => 'Title',
        'slug' => 'category-slug',
        'description' => 'Description',
    ];

    expect(fn (): bool => $adminApi->kb_category_update($data))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('kb category delete', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $di['validator'] = new FOSSBilling\Core\Validation\Validator();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];
    $result = $adminApi->kb_category_delete($data);
    expect($result)->toBeTrue();
});

test('kb category delete id not set', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $dispatcher = new FOSSBilling\Core\Api\Dispatcher();

    expect(fn () => $dispatcher->validateRequiredParams($adminApi, 'kb_category_delete', []))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});

test('kb category delete not found', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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
    $di['validator'] = new FOSSBilling\Core\Validation\Validator();

    $adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];

    expect(fn (): bool => $adminApi->kb_category_delete($data))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('kb category get pairs', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $adminApi = apiEndpoint(new Box\Mod\Support\Api\Admin());

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

test('ticket update assigns the ticket when assigned_to is given', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('ticketUpdate')->once()->andReturn(true);

    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 60);
    $serviceMock->shouldReceive('getTicketById')->once()->with(60)->andReturn($ticket);

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), priority INTEGER, support_helpdesk_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_helpdesk (id INTEGER PRIMARY KEY, name VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket_assignment (ticket_id BIGINT NOT NULL, admin_id BIGINT NOT NULL, assigned_by BIGINT DEFAULT NULL, assigned_at DATETIME NOT NULL, PRIMARY KEY (ticket_id))');
    $connection->insert('admin', ['id' => 3, 'name' => 'Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('support_ticket', ['id' => 60, 'subject' => 'Help', 'status' => 'open', 'priority' => 100, 'support_helpdesk_id' => 1]);
    $connection->insert('support_helpdesk', ['id' => 1, 'name' => 'General']);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->once()->andReturnTrue();
    $staff = Mockery::mock(Box\Mod\Staff\Service::class);
    $staff->shouldReceive('checkPermissionsAndThrowException')->atLeast()->once();
    $di['mod_service'] = $di->protect(fn (string $name): object => $name === 'email' ? $email : $staff);
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    expect($api->ticket_update(['id' => 60, 'assigned_to' => 3]))->toBeTrue();
    expect(Box\Mod\Support\TicketAssign::assignee($di, 60))->toBe(3);
});

test('ticket update refuses an unknown assignee', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('ticketUpdate')->once()->andReturn(true);

    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 60);
    $serviceMock->shouldReceive('getTicketById')->once()->with(60)->andReturn($ticket);

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY)');
    $connection->insert('support_ticket', ['id' => 60]);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $api->setDi($di);

    $api->setService($serviceMock);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    expect(fn (): bool => $api->ticket_update(['id' => 60, 'assigned_to' => 999]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'active staff member');
});

test('batch ticket pin and unpin round-trip', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY)');
    $connection->executeStatement('CREATE TABLE support_ticket_pin (ticket_id BIGINT NOT NULL, pinned_by BIGINT DEFAULT NULL, pinned_at DATETIME NOT NULL, PRIMARY KEY (ticket_id))');
    $connection->insert('support_ticket', ['id' => 60]);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $api->setDi($di);

    expect($api->batch_ticket_pin(['ids' => [60]]))->toBe(1);
    expect($api->ticket_pinned_ids([]))->toBe([60]);
    expect($api->batch_ticket_unpin(['ids' => [60]]))->toBe(1);
    expect($api->ticket_pinned_ids([]))->toBe([]);
});

test('batch ticket close closes only open tickets', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();

    $open = new SupportTicket();
    Tests\Helpers\setEntityId($open, 60);
    $open->setStatus(SupportTicket::STATUS_OPEN);
    $closed = new SupportTicket();
    Tests\Helpers\setEntityId($closed, 61);
    $closed->setStatus(SupportTicket::STATUS_CLOSED);

    $serviceMock->shouldReceive('getTicketById')->twice()->andReturn($open, $closed);
    $serviceMock->shouldReceive('closeTicket')->once()->with($open, Mockery::any())->andReturn(true);

    $api->setService($serviceMock);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    expect($api->batch_ticket_close(['ids' => [60, 61]]))->toBe(1);
});

test('ticket files upload, list and remove round-trip', function (): void {
    $dir = sys_get_temp_dir() . '/fb-ticket-files-' . bin2hex(random_bytes(4));
    putenv('FOSSBILLING_TICKET_FILES_DIR=' . $dir);

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER, client_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_file (
        id INTEGER PRIMARY KEY AUTOINCREMENT, token CHAR(32) NOT NULL, ticket_id BIGINT DEFAULT NULL,
        message_id BIGINT DEFAULT NULL, client_id BIGINT NOT NULL, uploader_kind VARCHAR(10) NOT NULL,
        uploader_id BIGINT NOT NULL, session_hash CHAR(64) NOT NULL, name VARCHAR(191) NOT NULL,
        mime VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL,
        attached_at DATETIME DEFAULT NULL, removed_at DATETIME DEFAULT NULL,
        removed_by BIGINT DEFAULT NULL, removed_reason VARCHAR(20) DEFAULT NULL,
        UNIQUE (token))');
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 7]);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => 1, 'client_id' => null]);

    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $path = tempnam(sys_get_temp_dir(), 'tftest');
    file_put_contents($path, 'staff notes');
    $request = Symfony\Component\HttpFoundation\Request::create('http://localhost/', 'POST', [], [], [], ['HTTP_CONTENT_TYPE' => 'multipart/form-data']);
    $request->files->set('file', new Symfony\Component\HttpFoundation\File\UploadedFile($path, 'notes.txt', 'text/plain', null, true));

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['request'] = $request;
    $api->setDi($di);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    $uploaded = $api->ticket_file_upload(['ticket_id' => 60]);
    expect($uploaded['name'])->toBe('notes.txt');

    // Bind then remove.
    $request->request->set('ticket_files', [$uploaded['id']]);
    expect(Box\Mod\Support\TicketFiles::attachFromRequest($di, 60, true))->toBe(1);
    expect($api->ticket_file_remove(['id' => $uploaded['id']]))->toBeTrue();
    expect(fn (): bool => $api->ticket_file_remove(['id' => $uploaded['id']]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'File not found');
});

test('ticket files update validates the keep period', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());
    $extension = Mockery::mock(Box\Mod\Extension\Service::class);
    $extension->shouldReceive('hasManagePermission')->atLeast()->once()->with('mod_support');
    $extension->shouldReceive('setConfig')->once()->with(['ext' => 'mod_support', 'ticket_files_days' => '30'])->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $name): object => $extension);
    $api->setDi($di);

    expect($api->ticket_files_update(['ticket_files_days' => 30]))->toBeTrue();
    expect(fn (): bool => $api->ticket_files_update(['ticket_files_days' => 99999]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'between 0 and 3650');
});

test('ticket secrets list flags open and delete rights separately', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255))');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER, status VARCHAR(30))');
    $connection->executeStatement('CREATE TABLE support_ticket_secret (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, kind VARCHAR(10) NOT NULL,
        label VARCHAR(100) NOT NULL, data TEXT NOT NULL, added_by_client_id BIGINT DEFAULT NULL,
        created_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_secret_event (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, secret_label VARCHAR(100) NOT NULL,
        action VARCHAR(10) NOT NULL, admin_id BIGINT DEFAULT NULL, client_id BIGINT DEFAULT NULL,
        created_at DATETIME NOT NULL)');
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 5, 'status' => 'open']);

    $staff = Mockery::mock(Box\Mod\Staff\Service::class);
    $staff->shouldReceive('hasPermission')->with(Mockery::any(), 'support', 'open_secrets')->andReturn(false);
    $staff->shouldReceive('hasPermission')->with(Mockery::any(), 'support', 'view')->andReturn(true);
    $staff->shouldReceive('hasPermission')->with(Mockery::any(), 'support', 'manage_tickets')->andReturn(true);
    $staff->shouldReceive('checkPermissionsAndThrowException')->byDefault()->andReturnTrue();
    $staff->shouldReceive('checkPermissionsAndThrowException')->with('support', 'open_secrets', Mockery::any(), Mockery::any())
        ->andThrow(new FOSSBilling\Core\Exception\InformationException('You do not have the permission to perform this action', [], 403));

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['crypt'] = new FOSSBilling\Core\Security\Crypt();
    $di['mod_config'] = $di->protect(fn (): array => []);
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['staff' => $staff]));
    $api->setDi($di);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    $secretId = Box\Mod\Support\TicketSecrets::addForClient($di, 5, 60, 'other', ['label' => 'Key', 'value' => 'v'])['id'];

    $list = $api->ticket_secret_list(['ticket_id' => 60]);
    expect($list['can_open'])->toBeFalse();
    expect($list['can_delete'])->toBeTrue();
    expect($list['secrets'])->toHaveCount(1);

    // Without the open permission the values stay closed.
    expect(fn (): array => $api->ticket_secret_open(['id' => $secretId]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'permission');
});

test('ticket update to closed asks for closing feedback', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30), client_id INTEGER, author_name VARCHAR(255), author_email VARCHAR(255), access_hash VARCHAR(255), support_helpdesk_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (
        ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL,
        answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, admin_id BIGINT DEFAULT NULL,
        rating SMALLINT DEFAULT NULL, comments TEXT NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\',
        created_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_helpdesk_feedback (
        helpdesk_id BIGINT NOT NULL, feedback_request TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL, PRIMARY KEY (helpdesk_id))');
    $connection->insert('support_ticket', ['id' => 60, 'subject' => 'H', 'status' => 'open', 'client_id' => 5, 'author_name' => 'A', 'author_email' => 'a@x.test', 'access_hash' => '', 'support_helpdesk_id' => 1]);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $em->shouldReceive('flush')->andReturnNull();
    $di['em'] = $em;
    $di['mod_config'] = $di->protect(fn (): array => []);
    $emails = [];
    $email = Mockery::mock();
    $email->shouldReceive('sendTemplate')->andReturnUsing(function (array $data) use (&$emails): bool {
        $emails[] = $data;

        return true;
    });
    $di['mod_service'] = $di->protect(Tests\Helpers\moduleService(['email' => $email]));
    $url = Mockery::mock(FOSSBilling\Core\Url::class);
    $url->shouldReceive('link')->andReturnUsing(fn (string $path): string => 'https://billing.example/' . $path);
    $di['url'] = $url;
    $api->setDi($di);
    $api->setIdentity(Tests\Helpers\admin(['id' => 1]));

    $service = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $ticketModel = new SupportTicket();
    Tests\Helpers\setEntityId($ticketModel, 60);
    $ticketModel->setStatus(SupportTicket::STATUS_OPEN);
    $service->shouldReceive('getTicketById')->with(60)->andReturn($ticketModel);
    $service->shouldReceive('ticketUpdate')->andReturnUsing(function (SupportTicket $model) use ($connection): bool {
        $model->setStatus(SupportTicket::STATUS_CLOSED);
        $connection->update('support_ticket', ['status' => SupportTicket::STATUS_CLOSED], ['id' => 60]);

        return true;
    });
    $api->setService($service);

    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 60);
    $ticket->setStatus(SupportTicket::STATUS_OPEN);

    expect($api->ticket_update(['id' => 60, 'status' => SupportTicket::STATUS_CLOSED]))->toBeTrue();
    expect($emails)->toHaveCount(1);
    expect($emails[0]['code'])->toBe('mod_support_ticket_feedback_request');
});

test('feedback report lists ratings and comments', function (): void {
    $api = apiEndpoint(new Box\Mod\Support\Api\Admin());

    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE admin (id INTEGER PRIMARY KEY, name VARCHAR(255), status VARCHAR(30), system_name VARCHAR(100) DEFAULT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, subject VARCHAR(255), status VARCHAR(30))');
    $connection->executeStatement('CREATE TABLE support_ticket_message_rating (
        message_id BIGINT NOT NULL, ticket_id BIGINT NOT NULL, admin_id BIGINT NOT NULL,
        rating SMALLINT NOT NULL, rated_by_kind VARCHAR(10) NOT NULL, rated_by_id BIGINT NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT \'\', rated_at DATETIME NOT NULL, PRIMARY KEY (message_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback (
        id INTEGER PRIMARY KEY AUTOINCREMENT, ticket_id BIGINT NOT NULL, admin_id BIGINT DEFAULT NULL,
        rating SMALLINT DEFAULT NULL, comments TEXT NOT NULL, ip VARCHAR(45) NOT NULL DEFAULT \'\',
        created_at DATETIME NOT NULL)');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (
        ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL,
        answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->insert('admin', ['id' => 3, 'name' => 'Agent', 'status' => 'active', 'system_name' => null]);
    $connection->insert('support_ticket', ['id' => 60]);
    $connection->insert('support_ticket_message_rating', ['message_id' => 70, 'ticket_id' => 60, 'admin_id' => 3, 'rating' => 5, 'rated_by_kind' => 'client', 'rated_by_id' => 1, 'ip' => '', 'rated_at' => date('Y-m-d H:i:s')]);

    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $api->setDi($di);

    $report = $api->ticket_feedback_report(['period' => 'bogus']);
    expect($report['period'])->toBe('90');
    expect($report['ratings'][0]['admin_id'])->toBe(3);
    expect($report['ratings'][0]['average'])->toBe(5.0);
});
