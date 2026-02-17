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
use Box\Mod\Support\Service;
use Box\Mod\Client\Service as ClientService;
use Box\Mod\Email\Service as EmailService;

/*
 * Dependency Injection Tests
 */

test('gets and sets dependency injection container', function (): void {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

/*
 * Event Handler Tests
 */

test('handles after client open ticket event', function (): void {
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_client'] = new \Model_Client();
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
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
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
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
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
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'client' => [
            'id' => 1,
        ],
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getTicketById')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);
    $serviceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
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

test('handles after guest public ticket open event', function (): void {
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'author_email' => 'email@example.com',
        'author_name' => 'Name',
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getPublicTicketById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);
    $serviceMock->shouldReceive('publicToApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterGuestPublicTicketOpen($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin public ticket open event', function (): void {
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'author_email' => 'email@example.com',
        'author_name' => 'Name',
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getPublicTicketById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);
    $serviceMock->shouldReceive('publicToApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminPublicTicketOpen($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin public ticket reply event', function (): void {
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'author_email' => 'email@example.com',
        'author_name' => 'Name',
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getPublicTicketById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);
    $serviceMock->shouldReceive('publicToApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminPublicTicketReply($eventMock);
    expect($result)->toBeNull();
});

test('handles after admin public ticket close event', function (): void {
    $service = new \Box\Mod\Support\Service();
    $toApiArrayReturn = [
        'author_email' => 'email@example.com',
        'author_name' => 'Name',
    ];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $serviceMock->shouldReceive('getPublicTicketById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);
    $serviceMock->shouldReceive('publicToApiArray')
        ->atLeast()->once()
        ->andReturn($toApiArrayReturn);

    $emailServiceMock = Mockery::mock(EmailService::class);
    $emailServiceMock->shouldReceive('sendTemplate')
        ->atLeast()->once()
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
    $di['loggedin_admin'] = new \Model_Admin();
    $serviceMock->setDi($di);

    $eventMock = Mockery::mock('\Box_Event');
    $eventMock->shouldReceive('getDi')
        ->atLeast()->once()
        ->andReturn($di);
    $eventMock->shouldReceive('getParameters')
        ->atLeast()->once()
        ->andReturn(['id' => random_int(1, 100)]);

    $result = $serviceMock->onAfterAdminPublicTicketClose($eventMock);
    expect($result)->toBeNull();
});

/*
 * Ticket Tests
 */

test('gets ticket by id', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getTicketById(1);
    expect($result)->toBeInstanceOf(\Model_SupportTicket::class);
});

test('gets public ticket by id', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getPublicTicketById(1);
    expect($result)->toBeInstanceOf(\Model_SupportPTicket::class);
});

test('gets statuses', function (): void {
    $service = new \Box\Mod\Support\Service();
    $result = $service->getStatuses();
    expect($result)->toBeArray();
});

test('finds one by client', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $result = $service->findOneByClient($client, 1);
    expect($result)->toBeInstanceOf(\Model_SupportTicket::class);
});

test('throws exception when ticket not found by client', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $service->findOneByClient($client, 1);
})->throws(\FOSSBilling\Exception::class);

dataset('searchQueryData', [
    [
        [
            'search' => 'query',
            'id' => 1,
            'status' => 'open',
            'client_id' => 1,
            'client' => 'Client name',
            'order_id' => 1,
            'subject' => 'subject',
            'content' => 'Content',
            'support_helpdesk_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'date_from' => date('Y-m-d H:i:s'),
            'date_to' => date('Y-m-d H:i:s'),
            'priority' => 1,
        ],
    ],
    [
        [
            'search' => 1,
            'id' => 1,
            'status' => 'open',
            'client_id' => 1,
            'client' => 'Client name',
            'order_id' => 1,
            'subject' => 'subject',
            'content' => 'Content',
            'support_helpdesk_id' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'date_from' => date('Y-m-d H:i:s'),
            'date_to' => date('Y-m-d H:i:s'),
            'priority' => 1,
        ],
    ],
]);

test('gets search query', function ($data) {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);
    [$query, $bindings] = $service->getSearchQuery($data);
    expect($query)->toBeString();
    expect($bindings)->toBeArray();
})->with('searchQueryData');

test('counts tickets', function (): void {
    $service = new \Box\Mod\Support\Service();
    $arr = [
        \Model_SupportTicket::OPENED => 1,
        \Model_SupportTicket::ONHOLD => 1,
        \Model_SupportTicket::CLOSED => 1,
    ];
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($arr);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->counter();
    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe(array_sum($arr));
});

test('gets latest tickets', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$ticket, $ticket]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getLatest();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_SupportTicket::class);
});

