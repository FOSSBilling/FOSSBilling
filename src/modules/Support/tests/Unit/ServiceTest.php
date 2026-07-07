<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Service as ClientService;
use Box\Mod\Email\Service as EmailService;
use Box\Mod\Support\Entity\CannedResponse;
use Box\Mod\Support\Entity\CannedResponseCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\Entity\SupportTicketMessage;
use Box\Mod\Support\Entity\SupportTicketMessageHistory;
use Box\Mod\Support\Entity\SupportTicketNote;
use Box\Mod\Support\Repository\CannedResponseCategoryRepository;
use Box\Mod\Support\Repository\CannedResponseRepository;
use Box\Mod\Support\Repository\HelpdeskRepository;
use Box\Mod\Support\Repository\KbArticleCategoryRepository;
use Box\Mod\Support\Repository\KbArticleRepository;
use Box\Mod\Support\Repository\SupportTicketMessageHistoryRepository;
use Box\Mod\Support\Repository\SupportTicketMessageRepository;
use Box\Mod\Support\Repository\SupportTicketNoteRepository;
use Box\Mod\Support\Repository\SupportTicketRepository;
use Box\Mod\Support\Service;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\setEntityId;

function supportClientFixture(): Model_Client
{
    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;
    $client->first_name = 'Client';
    $client->last_name = 'Name';
    $client->email = 'client@example.com';

    return $client;
}

function supportSetEntityId(object $entity, int $id): void
{
    $property = new ReflectionProperty($entity, 'id');
    $property->setValue($entity, $id);
}

function supportKbCategoryFixture(): KbArticleCategory
{
    $category = (new KbArticleCategory())
        ->setTitle('category-title')
        ->setSlug('category-slug')
        ->setDescription('Description');
    $category->setCreatedAt(new DateTime('2013-01-01 12:00:00'));
    $category->setUpdatedAt(new DateTime('2014-01-01 12:00:00'));
    supportSetEntityId($category, 1);

    return $category;
}

function supportKbArticleFixture(): KbArticle
{
    $article = (new KbArticle())
        ->setCategory(supportKbCategoryFixture())
        ->setTitle('Title')
        ->setSlug('article-slug')
        ->setViews(1)
        ->setContent('Content')
        ->setStatus(KbArticle::ACTIVE);
    $article->setCreatedAt(new DateTime('2013-01-01 12:00:00'));
    $article->setUpdatedAt(new DateTime('2014-01-01 12:00:00'));
    supportSetEntityId($article, 1);

    return $article;
}

function supportCannedCategoryFixture(): CannedResponseCategory
{
    $category = (new CannedResponseCategory())
        ->setTitle('General');
    $category->setCreatedAt(new DateTime('2013-01-01 12:00:00'));
    $category->setUpdatedAt(new DateTime('2014-01-01 12:00:00'));
    supportSetEntityId($category, 1);

    return $category;
}

function supportCannedResponseFixture(): CannedResponse
{
    $response = (new CannedResponse())
        ->setCategory(supportCannedCategoryFixture())
        ->setTitle('Name')
        ->setContent('Content');
    $response->setCreatedAt(new DateTime('2013-01-01 12:00:00'));
    $response->setUpdatedAt(new DateTime('2014-01-01 12:00:00'));
    supportSetEntityId($response, 1);

    return $response;
}

function helpdeskFixture(): Helpdesk
{
    $helpdesk = (new Helpdesk())
        ->setName('General')
        ->setEmail('support@example.com')
        ->setCanReopen(true)
        ->setCloseAfter(24)
        ->setSignature('Signature');
    $helpdesk->setCreatedAt(new DateTime('2013-01-01 12:00:00'));
    $helpdesk->setUpdatedAt(new DateTime('2014-01-01 12:00:00'));
    supportSetEntityId($helpdesk, 1);

    return $helpdesk;
}

function supportWireKbRepositories(EntityManagerInterface $em, ?KbArticleRepository $articleRepo = null, ?KbArticleCategoryRepository $categoryRepo = null, ?CannedResponseRepository $cannedRepo = null, ?CannedResponseCategoryRepository $cannedCategoryRepo = null, ?HelpdeskRepository $helpdeskRepo = null, ?SupportTicketRepository $supportTicketRepo = null, ?SupportTicketMessageRepository $supportTicketMessageRepo = null, ?SupportTicketNoteRepository $supportTicketNoteRepo = null, ?SupportTicketMessageHistoryRepository $supportTicketMessageHistoryRepo = null): void
{
    $articleRepo ??= Mockery::mock(KbArticleRepository::class)->shouldIgnoreMissing();
    $categoryRepo ??= Mockery::mock(KbArticleCategoryRepository::class)->shouldIgnoreMissing();
    $cannedRepo ??= Mockery::mock(CannedResponseRepository::class)->shouldIgnoreMissing();
    $cannedCategoryRepo ??= Mockery::mock(CannedResponseCategoryRepository::class)->shouldIgnoreMissing();
    $helpdeskRepo ??= Mockery::mock(HelpdeskRepository::class)->shouldIgnoreMissing();
    $supportTicketRepo ??= Mockery::mock(SupportTicketRepository::class)->shouldIgnoreMissing();
    $supportTicketMessageRepo ??= Mockery::mock(SupportTicketMessageRepository::class)->shouldIgnoreMissing();
    $supportTicketNoteRepo ??= Mockery::mock(SupportTicketNoteRepository::class)->shouldIgnoreMissing();
    $supportTicketMessageHistoryRepo ??= Mockery::mock(SupportTicketMessageHistoryRepository::class)->shouldIgnoreMissing();

    $em->shouldReceive('getRepository')
        ->with(KbArticle::class)
        ->byDefault()
        ->andReturn($articleRepo);
    $em->shouldReceive('getRepository')
        ->with(KbArticleCategory::class)
        ->byDefault()
        ->andReturn($categoryRepo);
    $em->shouldReceive('getRepository')
        ->with(CannedResponse::class)
        ->byDefault()
        ->andReturn($cannedRepo);
    $em->shouldReceive('getRepository')
        ->with(CannedResponseCategory::class)
        ->byDefault()
        ->andReturn($cannedCategoryRepo);
    $em->shouldReceive('getRepository')
        ->with(Helpdesk::class)
        ->byDefault()
        ->andReturn($helpdeskRepo);
    $em->shouldReceive('getRepository')
        ->with(SupportTicket::class)
        ->byDefault()
        ->andReturn($supportTicketRepo);
    $em->shouldReceive('getRepository')
        ->with(SupportTicketMessage::class)
        ->byDefault()
        ->andReturn($supportTicketMessageRepo);
    $em->shouldReceive('getRepository')
        ->with(SupportTicketNote::class)
        ->byDefault()
        ->andReturn($supportTicketNoteRepo);
    $em->shouldReceive('getRepository')
        ->with(SupportTicketMessageHistory::class)
        ->byDefault()
        ->andReturn($supportTicketMessageHistoryRepo);
}

