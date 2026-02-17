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



test('ticket get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];
        $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
        $paginatorMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($simpleResultArr);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('getSearchQuery')->atLeast()->once()
            ->andReturn(['query', []]);
        $serviceMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [];
        $result = $api->ticket_get_list($data);

        expect($result)->toBeArray();
    });

    test('ticket get', function (): void {
        $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
            ->andReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->ticket_get($data);

        expect($result)->toBeArray();
    });

    test('ticket update', function (): void {
        $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketUpdate')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->ticket_update($data);

        expect($result)->toBeTrue();
    });

    test('ticket message update', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicketMessage());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketMessageUpdate')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $api->ticket_message_update($data);

        expect($result)->toBeTrue();
    });

    test('ticket delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('rm')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->ticket_delete($data);

        expect($result)->toBeTrue();
    });

    test('ticket reply', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
            ->andReturn(1);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $api->ticket_reply($data);

        expect($result)->toBeInt();
    });

    test('ticket close', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($ticket);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('ticket close already closed', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($ticket);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('closeTicket')
        ;

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('ticket create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \Tests\Helpers\DummyBean());

        $supportHelpdeskModel = new \Model_SupportHelpdesk();
        $supportHelpdeskModel->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($clientModel, $supportHelpdeskModel);

        $randID = 1;
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class);
        $serviceMock->shouldReceive('ticketCreateForAdmin')->atLeast()->once()
            ->andReturn($randID);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

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
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('getExpired')->atLeast()->once()
            ->andReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->shouldReceive('autoClose')->atLeast()->once()
            ->andReturn(true);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($ticket);

        $api->setService($serviceMock);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createStub('\Box_Log');
        $api->setDi($di);
        $api->setService($serviceMock);

        $result = $api->batch_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch ticket auto close not closed', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('getExpired')->atLeast()->once()
            ->andReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->shouldReceive('autoClose')->atLeast()->once()
        ;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($ticket);

        $api->setService($serviceMock);
        $di = container();
        $di['logger'] = $this->createStub('\Box_Log');
        $di['db'] = $dbMock;
        $api->setDi($di);
        $result = $api->batch_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch public ticket auto close', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicGetExpired')->atLeast()->once()
            ->andReturn([new \Model_SupportPTicket(), new \Model_SupportPTicket()]);
        $serviceMock->shouldReceive('publicAutoClose')->atLeast()->once()
            ->andReturn(true);

        $api->setService($serviceMock);

        $result = $api->batch_public_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch public ticket auto close not closed', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicGetExpired')->atLeast()->once()
            ->andReturn([$ticket, $ticket]);
        $serviceMock->shouldReceive('publicAutoClose')->atLeast()->once()
        ;

        $api->setService($serviceMock);
        $di = container();
        $di['logger'] = $this->createStub('\Box_Log');
        $api->setDi($di);
        $result = $api->batch_public_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('ticket get statuses', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('getStatuses')
            ->andReturn($statuses);
        $serviceMock->shouldReceive('counter')->atLeast()->once()
            ->andReturn($statuses);

        $api->setService($serviceMock);

        $result = $api->ticket_get_statuses([]);

        expect($result)->toEqual($statuses);
    });

    test('ticket get statuses titles set', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
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

    test('public ticket get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
        $paginatorMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($resultSet);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicGetSearchQuery')->atLeast()->once()
            ->andReturn(['query', []]);
        $serviceMock->shouldReceive('publicToApiArray')->atLeast()->once()
            ->andReturn(['query', []]);

        $model = new \Model_SupportPTicket();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = Mockery::mock('\Box_DAtabase');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [];
        $result = $api->public_ticket_get_list($data);

        expect($result)->toBeArray();
    });

    test('public ticket create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $randID = 1;
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicTicketCreate')->atLeast()->once()
            ->andReturn($randID);

        $di = container();
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
        ];

        $result = $api->public_ticket_create($data);

        expect($result)->toBeInt();
        expect($result)->toEqual($randID);
    });

    test('public ticket get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPTicket());

        $randID = 1;
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicToApiArray')->atLeast()->once()
            ->andReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->public_ticket_get($data);

        expect($result)->toBeArray();
    });

    test('public ticket delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicRm')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->public_ticket_delete($data);

        expect($result)->toBeTrue();
    });

    test('public ticket close', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicCloseTicket')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->public_ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('public ticket update', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicTicketUpdate')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->public_ticket_update($data);

        expect($result)->toBeTrue();
    });

    test('public ticket reply', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPTicket());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicTicketReply')->atLeast()->once()
            ->andReturn(1);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $api->public_ticket_reply($data);

        expect($result)->toBeInt();
    });

    test('public ticket get statuses', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicGetStatuses')
            ->andReturn($statuses);
        $serviceMock->shouldReceive('publicCounter')->atLeast()->once()
            ->andReturn($statuses);

        $api->setService($serviceMock);

        $result = $api->public_ticket_get_statuses([]);

        expect($result)->toEqual($statuses);
    });

    test('public ticket get statuses titles set', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('publicGetStatuses')->atLeast()->once()
            ->andReturn($statuses);
        $serviceMock->shouldReceive('publicCounter')
            ->andReturn($statuses);

        $api->setService($serviceMock);

        $data = [
            'titles' => true,
        ];
        $result = $api->public_ticket_get_statuses($data);

        expect($result)->toEqual($statuses);
    });

    test('helpdesk get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
        $paginatorMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn([]);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskGetSearchQuery')->atLeast()->once()
            ->andReturn(['query', []]);

        $di = container();
        $di['pager'] = $paginatorMock;

        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [];
        $result = $api->helpdesk_get_list($data);

        expect($result)->toBeArray();
    });

    test('helpdesk get pairs', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskGetPairs')->atLeast()->once()
            ->andReturn([]);

        $api->setService($serviceMock);

        $data = [];
        $result = $api->helpdesk_get_pairs($data);

        expect($result)->toBeArray();
    });

    test('helpdesk get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportHelpdesk());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskToApiArray')->atLeast()->once()
            ->andReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->helpdesk_get($data);

        expect($result)->toBeArray();
    });

    test('helpdesk update', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportHelpdesk());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskUpdate')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->helpdesk_update($data);

        expect($result)->toBeTrue();
    });

    test('helpdesk create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskCreate')->atLeast()->once()
            ->andReturn(1);

        $di = container();
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->helpdesk_create($data);

        expect($result)->toBeInt();
    });

    test('helpdesk delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportHelpdesk());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskRm')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'General',
        ];
        $result = $api->helpdesk_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
        $paginatorMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($resultSet);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedGetSearchQuery')->atLeast()->once()
            ->andReturn(['query', []]);
        $serviceMock->shouldReceive('cannedToApiArray')->atLeast()->once()
            ->andReturn([]);

        $model = new \Model_SupportPr();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = Mockery::mock('\Box_DAtabase');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [];
        $result = $api->canned_get_list($data);

        expect($result)->toBeArray();
    });

    test('canned pairs', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getAssoc')
    ->atLeast()->once()
    ->andReturn([1 => 'Title']);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $data = [];
        $result = $api->canned_pairs();

        expect($result)->toBeArray();
    });

    test('canned get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPr());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedToApiArray')->atLeast()->once()
            ->andReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->canned_get($data);

        expect($result)->toBeArray();
    });

    test('canned delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPr());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedRm')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->canned_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedCreate')->atLeast()->once()
            ->andReturn(1);

        $di = container();
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'title' => 'Title',
            'category_id' => random_int(1, 100),
            'content' => 'Content',
        ];
        $result = $api->canned_create($data);

        expect($result)->toBeInt();
    });

    test('canned update', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedUpdate')->atLeast()->once()
            ->andReturn(true);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPr());

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $api->canned_update($data);

        expect($result)->toBeTrue();
    });

    test('canned category pairs', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getAssoc')
    ->atLeast()->once()
    ->andReturn([1 => 'Category 1']);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $api->canned_category_pairs($data);

        expect($result)->toBeArray();
    });

    test('canned category get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportPrCategory());

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedCategoryToApiArray')->atLeast()->once()
            ->andReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->canned_category_get($data);

        expect($result)->toBeArray();
    });

    test('canned category update', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($supportCategory);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedCategoryUpdate')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;

        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'title' => 'Updated Title',
        ];
        $result = $api->canned_category_update($data);

        expect($result)->toBeTrue();
    });

    test('canned category delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($supportCategory);

        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('cannedCategoryRm')->atLeast()->once()
            ->andReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->canned_category_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned category create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
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
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('noteCreate')->atLeast()->once()
            ->andReturn(1);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'ticket_id' => 1,
            'note' => 'Note',
        ];
        $result = $api->note_create($data);

        expect($result)->toBeInt();
    });

    test('note delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('noteRm')->atLeast()->once()
            ->andReturn(true);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicketNote());

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);
        $api->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $api->note_delete($data);

        expect($result)->toBeTrue();
    });

    test('task complete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketTaskComplete')->atLeast()->once()
            ->andReturn(true);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;
        $api->setDi($di);

        $api->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $api->task_complete($data);

        expect($result)->toBeTrue();
    });

    test('batch delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $activityMock = Mockery::mock(\Box\Mod\Support\Api\Admin::class)->makePartial();
        $activityMock->shouldReceive('ticket_delete')->atLeast()->once()->andReturn(true);

        $di = container();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        expect($result)->toBeTrue();
    });

    test('batch delete public', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $activityMock = Mockery::mock(\Box\Mod\Support\Api\Admin::class)->makePartial();
        $activityMock->shouldReceive('public_ticket_delete')->atLeast()->once()->andReturn(true);

        $di = container();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_public(['ids' => [1, 2, 3]]);
        expect($result)->toBeTrue();
    });

    /*
    * Knowledge Base Tests.
    */

    test('kb article get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $di = container();

        $adminApi = new \Box\Mod\Support\Api\Admin();
        $adminApi->setDi($di);

        $data = [
            'status' => 'status',
            'search' => 'search',
            'cat' => 'category',
        ];

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbSearchArticles')
    ->atLeast()->once()
    ->andReturn(['list' => []]);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get_list($data);
        expect($result)->toBeArray();
    });

    test('kb article get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticle());

        $admin = new \Model_Admin();
        $admin->loadBean(new \Tests\Helpers\DummyBean());

        $admin->id = 5;

        $di = container();
        $di['loggedin_admin'] = $admin;
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get($data);
        expect($result)->toBeArray();
    });

    test('kb article get not found exception', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(false);

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);
        expect(fn () => $adminApi->kb_article_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb article create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'kb_article_category_id' => 1,
            'title' => 'Title',
        ];

        $id = 1;

        $di = container();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
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
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
            'kb_article_category_id' => 1,
            'title' => 'Title',
            'slug' => 'article-slug',
            'status' => 'active',
            'content' => 'Content',
            'views' => 1,
        ];

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
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
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticle());

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive('kbRm')->atLeast()->once();
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_delete($data);
        expect($result)->toBeTrue();
    });

    test('kb article delete not found exception', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(false);

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbRm")->never()
        ;
        $adminApi->setService($kbService);

        expect(fn () => $adminApi->kb_article_delete(['id' => 1]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get list', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

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

        $adminApi->setDi($di);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_list([]);
        expect($result)->toBeArray();
        expect($result)->toEqual($willReturn);
    });

    test('kb category get', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticleCategory());

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbCategoryToApiArray')
    ->atLeast()->once()
    ->andReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];
        $result = $adminApi->kb_category_get($data);
        expect($result)->toBeArray();
    });

    test('kb category get id not set exception', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_get', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category get not found exception', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(false);

        $di = container();

        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbCategoryToApiArray")->never()
            ->andReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        expect(fn () => $adminApi->kb_category_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category create', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
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
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbUpdateCategory')
    ->atLeast()->once()
    ->andReturn(true);
        $adminApi->setService($kbService);

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticleCategory());

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

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
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_update', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category update not found', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbUpdateCategory")->never()
            ->andReturn(true);
        $adminApi->setService($kbService);

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(false);

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
            'title' => 'Title',
            'slug' => 'category-slug',
            'description' => 'Description',
        ];

        expect(fn () => $adminApi->kb_category_update($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category delete', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbCategoryRm')
    ->atLeast()->once()
    ->andReturn(true);
        $adminApi->setService($kbService);

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportKbArticleCategory());

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];
        $result = $adminApi->kb_category_delete($data);
        expect($result)->toBeTrue();
    });

    test('kb category delete id not set', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_delete', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category delete not found', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService->shouldReceive("kbCategoryRm")->never()
            ->andReturn(true);
        $adminApi->setService($kbService);

        $db = Mockery::mock('Box_Database');
        $db
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(false);

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];

        expect(fn () => $adminApi->kb_category_delete($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get pairs', function (): void {
    $api = new \Box\Mod\Support\Api\Admin();
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $kbService
    ->shouldReceive('kbCategoryGetPairs')
    ->atLeast()->once()
    ->andReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_pairs([]);
        expect($result)->toBeArray();
    });