test('gets expired tickets', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->getExpired();
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('counts by status', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->countByStatus('open');
    expect($result)->toBeInt();
});

test('gets active tickets count for order', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->getActiveTicketsCountForOrder($order);
    expect($result)->toBeInt();
});

test('checks if task already exists returns true', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->checkIfTaskAlreadyExists($client, 1, \Model_SupportTicket::REL_TYPE_ORDER, \Model_SupportTicket::REL_TASK_UPGRADE);
    expect($result)->toBeTrue();
});

test('checks if task already exists returns false', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->checkIfTaskAlreadyExists($client, 1, \Model_SupportTicket::REL_TYPE_ORDER, \Model_SupportTicket::REL_TASK_CANCEL);
    expect($result)->toBeFalse();
});

dataset('closeTicketIdentities', [
    [new \Model_Admin()],
    [new \Model_Client()],
]);

test('closes a ticket', function ($identity) {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->closeTicket($ticket, $identity);
    expect($result)->toBeTrue();
})->with('closeTicketIdentities');

test('auto closes a ticket', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->autoClose($ticket);
    expect($result)->toBeTrue();
});

test('checks if ticket can be reopened when not closed', function (): void {
    $service = new \Box\Mod\Support\Service();
    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('checks if ticket can be reopened', function (): void {
    $service = new \Box\Mod\Support\Service();
    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $helpdesk->support_helpdesk_id = 1;
    $helpdesk->can_reopen = true;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($helpdesk);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->status = \Model_SupportTicket::CLOSED;

    $result = $service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('removes tickets by client', function (): void {
    $service = new \Box\Mod\Support\Service();
    $model = new \Model_SupportTicket();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$model]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->rmByClient($client);
    expect($result)->toBeNull();
});

test('removes a ticket', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $callCount = 0;
    $dbMock->shouldReceive('find')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            return $callCount === 1 ? new \Model_SupportTicketNote() : new \Model_SupportTicketMessage();
        });

    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->rm($ticket);
    expect($result)->toBeTrue();
});

test('converts ticket to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $supportTicketMessageModel = new \Model_SupportTicketMessage();
    $supportTicketMessageModel->loadBean(new \Tests\Helpers\DummyBean());
    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($supportTicketMessageModel);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function ($type) use ($helpdesk) {
            return $type === 'SupportHelpdesk' ? $helpdesk : new \Model_Client();
        });

    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_SupportTicketNote()]);

    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $ticketMessages = [new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage()];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('messageGetRepliesCount')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('helpdeskToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('messageGetTicketMessages')
        ->atLeast()->once()
        ->andReturn($ticketMessages);
    $serviceMock->shouldReceive('noteToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getClientApiArrayForTicket')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $serviceMock->toApiArray($ticket, true, new \Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toHaveKey('replies');
    expect($result)->toHaveKey('helpdesk');
    expect($result)->toHaveKey('messages');
    expect(count($result['messages']))->toBe(count($ticketMessages));
});

test('converts ticket to api array with rel details', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(new \Model_SupportTicketMessage());

    $callCount = 0;
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;
            return $callCount === 1 ? new \Model_SupportHelpdesk() : new \Model_Client();
        });

    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_SupportTicketNote()]);

    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $ticketMessages = [new \Model_SupportTicketMessage(), new \Model_SupportTicketMessage()];
    $serviceMock = Mockery::mock(Service::class)->makePartial();
    $serviceMock->shouldReceive('messageGetRepliesCount')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('helpdeskToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('messageGetTicketMessages')
        ->atLeast()->once()
        ->andReturn($ticketMessages);
    $serviceMock->shouldReceive('noteToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('getClientApiArrayForTicket')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->rel_id = 1;
    $ticket->rel_type = 'Type';

    $result = $serviceMock->toApiArray($ticket, true, new \Model_Admin());
    expect($result)->toBeArray();
    expect($result)->toHaveKey('replies');
    expect($result)->toHaveKey('helpdesk');
    expect($result)->toHaveKey('messages');
    expect(count($result['messages']))->toBe(count($ticketMessages));
});

test('gets client api array for ticket', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn(new \Model_Client());

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $clientServiceMock);
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->getClientApiArrayForTicket($ticket);
    expect($result)->toBeArray();
});