/*
 * Dependency Injection Tests
 */

test('gets and sets dependency injection container', function (): void {
    $service = new Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

/*
 * Event Handler Tests
 */

test('handles after client open ticket event', function (): void {
    $service = new Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new SupportTicket();
    setEntityId($supportTicketModel, 1);
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_support_ticket_open'))
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'support') {
            return $serviceMock;
        }
    });
    $di['loggedin_client'] = new Model_Client();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterClientOpenTicket($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin open ticket event', function (): void {
    $service = new Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new SupportTicket();
    setEntityId($supportTicketModel, 1);
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_support_ticket_staff_open'))
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'support') {
            return $serviceMock;
        }
    });
    $di['loggedin_admin'] = new Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminOpenTicket($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin close ticket event', function (): void {
    $service = new Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new SupportTicket();
    setEntityId($supportTicketModel, 1);
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_support_ticket_staff_close'))
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'support') {
            return $serviceMock;
        }
    });
    $di['loggedin_admin'] = new Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminCloseTicket($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin reply ticket event', function (): void {
    $service = new Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new SupportTicket();
    setEntityId($supportTicketModel, 1);
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_support_ticket_staff_reply'))
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'support') {
            return $serviceMock;
        }
    });
    $di['loggedin_admin'] = new Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminReplyTicket($eventMock);
    expect($result)->toBeNull();
});

test('handles guest ticket with regular client open event', function (): void {
    $service = new Service();
    $toApiArrayReturn = [
        'author_email' => 'email@example.com',
        'author_name' => 'Name',
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportPTicketModel = new SupportTicket();
    setEntityId($supportPTicketModel, 1);
    $supportPTicketModel->setClientId(null);
    $supportPTicketModel->setAccessHash('guest-ticket-hash');
    $supportPTicketModel->setAuthorEmail('email@example.com');
    $supportPTicketModel->setAuthorName('Name');
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
        ->with(Mockery::on(fn ($email): bool => $email['code'] === 'mod_support_ticket_open' && $email['to'] === 'email@example.com'))
        ->andReturn(true);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName) use ($emailServiceMock, $serviceMock) {
        if ($serviceName == 'email') {
            return $emailServiceMock;
        }
        if ($serviceName == 'support') {
            return $serviceMock;
        }
    });
    $di['loggedin_client'] = static function (): void {
        throw new Exception('Client is not logged in');
    };
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterClientOpenTicket($eventMock);
    expect($result)->toBeNull();
});

/*
 * Ticket Tests
 */

test('gets ticket by id', function (): void {
    $service = new Service();
    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findOneByIdOrFail')
        ->atLeast()->once()
        ->with(1)
        ->andReturn($ticket);
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getTicketById(1);
    expect($result)->toBeInstanceOf(SupportTicket::class);
});

test('gets statuses', function (): void {
    $service = new Service();
    $result = $service->getStatuses();
    expect($result)->toBeArray();
});

test('finds one by client', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new SupportTicket();
    setEntityId($supportTicketModel, 1);
    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findOneByClientOrFail')->atLeast()->once()
        ->andReturn($supportTicketModel);
    $service->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($repo);

    $di = container();
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $result = $service->findOneByClient($client, 1);
    expect($result)->toBeInstanceOf(SupportTicket::class);
});

test('throws exception when ticket not found by client', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findOneByClientOrFail')->atLeast()->once()
        ->andThrow(new FOSSBilling\InformationException('Ticket not found'));
    $service->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($repo);

    $di = container();
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $service->findOneByClient($client, 1);
})->throws(FOSSBilling\InformationException::class);

test('counts tickets', function (): void {
    $service = new Service();
    $arr = [
        SupportTicket::STATUS_OPEN => 1,
        SupportTicket::STATUS_ONHOLD => 1,
        SupportTicket::STATUS_CLOSED => 1,
    ];

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('countGroupedByStatus')
        ->atLeast()->once()
        ->andReturn($arr);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe(array_sum($arr));
});

