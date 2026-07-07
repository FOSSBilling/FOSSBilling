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
use Box\Mod\Support\Repository\HelpdeskRepository;

use function Tests\Helpers\container;

test('ticket get list', function (): void {
    $clientApi = new Box\Mod\Support\Api\Client();
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

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();

    $repo = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $repo->shouldReceive('getSearchQueryBuilder')->andReturn($qb);

    $serviceMock->shouldReceive('getSupportTicketRepository')->andReturn($repo);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['em'] = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();

    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [];
    $result = $clientApi->ticket_get_list($data);

    expect($result)->toBeArray();
});

test('ticket get', function (): void {
    $clientApi = new Box\Mod\Support\Api\Client();
    $ticket = new Box\Mod\Support\Entity\SupportTicket();
    \Tests\Helpers\setEntityId($ticket, 1);

    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'id' => 1,
    ];
    $result = $clientApi->ticket_get($data);

    expect($result)->toBeArray();
});

test('helpdesk get pairs', function (): void {
    $clientApi = new Box\Mod\Support\Api\Client();
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
    $clientApi = new Box\Mod\Support\Api\Client();
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

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

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
    $clientApi = new Box\Mod\Support\Api\Client();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
        ->andReturn(true);
    $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
        ->andReturn(1);

    $ticket = new Box\Mod\Support\Entity\SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);
    $ticketRepoMock = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $ticketRepoMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($ticketRepoMock);

    $di = container();
    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

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
    $clientApi = new Box\Mod\Support\Api\Client();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
        ->andReturn(false);
    $serviceMock->shouldReceive('ticketReply')
        ->andReturn(1);

    $ticket = new Box\Mod\Support\Entity\SupportTicket();
    Tests\Helpers\setEntityId($ticket, 1);
    $ticketRepoMock = Mockery::mock(Box\Mod\Support\Repository\SupportTicketRepository::class);
    $ticketRepoMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn($ticket);
    $serviceMock->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($ticketRepoMock);

    $di = container();
    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'id' => 1,
    ];
    expect(fn (): bool => $clientApi->ticket_reply($data))->toThrow(FOSSBilling\Exception::class);
});

test('ticket close', function (): void {
    $clientApi = new Box\Mod\Support\Api\Client();
    $serviceMock = Mockery::mock(Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
        ->andReturn(new Box\Mod\Support\Entity\SupportTicket());
    $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $clientApi->setService($serviceMock);
    $clientApi->setIdentity($client);

    $data = [
        'content' => 'Content',
        'id' => 1,
    ];
    $result = $clientApi->ticket_close($data);

    expect($result)->toBeTrue();
});