test('gets client api array for ticket when client not exists', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn(null);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $clientServiceMock);
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->getClientApiArrayForTicket($ticket);
    expect($result)->toBeArray();
});

/*
 * Canned Response Tests
 */

test('canned get search query', function (): void {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);

    $data = [
        'search' => 'query',
    ];

    [$query, $bindings] = $service->cannedGetSearchQuery($data);
    expect($query)->toBeString();
    expect($bindings)->toBeArray();
});

test('canned get grouped pairs', function (): void {
    $service = new \Box\Mod\Support\Service();
    $pairs = [
        0 => [
            'id' => 1,
            'r_title' => 'R  Title',
            'c_title' => 'General',
        ],
    ];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn($pairs);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $expected = [
        'General' => [
            1 => 'R  Title',
        ],
    ];

    $result = $service->cannedGetGroupedPairs();
    expect($result)->toBeArray();
    expect($result)->toEqual($expected);
});

test('canned rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $canned = new \Model_SupportPr();
    $canned->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedRm($canned);
    expect($result)->toBeTrue();
});

test('canned to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $category = new \Model_SupportPrCategory();
    $category->loadBean(new \Tests\Helpers\DummyBean());
    $category->id = 1;
    $category->title = 'General';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($category);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $canned = new \Model_SupportPr();
    $canned->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedToApiArray($canned);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('category');
    expect($result['category'])->toBeArray();
    expect($result['category'])->toHaveKey('id');
    expect($result['category'])->toHaveKey('title');
});

test('canned to api array category not found', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $canned = new \Model_SupportPr();
    $canned->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedToApiArray($canned);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('category');
    expect($result['category'])->toEqual([]);
});

test('canned category get pairs', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([0 => 'General']);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->cannedCategoryGetPairs();
    expect($result)->toBeArray();
});

test('canned category rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $canned = new \Model_SupportPrCategory();
    $canned->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedCategoryRm($canned);
    expect($result)->toBeTrue();
});

test('canned category to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $canned = new \Model_SupportPrCategory();
    $canned->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedCategoryToApiArray($canned);
    expect($result)->toBeArray();
});

test('canned create', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $helpDeskModel = new \Model_SupportPr();
    $helpDeskModel->loadBean(new \Tests\Helpers\DummyBean());
    
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($helpDeskModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('checkLimits')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $systemServiceMock);
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedCreate('Name', 1, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('canned update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportPr();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'category_id' => 1,
        'title' => 'email@example.com',
        'content' => 1,
    ];

    $result = $service->cannedUpdate($model, $data);
    expect($result)->toBeTrue();
});

test('canned category create', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $supportPrCategoryModel = new \Model_SupportPrCategory();
    $supportPrCategoryModel->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($supportPrCategoryModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->cannedCategoryCreate('Name');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('canned category update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportPrCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->cannedCategoryUpdate($model, 'Title');
    expect($result)->toBeTrue();
});

/*
 * Helpdesk Tests
 */

test('helpdesk get search query', function (): void {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);

    $data = [
        'search' => 'SearchQuery',
    ];
    [$query, $bindings] = $service->helpdeskGetSearchQuery($data);

    $expectedBindings = [
        ':name' => '%SearchQuery%',
        ':email' => '%SearchQuery%',
        ':signature' => '%SearchQuery%',
    ];

    expect($query)->toBeString();
    expect($bindings)->toBeArray();
    expect($bindings)->toEqual($expectedBindings);
});

test('helpdesk get pairs', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([0 => 'General']);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->helpdeskGetPairs();
    expect($result)->toBeArray();
});

test('helpdesk rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $helpdesk->id = 1;
    
    $result = $service->helpdeskRm($helpdesk);
    expect($result)->toBeTrue();
});

test('helpdesk rm has tickets exception', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_SupportTicket()]);
    $dbMock->shouldReceive('trash')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $helpdesk->id = 1;
    
    $this->expectException(\FOSSBilling\Exception::class);
    $service->helpdeskRm($helpdesk);
});

test('helpdesk to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $helpdesk->id = 1;
    
    $result = $service->helpdeskToApiArray($helpdesk);
    expect($result)->toBeArray();
});

test('helpdesk update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'name' => 'Name',
        'email' => 'email@example.com',
        'can_reopen' => 1,
        'close_after' => 1,
        'signature' => 'Signature',
    ];

    $result = $service->helpdeskUpdate($helpdesk, $data);
    expect($result)->toBeTrue();
});