test('gets latest tickets', function (): void {
    $service = new Service();
    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('findLatest')
        ->atLeast()->once()
        ->andReturn([$ticket, $ticket]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getLatest();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(SupportTicket::class);
});

test('gets expired tickets', function (): void {
    $service = new Service();

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('findExpiredOnHold')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getExpired();
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('counts by status', function (): void {
    $service = new Service();

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('countByStatus')
        ->atLeast()->once()
        ->andReturn(1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->countByStatus('open');
    expect($result)->toBeInt();
});

test('gets active tickets count for order', function (): void {
    $service = new Service();

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('countActiveTicketsForOrder')
        ->atLeast()->once()
        ->andReturn(1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $order = new Model_ClientOrder();
    $order->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->getSupportTicketRepository()->countActiveTicketsForOrder((int) $order->id);
    expect($result)->toBeInt();
});

test('checks if task already exists returns true', function (): void {
    $service = new Service();

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('hasPendingTaskForClient')
        ->atLeast()->once()
        ->andReturn(true);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->checkIfTaskAlreadyExists($client, 1, SupportTicket::REL_TYPE_ORDER, SupportTicket::REL_TASK_UPGRADE);
    expect($result)->toBeTrue();
});

test('checks if task already exists returns false', function (): void {
    $service = new Service();

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('hasPendingTaskForClient')
        ->atLeast()->once()
        ->andReturn(false);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->checkIfTaskAlreadyExists($client, 1, SupportTicket::REL_TYPE_ORDER, SupportTicket::REL_TASK_CANCEL);
    expect($result)->toBeFalse();
});

dataset('closeTicketIdentities', [
    [new Model_Admin()],
    [new Model_Client()],
]);

test('closes a ticket', function ($identity): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->closeTicket($ticket, $identity);
    expect($result)->toBeTrue();
    expect($ticket->getStatus())->toBe(SupportTicket::STATUS_CLOSED);
})->with('closeTicketIdentities');

test('auto closes a ticket', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->autoClose($ticket);
    expect($result)->toBeTrue();
    expect($ticket->getStatus())->toBe(SupportTicket::STATUS_CLOSED);
});

test('checks if ticket can be reopened when not closed', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock('\Box_Database');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('checks if ticket can be reopened', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $ticket->setStatus(SupportTicket::STATUS_CLOSED);
    $helpdesk = new Helpdesk();
    setEntityId($helpdesk, 1);
    $helpdesk->setCanReopen(true);
    $ticket->setSupportHelpdesk($helpdesk);

    $result = $service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('removes tickets by client', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $model = new SupportTicket();
    setEntityId($model, 1);

    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findByClientId')->atLeast()->once()
        ->andReturn([$model]);
    $service->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($repo);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());

    $result = $service->rmByClient($client);
    expect($result)->toBeNull();
});

test('removes a ticket, its messages, and their edit history', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $note = new SupportTicketNote();
    setEntityId($note, 1);
    $message = new SupportTicketMessage();
    setEntityId($message, 1);
    $history = new SupportTicketMessageHistory();
    setEntityId($history, 1);

    $noteRepo = Mockery::mock(SupportTicketNoteRepository::class);
    $noteRepo->shouldReceive('findByTicketId')->atLeast()->once()
        ->andReturn([$note]);
    $messageRepo = Mockery::mock(SupportTicketMessageRepository::class);
    $messageRepo->shouldReceive('findByTicketId')->atLeast()->once()
        ->andReturn([$message]);
    $historyRepo = Mockery::mock(SupportTicketMessageHistoryRepository::class);
    $historyRepo->shouldReceive('findByMessageId')->atLeast()->once()
        ->with(1)
        ->andReturn([$history]);
    $service->shouldReceive('getSupportTicketNoteRepository')->atLeast()->once()
        ->andReturn($noteRepo);
    $service->shouldReceive('getSupportTicketMessageRepository')->atLeast()->once()
        ->andReturn($messageRepo);
    $service->shouldReceive('getSupportTicketMessageHistoryRepository')->atLeast()->once()
        ->andReturn($historyRepo);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $removed = [];
    $emMock->shouldReceive('remove')->atLeast()->once()
        ->with(Mockery::on(function ($entity) use (&$removed): bool {
            $removed[] = $entity;

            return true;
        }));
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->rm($ticket);
    expect($result)->toBeTrue();
    expect($removed)->toContain($note, $message, $history, $ticket);
});

test('converts ticket to api array', function (): void {
    $service = new Service();
    $helpdesk = helpdeskFixture();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAssociative')
        ->byDefault()
        ->andReturn(['id' => 1, 'first_name' => 'Client', 'last_name' => 'Name', 'email' => 'client@example.com']);

    $ticketMessages = [new SupportTicketMessage(), new SupportTicketMessage()];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('messageGetRepliesCount')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $messageRepo = Mockery::mock(SupportTicketMessageRepository::class)->shouldIgnoreMissing();
    $messageRepo->shouldReceive('findByTicketId')
        ->atLeast()->once()
        ->andReturn($ticketMessages);
    $serviceMock->shouldReceive('getSupportTicketMessageRepository')
        ->atLeast()->once()
        ->andReturn($messageRepo);
    $serviceMock->shouldReceive('noteToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $noteRepo = Mockery::mock(SupportTicketNoteRepository::class);
    $note = new SupportTicketNote();
    setEntityId($note, 1);
    $noteRepo->shouldReceive('findByTicketId')->atLeast()->once()
        ->andReturn([$note]);
    $serviceMock->shouldReceive('getSupportTicketNoteRepository')->atLeast()->once()
        ->andReturn($noteRepo);
    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->byDefault()
        ->andReturn([]);
    $helpdeskRepo = Mockery::mock(HelpdeskRepository::class);
    $helpdeskRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($helpdesk);
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock, helpdeskRepo: $helpdeskRepo);
    $di = container();
    $di['dbal'] = $dbalMock;
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $clientServiceMock);
    $serviceMock->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $helpdesk = new Helpdesk();
    setEntityId($helpdesk, 1);
    $ticket->setSupportHelpdesk($helpdesk);
    $ticket->setClientId(1);

    $result = $serviceMock->toApiArray($ticket, true, new Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toHaveKey('replies');
    expect($result)->toHaveKey('helpdesk');
    expect($result)->toHaveKey('messages');
    expect($result['author'])->toMatchArray([
        'id' => 1,
        'name' => 'Client Name',
        'first_name' => 'Client',
        'last_name' => 'Name',
        'email' => 'client@example.com',
        'role' => 'client',
    ]);
    expect(count($result['messages']))->toBe(count($ticketMessages));
});

test('converts ticket to api array with rel details', function (): void {
    $service = new Service();
    $dbMock = Mockery::mock('\Box_Database')->shouldIgnoreMissing();

    $callCount = 0;
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$callCount) {
            ++$callCount;

            return supportClientFixture();
        });

    $dbMock->shouldReceive('toArray')
        ->byDefault()
        ->andReturn([]);

    $ticketMessages = [new SupportTicketMessage(), new SupportTicketMessage()];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('messageGetRepliesCount')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $messageRepo = Mockery::mock(SupportTicketMessageRepository::class)->shouldIgnoreMissing();
    $messageRepo->shouldReceive('findByTicketId')
        ->atLeast()->once()
        ->andReturn($ticketMessages);
    $serviceMock->shouldReceive('getSupportTicketMessageRepository')
        ->atLeast()->once()
        ->andReturn($messageRepo);
    $serviceMock->shouldReceive('noteToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $noteRepo = Mockery::mock(SupportTicketNoteRepository::class);
    $note = new SupportTicketNote();
    setEntityId($note, 1);
    $noteRepo->shouldReceive('findByTicketId')->atLeast()->once()
        ->andReturn([$note]);
    $serviceMock->shouldReceive('getSupportTicketNoteRepository')->atLeast()->once()
        ->andReturn($noteRepo);
    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->byDefault()
        ->andReturn([]);
    $helpdeskRepo = Mockery::mock(HelpdeskRepository::class);
    $helpdeskRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(helpdeskFixture());
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock, helpdeskRepo: $helpdeskRepo);
    $di = container();
    $di['db'] = $dbMock;
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $clientServiceMock);
    $serviceMock->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $helpdesk = new Helpdesk();
    setEntityId($helpdesk, 1);
    $ticket->setSupportHelpdesk($helpdesk);
    $ticket->setClientId(1);
    $ticket->setRelId(1);
    $ticket->setRelType('Type');

    $result = $serviceMock->toApiArray($ticket, true, new Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toHaveKey('replies');
    expect($result)->toHaveKey('helpdesk');
    expect($result)->toHaveKey('messages');
    expect(count($result['messages']))->toBe(count($ticketMessages));
});

