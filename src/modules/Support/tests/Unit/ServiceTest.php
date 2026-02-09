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

beforeEach(function () {
    $this->service = new Service();
});

/*
 * Dependency Injection Tests
 */

test('gets and sets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

/*
 * Event Handler Tests
 */

test('handles after client open ticket event', function () {
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

test('handles after admin open ticket event', function () {
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

test('handles after admin close ticket event', function () {
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

test('handles after admin reply ticket event', function () {
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

test('handles after guest public ticket open event', function () {
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

test('handles after admin public ticket open event', function () {
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

test('handles after admin public ticket reply event', function () {
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

test('handles after admin public ticket close event', function () {
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

test('gets ticket by id', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn(new \Model_SupportTicket());

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getTicketById(1);
    expect($result)->toBeInstanceOf(\Model_SupportTicket::class);
});

test('gets public ticket by id', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $supportPTicketModel = new \Model_SupportPTicket();
    $supportPTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('getExistingModelById')
        ->atLeast()->once()
        ->andReturn($supportPTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getPublicTicketById(1);
    expect($result)->toBeInstanceOf(\Model_SupportPTicket::class);
});

test('gets statuses', function () {
    $result = $this->service->getStatuses();
    expect($result)->toBeArray();
});

test('finds one by client', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $result = $this->service->findOneByClient($client, 1);
    expect($result)->toBeInstanceOf(\Model_SupportTicket::class);
});

test('throws exception when ticket not found by client', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());
    $client->id = 1;

    $this->service->findOneByClient($client, 1);
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
    $di = container();
    $this->service->setDi($di);
    [$query, $bindings] = $this->service->getSearchQuery($data);
    expect($query)->toBeString();
    expect($bindings)->toBeArray();
})->with('searchQueryData');

test('counts tickets', function () {
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
    $this->service->setDi($di);

    $result = $this->service->counter();
    expect($result)->toBeArray();
    expect($result)->toHaveKey('total');
    expect($result['total'])->toBe(array_sum($arr));
});

test('gets latest tickets', function () {
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn([$ticket, $ticket]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getLatest();
    expect($result)->toBeArray();
    expect($result[0])->toBeInstanceOf(\Model_SupportTicket::class);
});

test('gets expired tickets', function () {
    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getAll')
        ->atLeast()->once()
        ->andReturn([['id' => 1]]);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->getExpired();
    expect($result)->toBeArray();
    expect($result[0])->toBeArray();
});

test('counts by status', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $result = $this->service->countByStatus('open');
    expect($result)->toBeInt();
});

test('gets active tickets count for order', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $order = new \Model_ClientOrder();
    $order->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getActiveTicketsCountForOrder($order);
    expect($result)->toBeInt();
});

test('checks if task already exists returns true', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $supportTicketModel = new \Model_SupportTicket();
    $supportTicketModel->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn($supportTicketModel);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->checkIfTaskAlreadyExists($client, 1, \Model_SupportTicket::REL_TYPE_ORDER, \Model_SupportTicket::REL_TASK_UPGRADE);
    expect($result)->toBeTrue();
});

test('checks if task already exists returns false', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')
        ->atLeast()->once()
        ->andReturn(false);

    $di = container();
    $di['db'] = $dbMock;
    $this->service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->checkIfTaskAlreadyExists($client, 1, \Model_SupportTicket::REL_TYPE_ORDER, \Model_SupportTicket::REL_TASK_CANCEL);
    expect($result)->toBeFalse();
});

dataset('closeTicketIdentities', [
    [new \Model_Admin()],
    [new \Model_Client()],
]);

test('closes a ticket', function ($identity) {
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
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->closeTicket($ticket, $identity);
    expect($result)->toBeTrue();
})->with('closeTicketIdentities');

test('auto closes a ticket', function () {
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('store')
        ->atLeast()->once()
        ->andReturn(1);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->autoClose($ticket);
    expect($result)->toBeTrue();
});

test('checks if ticket can be reopened when not closed', function () {
    $helpdesk = new \Model_SupportHelpdesk();
    $helpdesk->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getExistingModelById')->never();

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('checks if ticket can be reopened', function () {
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
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());
    $ticket->status = \Model_SupportTicket::CLOSED;

    $result = $this->service->canBeReopened($ticket);
    expect($result)->toBeTrue();
});

test('removes tickets by client', function () {
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
    $this->service->setDi($di);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->rmByClient($client);
    expect($result)->toBeNull();
});

test('removes a ticket', function () {
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
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->rm($ticket);
    expect($result)->toBeTrue();
});

test('converts ticket to api array', function () {
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

test('converts ticket to api array with rel details', function () {
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

test('gets client api array for ticket', function () {
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
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getClientApiArrayForTicket($ticket);
    expect($result)->toBeArray();
});

test('gets client api array for ticket when client not exists', function () {
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
    $this->service->setDi($di);

    $ticket = new \Model_SupportTicket();
    $ticket->loadBean(new \Tests\Helpers\DummyBean());

    $result = $this->service->getClientApiArrayForTicket($ticket);
    expect($result)->toBeArray();
});