test('helpdesk create', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $helpDeskModel = new \Model_SupportHelpdesk();
    $helpDeskModel->loadBean(new \Tests\Helpers\DummyBean());
    
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($helpDeskModel);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
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

test('kb search articles', function (): void {
    $service = new \Box\Mod\Support\Service();
    $willReturn = [
        'pages' => 5,
        'page' => 2,
        'per_page' => 2,
        'total' => 10,
        'list' => [],
    ];

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class);
    $pagerMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()->once()
        ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pagerMock;
    $service->setDi($di);

    $result = $service->kbSearchArticles('active', 'keyword', 'category');
    expect($result)->toBeArray();
    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
    expect($result)->toHaveKey('pages');
    expect($result)->toHaveKey('page');
    expect($result)->toHaveKey('per_page');
    expect($result)->toHaveKey('total');
});

test('kb find active article by id', function (): void {
    $service = new \Box\Mod\Support\Service();
    $model = new \Model_SupportKbArticle();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbFindActiveArticleById(5);
    expect($result)->toBeInstanceOf(\Model_SupportKbArticle::class);
    expect($result)->toEqual($model);
});

test('kb find active article by slug', function (): void {
    $service = new \Box\Mod\Support\Service();
    $model = new \Model_SupportKbArticle();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbFindActiveArticleBySlug('slug');
    expect($result)->toBeInstanceOf(\Model_SupportKbArticle::class);
    expect($result)->toEqual($model);
});

test('kb find active', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbFindActive();
    expect($result)->toBeArray();
});

test('kb hit view', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(5);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $modelKb = new \Model_SupportKbArticle();
    $modelKb->loadBean(new \Tests\Helpers\DummyBean());
    $modelKb->views = 10;

    $result = $service->kbHitView($modelKb);
    expect($result)->toBeNull();
});

test('kb rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $modelKb = new \Model_SupportKbArticle();
    $modelKb->loadBean(new \Tests\Helpers\DummyBean());
    $modelKb->id = 1;
    $modelKb->views = 10;

    $result = $service->kbRm($modelKb);
    expect($result)->toBeNull();
});

dataset('kbToApiArrayProvider', function () {
    $model = new \Model_SupportKbArticle();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;
    $model->slug = 'article-slug';
    $model->title = 'Title';
    $model->views = 1;
    $model->content = 'Content';
    $model->created_at = '2013-01-01 12:00:00';
    $model->updated_at = '2014-01-01 12:00:00';
    $model->status = 'active';
    $model->kb_article_category_id = 1;

    $category = new \Model_SupportKbArticleCategory();
    $category->loadBean(new \Tests\Helpers\DummyBean());
    $category->id = 1;
    $category->slug = 'category-slug';
    $category->title = 'category-title';

    return [
        'shallow without admin' => [
            $model,
            [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'views' => $model->views,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'title' => $category->title,
                ],
                'status' => $model->status,
            ],
            false,
            null,
            $category,
        ],
        'deep without admin' => [
            $model,
            [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'views' => $model->views,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'title' => $category->title,
                ],
                'content' => $model->content,
                'status' => $model->status,
            ],
            true,
            null,
            $category,
        ],
        'deep with admin' => [
            $model,
            [
                'id' => $model->id,
                'slug' => $model->slug,
                'title' => $model->title,
                'views' => $model->views,
                'created_at' => $model->created_at,
                'updated_at' => $model->updated_at,
                'category' => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'title' => $category->title,
                ],
                'content' => $model->content,
                'status' => $model->status,
                'kb_article_category_id' => $model->kb_article_category_id,
            ],
            true,
            new \Model_Admin(),
            $category,
        ],
    ];
});

test('kb to api array', function (\Model_SupportKbArticle $model, array $expected, bool $deep, ?\Model_Admin $identity, \Model_SupportKbArticleCategory $category): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($category);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbToApiArray($model, $deep, $identity);
    expect($result)->toEqual($expected);
})->with('kbToApiArrayProvider');

test('kb create article', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);
    
    $model = new \Model_SupportKbArticle();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($model);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('slug')
        ->atLeast()->once()
        ->andReturn('article-slug');

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbCreateArticle(1, 'Title', 'Active', 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('kb update article', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $model = new \Model_SupportKbArticle();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $result = $service->kbUpdateArticle($randId, 1, 'Title', 'article-slug', 'active', 'content', 1);
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});

test('kb update article not found exception', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(false);
    $dbMock->shouldReceive('store')
        ->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $service->kbUpdateArticle($randId, 1, 'Title', 'article-slug', 'active', 'content', 1);
});