/*
 * Canned Response Tests
 */

test('canned rm', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('remove')
        ->atLeast()->once()
        ->andReturn(null);
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedRm(supportCannedResponseFixture());
    expect($result)->toBeTrue();
});

test('canned category rm', function (): void {
    $service = new Service();
    $cannedRepoMock = Mockery::mock(CannedResponseRepository::class);
    $cannedRepoMock->shouldReceive('countByCategoryId')
        ->atLeast()->once()
        ->andReturn(0);
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('remove')
        ->atLeast()->once()
        ->andReturn(null);
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock, cannedRepo: $cannedRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedCategoryRm(supportCannedCategoryFixture());
    expect($result)->toBeTrue();
});

test('canned create', function (): void {
    $service = new Service();
    $categoryRepoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(supportCannedCategoryFixture());
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (CannedResponse $model): void {
            supportSetEntityId($model, 1);
        });
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock, cannedCategoryRepo: $categoryRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedCreate('Name', 1, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual(1);
});

test('canned update', function (): void {
    $service = new Service();
    $categoryRepoMock = Mockery::mock(CannedResponseCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(supportCannedCategoryFixture());
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock, cannedCategoryRepo: $categoryRepoMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = supportCannedResponseFixture();

    $data = [
        'category_id' => 1,
        'title' => 'Updated Title',
        'content' => 'Updated Content',
    ];

    $result = $service->cannedUpdate($model, $data);
    expect($result)->toBeTrue();
    expect($model->getTitle())->toBe('Updated Title');
});

test('canned category create', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (CannedResponseCategory $model): void {
            supportSetEntityId($model, 1);
        });
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedCategoryCreate('Name');
    expect($result)->toBeInt();
    expect($result)->toEqual(1);
});

test('canned category update', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);
    supportWireKbRepositories($emMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = supportCannedCategoryFixture();

    $result = $service->cannedCategoryUpdate($model, 'Title');
    expect($result)->toBeTrue();
    expect($model->getTitle())->toBe('Title');
});

/*
 * Helpdesk Tests
 */

test('helpdesk rm', function (): void {
    $service = new Service();
    $repo = Mockery::mock(HelpdeskRepository::class);
    $repo->shouldReceive('countTickets')
        ->atLeast()->once()
        ->andReturn(0);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, helpdeskRepo: $repo);
    $emMock->shouldReceive('remove')
        ->atLeast()->once()
        ->andReturn(null);
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->helpdeskRm(helpdeskFixture());
    expect($result)->toBeTrue();
});

test('helpdesk rm has tickets exception', function (): void {
    $service = new Service();
    $repo = Mockery::mock(HelpdeskRepository::class);
    $repo->shouldReceive('countTickets')
        ->atLeast()->once()
        ->andReturn(1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, helpdeskRepo: $repo);
    $emMock->shouldReceive('remove')
        ->never();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->helpdeskRm(helpdeskFixture());
});

test('helpdesk update', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $helpdesk = helpdeskFixture();

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'can_reopen' => 1,
        'close_after' => 1,
        'signature' => 'Signature',
    ];

    $result = $service->helpdeskUpdate($helpdesk, $data);
    expect($result)->toBeTrue();
    expect($helpdesk->getName())->toBe('Name');
});

