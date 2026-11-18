<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Repository\HelpdeskRepository;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('ticket get list', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
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

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();

    $repo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $repo->shouldReceive('getSearchQueryBuilder')->andReturn($qb);

    $serviceMock->shouldReceive('getSupportTicketRepository')->andReturn($repo);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();

    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [];
    $result = $clientApi->ticket_get_list($data);

    expect($result)->toBeArray();
});

test('ticket get', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $ticket = new SupportTicket();
    \Tests\Helpers\setEntityId($ticket, 1);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'id' => 1,
    ];
    $result = $clientApi->ticket_get($data);

    expect($result)->toBeArray();
});

test('helpdesk get pairs', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('getPairs')->atLeast()->once()
        ->andReturn([0 => 'General']);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);

    $clientApi->setService($serviceMock);

    $result = $clientApi->helpdesk_get_pairs();

    expect($result)->toBeArray();
});

test('ticket create', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $repoMock = Mockery::mock(HelpdeskRepository::class);
    $repoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(new Helpdesk());
    $serviceMock->shouldReceive('getHelpdeskRepository')->atLeast()->once()
        ->andReturn($repoMock);
    $serviceMock->shouldReceive('ticketCreateForClient')->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'subject' => 'Subject',
        'support_helpdesk_id' => 1,
    ];
    $result = $clientApi->ticket_create($data);

    expect($result)->toBeInt();
});

test('ticket reply', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
        ->andReturn(1);

    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);
    $ticketRepoMock = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $ticketRepoMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($ticketRepoMock);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'id' => 1,
    ];
    $result = $clientApi->ticket_reply($data);

    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('ticket reply can not be reopened exception', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('ticketReply')
        ->andReturn(1);

    $ticket = new SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);
    $ticketRepoMock = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $ticketRepoMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($ticketRepoMock);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'id' => 1,
    ];
    expect(fn (): bool => $clientApi->ticket_reply($data))->toThrow(FOSSBilling\Core\Exception\BaseException::class);
});

test('ticket close', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn(new SupportTicket());
    $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'id' => 1,
    ];
    $result = $clientApi->ticket_close($data);

    expect($result)->toBeTrue();
});

test('ticket reply to another client ticket is a 404', function (): void {
    $clientApi = apiEndpoint(new Box\Mod\Support\Api\Client());
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldNotReceive('ticketReply');

    $ticketRepoMock = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $ticketRepoMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn(null);
    $serviceMock->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($ticketRepoMock);

    $di = container();
    $clientApi->setDi($di);

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    try {
        $clientApi->ticket_reply(['content' => 'Content', 'id' => 999]);
        expect(false)->toBeTrue('expected a 404 InformationException');
    } catch (FOSSBilling\Core\Exception\InformationException $e) {
        expect($e->getCode())->toBe(404);
    }
});

test('ticket file rules are public', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Support\Api\Guest());
    $guestApi->setDi(container());

    $rules = $guestApi->ticket_file_rules();
    expect($rules['max_files'])->toBe(5);
    expect($rules['extensions'])->toContain('pdf');
});

test('client secrets add, list and delete their own', function (): void {
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
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 1, 'status' => 'open']);

    $api = apiEndpoint(new Box\Mod\Support\Api\Client());
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['crypt'] = new FOSSBilling\Core\Security\Crypt();
    $di['mod_config'] = $di->protect(fn (): array => []);
    $api->setDi($di);
    $api->setIdentity(createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]));

    $kinds = $api->ticket_secret_kinds();
    expect($kinds)->toBe(['server' => true, 'other' => true, 'days' => 7]);

    $added = $api->ticket_secret_add(['ticket_id' => 60, 'kind' => 'other', 'label' => 'Key', 'value' => 'v']);
    expect($added['id'])->toBeGreaterThan(0);

    $list = $api->ticket_secret_list(['ticket_id' => 60]);
    expect($list['secrets'])->toHaveCount(1);

    expect($api->ticket_secret_delete(['id' => $added['id']]))->toBeTrue();
    expect($api->ticket_secret_list(['ticket_id' => 60])['secrets'])->toBe([]);
});

test('client rates a staff reply of their own ticket', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE support_ticket (id INTEGER PRIMARY KEY, client_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_message (id INTEGER PRIMARY KEY, support_ticket_id INTEGER, admin_id INTEGER)');
    $connection->executeStatement('CREATE TABLE support_ticket_message_rating (
        message_id BIGINT NOT NULL, ticket_id BIGINT NOT NULL, admin_id BIGINT NOT NULL,
        rating SMALLINT NOT NULL, rated_by_kind VARCHAR(10) NOT NULL, rated_by_id BIGINT NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT \'\', rated_at DATETIME NOT NULL, PRIMARY KEY (message_id))');
    $connection->executeStatement('CREATE TABLE support_ticket_feedback_request (
        ticket_id BIGINT NOT NULL, token CHAR(64) NOT NULL, sent_at DATETIME NOT NULL,
        answered_at DATETIME DEFAULT NULL, PRIMARY KEY (ticket_id))');
    $connection->insert('support_ticket', ['id' => 60, 'client_id' => 1]);
    $connection->insert('support_ticket_message', ['id' => 70, 'support_ticket_id' => 60, 'admin_id' => 3]);

    $api = apiEndpoint(new Box\Mod\Support\Api\Client());
    $di = container();
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $di['mod_config'] = $di->protect(fn (): array => []);
    $api->setDi($di);
    $api->setIdentity(createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]));

    $ticketRepo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $owned = new SupportTicket();
    Tests\Helpers\setEntityId($owned, 60);
    $ticketRepo->shouldReceive('findOneByClient')->with(1, 60)->andReturn($owned);
    $service = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $service->shouldReceive('getSupportTicketRepository')->andReturn($ticketRepo);
    $api->setService($service);

    expect($api->ticket_message_rate(['id' => 60, 'message_id' => 70, 'rating' => 5]))->toBeTrue();

    // Another client's ticket is a 404.
    $ticketRepo->shouldReceive('findOneByClient')->with(1, 61)->andReturn(null);
    expect(fn (): bool => $api->ticket_message_rate(['id' => 61, 'message_id' => 70, 'rating' => 5]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Ticket not found');
});