dataset('kbCategoryGetSearchQueryProvider', function () {
    return [
        'empty data' => [
            [],
            'SELECT kac.* FROM support_kb_article_category kac LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id GROUP BY kac.id ORDER BY kac.title',
            [],
        ],
        'with article status' => [
            ['article_status' => 'active'],
            'SELECT kac.* FROM support_kb_article_category kac LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id WHERE ka.status = :status GROUP BY kac.id ORDER BY kac.title',
            [':status' => 'active'],
        ],
        'with search query' => [
            ['q' => 'search query'],
            'SELECT kac.* FROM support_kb_article_category kac LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id WHERE (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.title',
            [':title' => '%search query%', ':content' => '%search query%'],
        ],
        'with search query and article status' => [
            ['q' => 'search query', 'article_status' => 'active'],
            'SELECT kac.* FROM support_kb_article_category kac LEFT JOIN support_kb_article ka ON kac.id  = ka.kb_article_category_id WHERE ka.status = :status AND (ka.title LIKE :title OR ka.content LIKE :content) GROUP BY kac.id ORDER BY kac.title',
            [':title' => '%search query%', ':content' => '%search query%', ':status' => 'active'],
        ],
    ];
});

test('kb category get search query', function (array $data, string $query, array $bindings): void {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);

    $result = $service->kbCategoryGetSearchQuery($data);

    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();
    
    // Normalize whitespace for comparison
    $normalizedResult = trim((string) preg_replace('/\s+/', ' ', str_replace("\n", ' ', $result[0])));
    $normalizedQuery = trim((string) preg_replace('/\s+/', ' ', str_replace("\n", ' ', $query)));
    expect($normalizedResult)->toEqual($normalizedQuery);
    expect($result[1])->toEqual($bindings);
})->with('kbCategoryGetSearchQueryProvider');

test('kb category find all', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbCategoryFindAll();
    expect($result)->toBeArray();
});

test('kb category get pairs', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbCategoryGetPairs();
    expect($result)->toBeArray();
});

test('kb category rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(0);
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportKbArticleCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $result = $service->kbCategoryRm($model);
    expect($result)->toBeTrue();
});

test('kb category rm has articles exception', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportKbArticleCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $this->expectException(\FOSSBilling\Exception::class);
    $service->kbCategoryRm($model);
});

test('kb create category', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);
    
    $articleCategoryModel = new \Model_SupportKbArticleCategory();
    $articleCategoryModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($articleCategoryModel);

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('slug')
        ->atLeast()->once()
        ->andReturn('article-slug');

    $systemServiceMock = Mockery::mock(\Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('checkLimits')
        ->atLeast()->once()
        ->andReturn(true);

    $di = container();
    $di['db'] = $dbMock;
    $di['tools'] = $toolsMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $systemServiceMock);
    $service->setDi($di);

    $result = $service->kbCreateCategory('Title', 'Description');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('kb update category', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportKbArticleCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $model->id = 1;

    $result = $service->kbUpdateCategory($model, 'New Title', 'new-title', 'Description');
    expect($result)->toBeTrue();
});

test('kb find category by id', function (): void {
    $service = new \Box\Mod\Support\Service();
    $model = new \Model_SupportKbArticleCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbFindCategoryById(5);
    expect($result)->toBeInstanceOf(\Model_SupportKbArticleCategory::class);
    expect($result)->toEqual($model);
});

test('kb find category by slug', function (): void {
    $service = new \Box\Mod\Support\Service();
    $model = new \Model_SupportKbArticleCategory();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($model);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->kbFindCategoryBySlug('slug');
    expect($result)->toBeInstanceOf(\Model_SupportKbArticleCategory::class);
    expect($result)->toEqual($model);
});

/*
 * Public Ticket Tests
 */

test('public get statuses', function (): void {
    $service = new \Box\Mod\Support\Service();
    $result = $service->publicGetStatuses();
    expect($result)->toBeArray();
});

test('public find one by hash', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(new \Model_SupportPTicket());

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->publicFindOneByHash(sha1(uniqid()));
    expect($result)->toBeInstanceOf(\Model_SupportPTicket::class);
});

test('public find one by hash not found exception', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $service->publicFindOneByHash(sha1(uniqid()));
});