test('helpdesk create', function (): void {
    $service = new Service();
    $randId = 1;

    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')
        ->atLeast()->once()
        ->andReturnUsing(function (Helpdesk $helpdesk) use ($randId): void {
            supportSetEntityId($helpdesk, $randId);
        });
    $emMock->shouldReceive('flush')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'can_reopen' => 1,
        'close_after' => 1,
        'signature' => 'Signature',
    ];

    $result = $service->helpdeskCreate($data);
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

/*
 * Knowledge Base Tests
 */

test('kb rm', function (): void {
    $service = new Service();
    $modelKb = supportKbArticleFixture()->setViews(10);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('remove')->once()->with($modelKb);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbRm($modelKb);
    expect($result)->toBeNull();
});

dataset('kbArticleToApiArrayProvider', function () {
    $model = supportKbArticleFixture();
    $category = supportKbCategoryFixture();

    return [
        'shallow without admin' => [
            $model,
            [
                'id' => $model->getId(),
                'slug' => $model->getSlug(),
                'title' => $model->getTitle(),
                'views' => $model->getViews(),
                'created_at' => '2013-01-01 12:00:00',
                'updated_at' => '2014-01-01 12:00:00',
                'category' => [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'title' => $category->getTitle(),
                ],
                'status' => $model->getStatus(),
            ],
            false,
            null,
        ],
        'with content without admin' => [
            $model,
            [
                'id' => $model->getId(),
                'slug' => $model->getSlug(),
                'title' => $model->getTitle(),
                'views' => $model->getViews(),
                'created_at' => '2013-01-01 12:00:00',
                'updated_at' => '2014-01-01 12:00:00',
                'category' => [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'title' => $category->getTitle(),
                ],
                'content' => $model->getContent(),
                'status' => $model->getStatus(),
            ],
            true,
            null,
        ],
        'with content and admin' => [
            $model,
            [
                'id' => $model->getId(),
                'slug' => $model->getSlug(),
                'title' => $model->getTitle(),
                'views' => $model->getViews(),
                'created_at' => '2013-01-01 12:00:00',
                'updated_at' => '2014-01-01 12:00:00',
                'category' => [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'title' => $category->getTitle(),
                ],
                'content' => $model->getContent(),
                'status' => $model->getStatus(),
                'kb_article_category_id' => $model->getKbArticleCategoryId(),
            ],
            true,
            new Model_Admin(),
        ],
        'views disabled' => [
            $model,
            [
                'id' => $model->getId(),
                'slug' => $model->getSlug(),
                'title' => $model->getTitle(),
                'created_at' => '2013-01-01 12:00:00',
                'status' => $model->getStatus(),
                'updated_at' => '2014-01-01 12:00:00',
                'category' => [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'title' => $category->getTitle(),
                ],
            ],
            false,
            null,
            false,
        ],
    ];
});

test('kb to api array', function (KbArticle $model, array $expected, bool $includeContent, ?Model_Admin $identity, bool $includeViews = true): void {
    $result = $model->toApiArray($identity, $includeContent, $includeViews);
    expect($result)->toEqual($expected);
})->with('kbArticleToApiArrayProvider');

test('kb article view increment does not update timestamp', function (): void {
    $model = supportKbArticleFixture();

    $updatedAt = $model->getUpdatedAt()?->format('Y-m-d H:i:s');
    $model->incrementViews();

    expect($model->getViews())->toBe(2)
        ->and($model->getUpdatedAt()?->format('Y-m-d H:i:s'))->toBe($updatedAt);
});

test('kb suggestions enabled checks kb setting and area setting', function (): void {
    $service = new Service();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('getConfig')
        ->with('mod_support')
        ->andReturn([
            'kb_enable' => true,
            'kb_suggestions_ticket' => 'on',
        ]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $serviceName, ?string $sub = null): Box\Mod\Extension\Service => $extensionService);
    $service->setDi($di);

    expect($service->kbSuggestionsEnabled('ticket'))->toBeTrue()
        ->and($service->kbSuggestionsEnabled('contact'))->toBeFalse();
});

test('kb article views enabled defaults on and can be disabled', function (array $config, bool $expected): void {
    $service = new Service();

    $extensionService = Mockery::mock(Box\Mod\Extension\Service::class);
    $extensionService->shouldReceive('getConfig')
        ->with('mod_support')
        ->andReturn($config);

    $di = container();
    $di['mod_service'] = $di->protect(fn (string $serviceName, ?string $sub = null): Box\Mod\Extension\Service => $extensionService);
    $service->setDi($di);

    expect($service->kbArticleViewsEnabled())->toBe($expected);
})->with([
    'unset' => [[], true],
    'disabled' => [['kb_article_views_enable' => 'false'], false],
]);