dataset('publicGetSearchQueryProvider', function () {
    return [
        'with search string' => [
            [
                'search' => 'Query',
                'id' => 1,
                'status' => \Model_SupportPTicket::OPENED,
                'name' => 'Name',
                'email' => 'email@example.com',
                'subject' => 'Subject',
                'content' => 'Content',
            ],
        ],
        'with search int' => [
            [
                'search' => 1,
                'id' => 1,
                'status' => \Model_SupportPTicket::OPENED,
                'name' => 'Name',
                'email' => 'email@example.com',
                'subject' => 'Subject',
                'content' => 'Content',
            ],
        ],
    ];
});

test('public get search query', function (array $data): void {
    $service = new \Box\Mod\Support\Service();
    $di = container();
    $service->setDi($di);

    [$query, $bindings] = $service->publicGetSearchQuery($data);
    expect($query)->toBeString();
    expect($bindings)->toBeArray();
})->with('publicGetSearchQueryProvider');

test('public counter', function (): void {
    $service = new \Box\Mod\Support\Service();
    $arr = [
        \Model_SupportPTicket::OPENED => 1,
        \Model_SupportPTicket::ONHOLD => 1,
        \Model_SupportPTicket::CLOSED => 1,
    ];
    
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAssoc')
        ->atLeast()->once()
        ->andReturn($arr);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->publicCounter();
    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toEqual(array_sum($arr));
});

test('public get latest', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportPTicket();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$ticket, $ticket]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->publicGetLatest();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_SupportPTicket::class);
});

test('public count by status', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->publicCountByStatus('open');
    expect($result)->toBeInt();
});

test('public get expired', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportPTicket();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$ticket, $ticket]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $result = $service->publicGetExpired();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_SupportPTicket::class);
});

dataset('publicCloseTicketProvider', function () {
    return [
        'with admin' => [new \Model_Admin()],
        'with guest' => [new \Model_Guest()],
    ];
});

test('public close ticket', function (\Model_Admin|\Model_Guest $identity): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->publicCloseTicket($ticket, $identity);
    expect($result)->toBeTrue();
})->with('publicCloseTicketProvider');

test('public auto close', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->publicAutoClose($ticket);
    expect($result)->toBeTrue();
});

test('public rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_SupportPTicketMessage()]);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->publicRm($ticket);
    expect($result)->toBeTrue();
});

dataset('publicToApiArrayProvider', function () {
    return [
        'with message' => [
            new \Model_SupportPTicketMessage(),
            'atLeastOnce',
        ],
        'without message' => [
            null,
            'never',
        ],
    ];
});

test('public to api array', function (?\Model_SupportPTicketMessage $findOne, string $publicMessageGetAuthorDetailsCalled): void {
    $service = new \Box\Mod\Support\Service();
    $ticketMessages = [new \Model_SupportPTicketMessage(), new \Model_SupportPTicketMessage()];

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->andReturn($findOne);
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);
    $dbMock->shouldReceive('find')
        ->andReturn($ticketMessages);

    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('publicMessageToApiArray')
        ->atLeast()->once()
        ->andReturn([]);
    $serviceMock->shouldReceive('publicMessageGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn(['name' => 'Name', 'email' => 'email#example.com']);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $serviceMock->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $serviceMock->publicToApiArray($ticket, true);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
    expect($result)->toHaveKey('messages');
    expect($result['messages'])->toHaveCount(count($ticketMessages));
})->with('publicToApiArrayProvider');