test('kb create article', function (): void {
    $service = new Service();
    $randId = 1;
    $category = supportKbCategoryFixture();

    $categoryRepoMock = Mockery::mock(KbArticleCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($category);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, categoryRepo: $categoryRepoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticleCategory::class)
        ->andReturn($categoryRepoMock);
    $emMock->shouldReceive('persist')
        ->once()
        ->with(Mockery::on(static fn (KbArticle $article): bool => $article->getCategory() === $category))
        ->andReturnUsing(static function (KbArticle $article) use ($randId): void {
            supportSetEntityId($article, $randId);
        });
    $emMock->shouldReceive('flush')->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('slug')
        ->atLeast()->once()
        ->andReturn('article-slug');

    $di = container();
    $di['em'] = $emMock;
    $di['tools'] = $toolsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbCreateArticle(1, 'Title', 'Active', 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('kb create article category not found exception', function (): void {
    $service = new Service();

    $categoryRepoMock = Mockery::mock(KbArticleCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, categoryRepo: $categoryRepoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticleCategory::class)
        ->andReturn($categoryRepoMock);
    $emMock->shouldReceive('persist')->never();
    $emMock->shouldReceive('flush')->never();

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbCreateArticle(1, 'Title', 'Active', 'Content');
});

test('kb create article invalid status exception', function (): void {
    $service = new Service();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->never();
    $emMock->shouldReceive('flush')->never();

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbCreateArticle(1, 'Title', 'invalid', 'Content');
});

test('kb update article', function (): void {
    $service = new Service();
    $randId = 1;
    $model = supportKbArticleFixture();

    $repoMock = Mockery::mock(KbArticleRepository::class);
    $repoMock->shouldReceive('find')
        ->once()
        ->with($randId)
        ->andReturn($model);

    $category = supportKbCategoryFixture();
    $categoryRepoMock = Mockery::mock(KbArticleCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn($category);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, $repoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticle::class)
        ->andReturn($repoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticleCategory::class)
        ->andReturn($categoryRepoMock);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbUpdateArticle($randId, 1, 'Title', 'article-slug', 'active', 'content', 1);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
    expect($model->getContent())->toBe('content');
});

test('kb update article category not found exception', function (): void {
    $service = new Service();
    $randId = 1;

    $repoMock = Mockery::mock(KbArticleRepository::class);
    $repoMock->shouldReceive('find')
        ->once()
        ->with($randId)
        ->andReturn(supportKbArticleFixture());

    $categoryRepoMock = Mockery::mock(KbArticleCategoryRepository::class);
    $categoryRepoMock->shouldReceive('find')
        ->once()
        ->with(1)
        ->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, $repoMock, $categoryRepoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticle::class)
        ->andReturn($repoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticleCategory::class)
        ->andReturn($categoryRepoMock);
    $emMock->shouldReceive('flush')->never();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbUpdateArticle($randId, 1, 'Title', 'article-slug', 'active', 'content', 1);
});

test('kb update article invalid status exception', function (): void {
    $service = new Service();

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->never();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbUpdateArticle(1, null, null, null, 'invalid');
});

test('kb update article not found exception', function (): void {
    $service = new Service();
    $randId = 1;

    $repoMock = Mockery::mock(KbArticleRepository::class);
    $repoMock->shouldReceive('find')
        ->once()
        ->with($randId)
        ->andReturn(null);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, $repoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticle::class)
        ->andReturn($repoMock);
    $emMock->shouldReceive('flush')->never();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbUpdateArticle($randId, 1, 'Title', 'article-slug', 'active', 'content', 1);
});

test('kb category rm', function (): void {
    $service = new Service();
    $model = supportKbCategoryFixture();

    $articleRepoMock = Mockery::mock(KbArticleRepository::class);
    $articleRepoMock->shouldReceive('countByCategoryId')
        ->once()
        ->with(1)
        ->andReturn(0);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, $articleRepoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticle::class)
        ->andReturn($articleRepoMock);
    $emMock->shouldReceive('remove')->once()->with($model);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbCategoryRm($model);
    expect($result)->toBeTrue();
});

test('kb category rm has articles exception', function (): void {
    $service = new Service();
    $articleRepoMock = Mockery::mock(KbArticleRepository::class);
    $articleRepoMock->shouldReceive('countByCategoryId')
        ->once()
        ->with(1)
        ->andReturn(1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, $articleRepoMock);
    $emMock->shouldReceive('getRepository')
        ->once()
        ->with(KbArticle::class)
        ->andReturn($articleRepoMock);
    $emMock->shouldReceive('remove')->never();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = supportKbCategoryFixture();

    $this->expectException(FOSSBilling\Exception::class);
    $service->kbCategoryRm($model);
});

test('kb create category', function (): void {
    $service = new Service();
    $randId = 1;

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')
        ->once()
        ->with(Mockery::type(KbArticleCategory::class))
        ->andReturnUsing(static function (KbArticleCategory $category) use ($randId): void {
            supportSetEntityId($category, $randId);
        });
    $emMock->shouldReceive('flush')->once();

    $toolsMock = Mockery::mock(FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('slug')
        ->atLeast()->once()
        ->andReturn('article-slug');

    $di = container();
    $di['em'] = $emMock;
    $di['tools'] = $toolsMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbCreateCategory('Title', 'Description');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('kb update category', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = supportKbCategoryFixture();

    $result = $service->kbUpdateCategory($model, 'New Title', 'new-title', 'Description');
    expect($result)->toBeTrue();
    expect($model->getTitle())->toBe('New Title');
});

/*
 * Guest Ticket Tests
 */

test('public find one by hash', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findOneByAccessHash')->atLeast()->once()
        ->andReturn($ticket);
    $service->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($repo);

    $di = container();
    $service->setDi($di);

    $result = $service->findOneByHash(sha1(uniqid()));
    expect($result)->toBeInstanceOf(SupportTicket::class);
});

test('public find one by hash not found exception', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $repo = Mockery::mock(SupportTicketRepository::class);
    $repo->shouldReceive('findOneByAccessHash')->atLeast()->once()
        ->andReturn(null);
    $service->shouldReceive('getSupportTicketRepository')->atLeast()->once()
        ->andReturn($repo);

    $di = container();
    $service->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $service->findOneByHash(sha1(uniqid()));
});

dataset('closeTicketProvider', fn (): array => [
    'with admin' => [new Model_Admin()],
    'with guest' => [new Model_Guest()],
]);

test('public close ticket', function (Model_Admin|Model_Guest $identity): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $ticket->setAccessHash('test-hash-123');

    $result = $service->closeTicket($ticket, $identity);
    expect($result)->toBeTrue();
})->with('closeTicketProvider');

test('public to api array delegates to ticket serialization', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn(['hash' => 'test-hash-123']);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $serviceMock->toApiArray($ticket, true);
    expect($result)->toEqual(['hash' => 'test-hash-123']);
});

test('guest ticket reply', function (): void {
    $service = new Service();
    $message = new SupportTicketMessage();
    setEntityId($message, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $requestMock = Mockery::mock(FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $ticket->setAccessHash('test-hash-123');

    $result = $service->ticketReply($ticket, new Model_Guest(), 'Content');
    expect($result)->toBeInt();
});

/*
 * Ticket Operations Tests
 */

test('ticket update', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $data = [
        'status' => SupportTicket::STATUS_OPEN,
        'subject' => 'Subject',
        'priority' => 1,
    ];

    $result = $service->ticketUpdate($ticket, $data);
    expect($result)->toBeTrue();
});

test('ticket message update', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $capturedHistory = null;
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->with(Mockery::on(function ($entity) use (&$capturedHistory): bool {
            $capturedHistory = $entity;

            return $entity instanceof SupportTicketMessageHistory;
        }));

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $message = new SupportTicketMessage();
    setEntityId($message, 1);
    $message->setAdminId(1);
    $message->setContent('Original content');

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 7;

    $result = $service->ticketMessageUpdate($message, 'Edited content', $admin);
    expect($result)->toBeTrue();
    expect($message->getContent())->toBe('Edited content');
    expect($capturedHistory)->not->toBeNull();
    expect($capturedHistory->getContent())->toBe('Original content');
    expect($capturedHistory->getAdminId())->toBe(7);
});

test('ticket message update rejects editing a client-authored message', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class)->shouldIgnoreMissing();
    supportWireKbRepositories($emMock);

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $message = new SupportTicketMessage();
    setEntityId($message, 1);
    $message->setClientId(1);
    $message->setContent('Client wrote this');

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 7;

    $service->ticketMessageUpdate($message, 'Tampered content', $admin);
})->throws(FOSSBilling\InformationException::class);

test('ticket message update skips creating history when content is unchanged', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldNotReceive('persist');
    $emMock->shouldNotReceive('flush');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $message = new SupportTicketMessage();
    setEntityId($message, 1);
    $message->setAdminId(1);
    $message->setContent('Same content');

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 7;

    $result = $service->ticketMessageUpdate($message, 'Same content', $admin);
    expect($result)->toBeTrue();
});

test('gets message history', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);

    $message = new SupportTicketMessage();
    setEntityId($message, 1);

    $history = new SupportTicketMessageHistory();
    setEntityId($history, 1);
    $history->setMessage($message);
    $history->setAdminId(7);
    $history->setContent('Original content');

    $historyRepo = Mockery::mock(SupportTicketMessageHistoryRepository::class);
    $historyRepo->shouldReceive('findByMessageId')->atLeast()->once()
        ->with(1)
        ->andReturn([$history]);
    supportWireKbRepositories($emMock, supportTicketMessageHistoryRepo: $historyRepo);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->getMessageHistory($message);
    expect($result)->toBe([$history->toApiArray()]);
});

dataset('ticketReplyProvider', function () {
    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    return [
        'with admin' => [$admin],
        'with client' => [$client],
    ];
});