test('public message get author details admin', function (): void {
    $service = new \Box\Mod\Support\Service();
    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($admin);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticketMsg = new \Model_SupportPTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->admin_id = 1;

    $result = $service->publicMessageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

test('public message get author details not admin', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->author_name = 'Name';
    $ticket->author_email = 'Email@example.com';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($ticket);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticketMsg = new \Model_SupportPTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->admin_id = null;

    $result = $service->publicMessageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

test('public message to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('publicMessageGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $ticketMsg = new \Model_SupportPTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->id = 1;

    $result = $serviceMock->publicMessageToApiArray($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
});

test('public ticket create', function (): void {
    $service = new \Box\Mod\Support\Service();
    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($message);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $toolsMock = Mockery::mock(\FOSSBilling\Tools::class);
    $toolsMock->shouldReceive('validateAndSanitizeEmail')
        ->atLeast()->once();

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $di['tools'] = $toolsMock;
    $di['request'] = $requestMock;
    $service->setDi($di);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $data = [
        'email' => 'email@example.com',
        'name' => 'Name',
        'message' => 'Message',
        'request' => 'Request',
        'subject' => 'Subject',
    ];

    $result = $service->publicTicketCreate($data, $admin);
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('public ticket update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'support_helpdesk_id' => 1,
        'status' => \Model_SupportTicket::OPENED,
        'subject' => 'Subject',
        'priority' => 1,
    ];

    $result = $service->publicTicketUpdate($ticket, $data);
    expect($result)->toBeTrue();
});

test('public ticket reply', function (): void {
    $service = new \Box\Mod\Support\Service();
    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($message);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $di['request'] = $requestMock;
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->publicTicketReply($ticket, $admin, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('public ticket reply for guest', function (): void {
    $service = new \Box\Mod\Support\Service();
    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($message);
    $dbMock->shouldReceive('store')
        ->atLeast()->once();

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new \Model_SupportPTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->hash = 'test-hash-123';

    $result = $service->publicTicketReplyForGuest($ticket, 'Content');
    expect($result)->toBeString();
    expect($result)->toEqual('test-hash-123');
});

/*
 * Ticket Operations Tests
 */

test('ticket update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'support_helpdesk_id' => 1,
        'status' => \Model_SupportTicket::OPENED,
        'subject' => 'Subject',
        'priority' => 1,
    ];

    $result = $service->ticketUpdate($ticket, $data);
    expect($result)->toBeTrue();
});

test('ticket message update', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->ticketMessageUpdate($message, 'Content');
    expect($result)->toBeTrue();
});

dataset('ticketReplyProvider', function () {
    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    return [
        'with admin' => [$admin],
        'with client' => [$client],
    ];
});

test('ticket reply', function (\Model_Admin|\Model_Client $identity): void {
    $service = new \Box\Mod\Support\Service();
    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($message);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->ticketReply($ticket, $identity, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
})->with('ticketReplyProvider');

test('ticket create for admin', function (): void {
    $service = new \Box\Mod\Support\Service();
    $message = new \Model_SupportTicketMessage();
    $message->loadBean(new \Tests\Helpers\DummyBean());

    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($message);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $di['events_manager'] = $eventMock;
    $service->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $data = [
        'subject' => 'Subject',
        'content' => 'Content',
    ];

    $result = $service->ticketCreateForAdmin($client, $helpdesk, $data, $admin);
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

test('ticket create for client', function (): void {
    $service = new \Box\Mod\Support\Service();
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->with('SupportTicket')
        ->atLeast()->once()
        ->andReturn($ticket);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_SupportPr());

    $eventMock = Mockery::mock('\Box_EventManager');
    $eventMock->shouldReceive('fire')
        ->atLeast()->once();

    $config = [
        'autorespond_enable' => 1,
        'autorespond_message_id' => 1,
    ];
    $supportModMock = Mockery::mock(\FOSSBilling\Module::class);
    $supportModMock->shouldReceive('getConfig')
        ->atLeast()->once()
        ->andReturn($config);

    $staffServiceMock = Mockery::mock(\Box\Mod\Staff\Service::class);
    $staffServiceMock->shouldReceive('getCronAdmin')
        ->atLeast()->once()
        ->andReturn(new \Model_Admin());

    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('ticketReply')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('messageCreateForTicket')
        ->atLeast()->once()
        ->andReturn(1);
    $serviceMock->shouldReceive('cannedToApiArray')
        ->atLeast()->once()
        ->andReturn(['content' => 'Content']);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventMock;
    $di['mod'] = $di->protect(fn () => $supportModMock);
    $di['mod_service'] = $di->protect(fn () => $staffServiceMock);

    $serviceMock->setDi($di);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
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
    $service = new \Box\Mod\Support\Service();
    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
    $serviceMock->shouldReceive('checkIfTaskAlreadyExists')
        ->atLeast()->once()
        ->andReturn(true);

    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());

    $data = [
        'rel_id' => 1,
        'rel_type' => 'Type',
        'rel_task' => 'Task',
        'rel_new_value' => 'New value',
    ];

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $di = container();
    $serviceMock->setDi($di);

    $this->expectException(\FOSSBilling\Exception::class);
    $serviceMock->ticketCreateForClient($client, $helpdesk, $data);
});

test('ticket task complete', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $model = new \Model_SupportTicket();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->ticketTaskComplete($model);
    expect($result)->toBeTrue();
});

/*
 * Message Tests
 */

test('message get ticket messages', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([new \Model_SupportTicketMessage()]);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->id = 1;

    $result = $service->messageGetTicketMessages($ticket);
    expect($result)->toBeArray();
});

test('message get replies count', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->id = 1;

    $result = $service->messageGetRepliesCount($ticket);
    expect($result)->toBeInt();
});

test('message get author details admin', function (): void {
    $service = new \Box\Mod\Support\Service();
    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($admin);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticketMsg = new \Model_SupportTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->admin_id = 1;

    $result = $service->messageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

test('message get author details client', function (): void {
    $service = new \Box\Mod\Support\Service();
    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($client);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $ticketMsg = new \Model_SupportTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->client_id = 1;

    $result = $service->messageGetAuthorDetails($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('name');
    expect($result)->toHaveKey('email');
});

test('message to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('messageGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $ticketMsg = new \Model_SupportTicketMessage();
    $ticketMsg->loadBean(new \Tests\Helpers\DummyBean());
    $ticketMsg->id = 1;

    $result = $serviceMock->messageToApiArray($ticketMsg);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
});

dataset('messageCreateForTicketProvider', function () {
    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->id = 1;

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    return [
        'with admin' => [$admin],
        'with client' => [$client],
    ];
});

test('message create for ticket', function (\Model_Admin|\Model_Client $identity): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $supportTicketMessage = new \Model_SupportTicketMessage();
    $supportTicketMessage->loadBean(new \Tests\Helpers\DummyBean());
    
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($supportTicketMessage);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $requestMock = Mockery::mock(\FOSSBilling\Request::class);
    $requestMock->shouldReceive('getClientIp')
        ->atLeast()->once()
        ->andReturn('127.0.0.1');

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['request'] = $requestMock;
    $service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->messageCreateForTicket($ticket, $identity, 'Content');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
})->with('messageCreateForTicketProvider');

/*
 * Note Tests
 */

test('note get author details', function (): void {
    $service = new \Box\Mod\Support\Service();
    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());
    $admin->name = 'AdminName';

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('load')
        ->atLeast()->once()
        ->andReturn($admin);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);

    $note = new \Model_SupportTicketNote();
    $note->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->noteGetAuthorDetails($note);
    expect($result)->toBeArray();
});

test('note rm', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('trash')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $note = new \Model_SupportTicketNote();
    $note->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->noteRm($note);
    expect($result)->toBeTrue();
});

test('note to api array', function (): void {
    $service = new \Box\Mod\Support\Service();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('toArray')
        ->atLeast()->once()
        ->andReturn([]);

    $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('noteGetAuthorDetails')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $serviceMock->setDi($di);

    $note = new \Model_SupportTicketNote();
    $note->loadBean(new \Tests\Helpers\DummyBean());

    $result = $serviceMock->noteToApiArray($note);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('author');
});

test('note create', function (): void {
    $service = new \Box\Mod\Support\Service();
    $randId = 1;
    $supportTicketNote = new \Model_SupportTicketNote();
    $supportTicketNote->loadBean(new \Tests\Helpers\DummyBean());

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('dispense')
        ->atLeast()->once()
        ->andReturn($supportTicketNote);
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn($randId);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $service->setDi($di);

    $admin = new \Model_Admin();
    $admin->loadBean(new \Tests\Helpers\DummyBean());

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $service->noteCreate($ticket, $admin, 'Note');
    expect($result)->toBeInt();
    expect($result)->toEqual($randId);
});

/*
 * Other Tests
 */

dataset('canClientSubmitNewTicketProvider', function () {
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->client_id = 5;
    $ticket->created_at = date('Y-m-d H:i:s');

    $ticket2 = new \Model_SupportTicket();
    $ticket2->loadBean(new \Tests\Helpers\DummyBean());
    $ticket2->client_id = 5;
    $ticket2->created_at = date('Y-m-d H:i:s', strtotime('-2 days'));

    return [
        'ticket created today - cannot submit' => [$ticket, 24, false],
        'no previous tickets - can submit' => [null, 24, true],
        'last ticket 2 days ago - can submit' => [$ticket2, 24, true],
    ];
});

test('can client submit new ticket', function (?\Model_SupportTicket $ticket, int $hours, bool $expected): void {
    $service = new \Box\Mod\Support\Service();
    if (!$expected) {
        $this->expectException(\FOSSBilling\Exception::class);
    }
    
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($ticket);

    $di = container();
    $di['db'] = $dbMock;
    $service->setDi($di);
    
    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 5;

    $config = ['wait_hours' => $hours];

    $result = $service->canClientSubmitNewTicket($client, $config);
    
    if ($expected) {
        expect($result)->toBeTrue();
    }
})->with('canClientSubmitNewTicketProvider');