test('ticket reply', function (Model_Admin|Model_Client $identity): void {
    $service = new Service();
    $message = new SupportTicketMessage();
    setEntityId($message, 1);

    $randId = 1;
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $requestMock = Mockery::mock(FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->ticketReply($ticket, $identity, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
})->with('ticketReplyProvider');

test('ticket create for admin', function (): void {
    $service = new Service();
    $message = new SupportTicketMessage();
    setEntityId($message, 1);

    $randId = 1;
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $requestMock = Mockery::mock(FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $helpdesk = helpdeskFixture();

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;

    $data = [
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $result = $service->ticketCreateForAdmin(1, $helpdesk, $data, $admin);
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('ticket create for client', function (): void {
    $service = new Service();
    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $randId = 1;
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();
    $cannedRepoMock = Mockery::mock(CannedResponseRepository::class);
    $cannedRepoMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn(supportCannedResponseFixture());
    supportWireKbRepositories($emMock, cannedRepo: $cannedRepoMock);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $config = [
        'autorespond_enable' => 1,
        'autorespond_message_id' => 1,
    ];
    $supportModMock = Mockery::mock(FOSSBilling\Module::class);
    $supportModMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $staffServiceMock = Mockery::mock(Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('getCronAdmin')
        ->atLeast()->once()
        ->andReturn(new Model_Admin());

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('ticketReply')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageCreateForTicket')
        ->atLeast()->once()
        ->andReturn(1);
    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $di['mod'] = $di->protect(fn () => $supportModMock);
    $di['mod_service'] = $di->protect(fn () => $staffServiceMock);

    $serviceMock->setDi($di);

    $helpdesk = helpdeskFixture();

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'subject' => 'Subject',
        'content' => 'content',
    ];

    $result = $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('ticket create for client task already exists exception', function (): void {
    $service = new Service();
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('checkIfTaskAlreadyExists')
        ->byDefault()
        ->andReturn(true);

    $helpdesk = helpdeskFixture();

    $data = [
        'rel_id' => 1,
        'rel_type' => 'Type',
        'rel_task' => 'Task',
        'rel_new_value' => 'New value',
    ];

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    $di = container();
    $serviceMock->setDi($di);

    $this->expectException(FOSSBilling\Exception::class);
    $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
});

test('ticket task complete', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new SupportTicket();
    setEntityId($model, 1);

    $result = $service->ticketTaskComplete($model);
    expect($result)->toBeTrue();
    expect($model->getRelStatus())->toBe(SupportTicket::REL_STATUS_COMPLETE);
});

/*
 * Message Tests
 */

test('message get ticket messages', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $messageRepo = Mockery::mock(SupportTicketMessageRepository::class);
    $messageRepo->shouldReceive('findByTicketId')->atLeast()->once()
        ->andReturn([new SupportTicketMessage()]);
    $service->shouldReceive('getSupportTicketMessageRepository')->atLeast()->once()
        ->andReturn($messageRepo);

    $di = container();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->getSupportTicketMessageRepository()->findByTicketId($ticket->getId() ?? 0);
    expect($result)->toBeArray();
});

test('message get replies count', function (): void {
    $service = Mockery::mock(Service::class)->makePartial();
    $messageRepo = Mockery::mock(SupportTicketMessageRepository::class);
    $messageRepo->shouldReceive('countByTicketId')->atLeast()->once()
        ->andReturn(1);
    $service->shouldReceive('getSupportTicketMessageRepository')->atLeast()->once()
        ->andReturn($messageRepo);

    $di = container();
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->messageGetRepliesCount($ticket);
    expect($result)->toBeInt();
});

test('message get author details admin', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAssociative')
        ->atLeast()->once()
        ->andReturn(['id' => 1, 'name' => 'Admin Name', 'email' => 'admin@example.com']);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $ticketMsg = new SupportTicketMessage();
    setEntityId($ticketMsg, 1);
    $ticketMsg->setAdminId(1);

    $result = $service->messageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
});

test('message get author details client', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAssociative')
        ->atLeast()->once()
        ->andReturn(['id' => 1, 'first_name' => 'Client', 'last_name' => 'Name', 'email' => 'client@example.com']);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $ticketMsg = new SupportTicketMessage();
    setEntityId($ticketMsg, 1);
    $ticketMsg->setClientId(1);

    $result = $service->messageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
});

test('message to api array', function (): void {
    $service = new Service();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('messageGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $serviceMock->setDi($di);

    $ticketMsg = new SupportTicketMessage();
    setEntityId($ticketMsg, 1);

    $result = $serviceMock->messageToApiArray($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
});

dataset('messageCreateForTicketProvider', function () {
    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());
    $admin->id = 1;

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;

    return [
        'with admin' => [$admin],
        'with client' => [$client],
    ];
});

test('message create for ticket', function (Model_Admin|Model_Client $identity): void {
    $service = new Service();
    $randId = 1;
    $supportTicketMessage = new SupportTicketMessage();
    setEntityId($supportTicketMessage, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $requestMock = Mockery::mock(FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $service->setDi($di);

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->messageCreateForTicket($ticket, $identity, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
})->with('messageCreateForTicketProvider');

/*
 * Note Tests
 */

test('note get author details', function (): void {
    $service = new Service();

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchAssociative')
        ->atLeast()->once()
        ->andReturn(['id' => 1, 'name' => 'AdminName', 'email' => 'admin@example.com']);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $note = new SupportTicketNote();
    setEntityId($note, 1);
    $note->setAdminId(1);

    $result = $service->noteGetAuthorDetails($note);
    expect($result)->toBeArray();
});

test('note rm', function (): void {
    $service = new Service();
    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('remove')->atLeast()->once();
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $note = new SupportTicketNote();
    setEntityId($note, 1);

    $result = $service->noteRm($note);
    expect($result)->toBeTrue();
});

test('note to api array', function (): void {
    $service = new Service();

    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('noteGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $serviceMock->setDi($di);

    $note = new SupportTicketNote();
    setEntityId($note, 1);

    $result = $serviceMock->noteToApiArray($note);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
});

test('note create', function (): void {
    $service = new Service();
    $randId = 1;
    $supportTicketNote = new SupportTicketNote();
    setEntityId($supportTicketNote, 1);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock);
    $emMock->shouldReceive('persist')->atLeast()->once()
        ->andReturnUsing(function ($entity): void {
            if ($entity->getId() === null) {
                \Tests\Helpers\setEntityId($entity, 1);
            }
        });
    $emMock->shouldReceive('flush')->atLeast()->once();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $service->setDi($di);

    $admin = new Model_Admin();
    $admin->loadBean(new Tests\Helpers\DummyBean());

    $ticket = new SupportTicket();
    setEntityId($ticket, 1);

    $result = $service->noteCreate($ticket, $admin, 'Note');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

/*
 * Other Tests
 */

dataset('canClientSubmitNewTicketProvider', function () {
    $ticket = new SupportTicket();
    setEntityId($ticket, 1);
    $ticket->setClientId(5);
    $ticket->setCreatedAt(new DateTime());

    $ticket2 = new SupportTicket();
    setEntityId($ticket2, 1);
    $ticket2->setClientId(5);
    $ticket2->setCreatedAt(new DateTime('-2 days'));

    return [
        'ticket created today - cannot submit' => [$ticket, 24, false],
        'no previous tickets - can submit' => [null, 24, true],
        'last ticket 2 days ago - can submit' => [$ticket2, 24, true],
    ];
});

test('can client submit new ticket', function (?SupportTicket $ticket, int $hours, bool $expected): void {
    $service = new Service();
    if (!$expected) {
        $this->expectException(FOSSBilling\Exception::class);
    }

    $repoMock = Mockery::mock(SupportTicketRepository::class);
    $repoMock->shouldReceive('findOneBy')
        ->atLeast()->once()
        ->andReturn($ticket);

    $emMock = Mockery::mock(EntityManagerInterface::class);
    supportWireKbRepositories($emMock, supportTicketRepo: $repoMock);

    $di = container();
    $di['em'] = $emMock;
    $service->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;

    $config = ['wait_hours' => $hours];

    $result = $service->canClientSubmitNewTicket($client, $config);

    if ($expected) {
        expect($result)->toBeTrue();
    }
})->with('canClientSubmitNewTicketProvider');
