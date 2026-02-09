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
    $this->adminApi = new \Box\Mod\Support\Api\Admin();
});

test('ticket get list', function () {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $model = new \Model_SupportTicket();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->ticket_get_list($data);

        expect($result)->toBeArray();
    });

    test('ticket get', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_get($data);

        expect($result)->toBeArray();
    });

    test('ticket update', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketUpdate')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_update($data);

        expect($result)->toBeTrue();
    });

    test('ticket message update', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicketMessage());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketMessageUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketMessageUpdate')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->ticket_message_update($data);

        expect($result)->toBeTrue();
    });

    test('ticket delete', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['rm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('rm')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_delete($data);

        expect($result)->toBeTrue();
    });

    test('ticket reply', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->willReturn(1);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->ticket_reply($data);

        expect($result)->toBeInt();
    });

    test('ticket close', function () {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['closeTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('ticket close already closed', function () {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->status = \Model_SupportTicket::CLOSED;

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['closeTicket'])->getMock();
        $serviceMock->expects($this->never())->method('closeTicket')
        ;

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('ticket create', function () {
        $clientModel = new \Model_Client();
        $clientModel->loadBean(new \Tests\Helpers\DummyBean());

        $supportHelpdeskModel = new \Model_SupportHelpdesk();
        $supportHelpdeskModel->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($clientModel, $supportHelpdeskModel);

        $randID = 1;
        $serviceMock = $this->createMock(\Box\Mod\Support\Service::class);
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForAdmin')
            ->willReturn($randID);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'client_id' => 1,
            'content' => 'Content',
            'subject' => 'Subject',
            'support_helpdesk_id' => 1,
        ];
        $result = $this->adminApi->ticket_create($data);

        expect($result)->toBeInt();
        expect($result)->toEqual($randID);
    });

    test('batch ticket auto close', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getExpired', 'autoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->willReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
            ->willReturn(true);

        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $this->adminApi->setService($serviceMock);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createStub('Box_Log');
        $this->adminApi->setDi($di);
        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch ticket auto close not closed', function () {
        $ticket = new \Model_SupportTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getExpired', 'autoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getExpired')
            ->willReturn([['id' => 1], ['id' => 2]]);
        $serviceMock->expects($this->atLeastOnce())->method('autoClose')
        ;

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($ticket);

        $this->adminApi->setService($serviceMock);
        $di = container();
        $di['logger'] = $this->createStub('Box_Log');
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch public ticket auto close', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetExpired', 'publicAutoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->willReturn([new \Model_SupportPTicket(), new \Model_SupportPTicket()]);
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
            ->willReturn(true);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->batch_public_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('batch public ticket auto close not closed', function () {
        $ticket = new \Model_SupportPTicket();
        $ticket->loadBean(new \Tests\Helpers\DummyBean());
        $ticket->id = 1;

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetExpired', 'publicAutoClose'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetExpired')
            ->willReturn([$ticket, $ticket]);
        $serviceMock->expects($this->atLeastOnce())->method('publicAutoClose')
        ;

        $this->adminApi->setService($serviceMock);
        $di = container();
        $di['logger'] = $this->createStub('Box_Log');
        $this->adminApi->setDi($di);
        $result = $this->adminApi->batch_public_ticket_auto_close([]);

        expect($result)->toBeTrue();
    });

    test('ticket get statuses', function () {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getStatuses', 'counter'])->getMock();
        $serviceMock->expects($this->never())->method('getStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->atLeastOnce())->method('counter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->ticket_get_statuses([]);

        expect($result)->toEqual($statuses);
    });

    test('ticket get statuses titles set', function () {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['getStatuses', 'counter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->never())->method('counter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $data = [
            'titles' => true,
        ];
        $result = $this->adminApi->ticket_get_statuses($data);

        expect($result)->toEqual($statuses);
    });

    test('public ticket get list', function () {
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetSearchQuery', 'publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn(['query', []]);

        $model = new \Model_SupportPTicket();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = $this->createMock('\Box_DAtabase');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->public_ticket_get_list($data);

        expect($result)->toBeArray();
    });

    test('public ticket create', function () {
        $randID = 1;
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketCreate')
            ->willReturn($randID);

        $di = container();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'name' => 'Name',
            'email' => 'email@example.com',
            'subject' => 'Subject',
            'message' => 'Message',
        ];

        $result = $this->adminApi->public_ticket_create($data);

        expect($result)->toBeInt();
        expect($result)->toEqual($randID);
    });

    test('public ticket get', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $randID = 1;
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicToApiArray')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_get($data);

        expect($result)->toBeArray();
    });

    test('public ticket delete', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicRm')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_delete($data);

        expect($result)->toBeTrue();
    });

    test('public ticket close', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicCloseTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicCloseTicket')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_close($data);

        expect($result)->toBeTrue();
    });

    test('public ticket update', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketUpdate')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->public_ticket_update($data);

        expect($result)->toBeTrue();
    });

    test('public ticket reply', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPTicket());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicTicketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicTicketReply')
            ->willReturn(1);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'content' => 'Content',
        ];
        $result = $this->adminApi->public_ticket_reply($data);

        expect($result)->toBeInt();
    });

    test('public ticket get statuses', function () {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetStatuses', 'publicCounter'])->getMock();
        $serviceMock->expects($this->never())->method('publicGetStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->atLeastOnce())->method('publicCounter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $result = $this->adminApi->public_ticket_get_statuses([]);

        expect($result)->toEqual($statuses);
    });

    test('public ticket get statuses titles set', function () {
        $statuses = [
            \Model_SupportPTicket::OPENED => 'Open',
            \Model_SupportPTicket::ONHOLD => 'On hold',
            \Model_SupportPTicket::CLOSED => 'Closed',
        ];
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['publicGetStatuses', 'publicCounter'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('publicGetStatuses')
            ->willReturn($statuses);
        $serviceMock->expects($this->never())->method('publicCounter')
            ->willReturn($statuses);

        $this->adminApi->setService($serviceMock);

        $data = [
            'titles' => true,
        ];
        $result = $this->adminApi->public_ticket_get_statuses($data);

        expect($result)->toEqual($statuses);
    });

    test('helpdesk get list', function () {
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn([]);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetSearchQuery'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetSearchQuery')
            ->willReturn(['query', []]);

        $di = container();
        $di['pager'] = $paginatorMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->helpdesk_get_list($data);

        expect($result)->toBeArray();
    });

    test('helpdesk get pairs', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetPairs'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->willReturn([]);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->helpdesk_get_pairs($data);

        expect($result)->toBeArray();
    });

    test('helpdesk get', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskToApiArray')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_get($data);

        expect($result)->toBeArray();
    });

    test('helpdesk update', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskUpdate')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_update($data);

        expect($result)->toBeTrue();
    });

    test('helpdesk create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskCreate')
            ->willReturn(1);

        $di = container();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->helpdesk_create($data);

        expect($result)->toBeInt();
    });

    test('helpdesk delete', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskRm')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'General',
        ];
        $result = $this->adminApi->helpdesk_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned get list', function () {
        $resultSet = [
            'list' => [
                0 => ['id' => 1],
            ],
        ];
        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($resultSet);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedGetSearchQuery', 'cannedToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedGetSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->willReturn([]);

        $model = new \Model_SupportPr();
        $model->loadBean(new \Tests\Helpers\DummyBean());
        $dbMock = $this->createMock('\Box_DAtabase');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = container();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->canned_get_list($data);

        expect($result)->toBeArray();
    });

    test('canned pairs', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([1 => 'Title']);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $data = [];
        $result = $this->adminApi->canned_pairs();

        expect($result)->toBeArray();
    });

    test('canned get', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedToApiArray')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_get($data);

        expect($result)->toBeArray();
    });

    test('canned delete', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedRm')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCreate')
            ->willReturn(1);

        $di = container();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'title' => 'Title',
            'category_id' => random_int(1, 100),
            'content' => 'Content',
        ];
        $result = $this->adminApi->canned_create($data);

        expect($result)->toBeInt();
    });

    test('canned update', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedUpdate')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPr());

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $this->adminApi->canned_update($data);

        expect($result)->toBeTrue();
    });

    test('canned category pairs', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([1 => 'Category 1']);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 'Title',
        ];
        $result = $this->adminApi->canned_category_pairs($data);

        expect($result)->toBeArray();
    });

    test('canned category get', function () {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportPrCategory());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryToApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryToApiArray')
            ->willReturn([]);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_category_get($data);

        expect($result)->toBeArray();
    });

    test('canned category update', function () {
        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($supportCategory);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryUpdate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryUpdate')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
            'title' => 'Updated Title',
        ];
        $result = $this->adminApi->canned_category_update($data);

        expect($result)->toBeTrue();
    });

    test('canned category delete', function () {
        $supportCategory = new \Model_SupportPrCategory();
        $supportCategory->loadBean(new \Tests\Helpers\DummyBean());

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($supportCategory);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryRm')
            ->willReturn(true);

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->canned_category_delete($data);

        expect($result)->toBeTrue();
    });

    test('canned category create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['cannedCategoryCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('cannedCategoryCreate')
            ->willReturn(1);

        $di = container();
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'title' => 'Title',
        ];
        $result = $this->adminApi->canned_category_create($data);

        expect($result)->toBeInt();
    });

    test('note create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['noteCreate'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteCreate')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'ticket_id' => 1,
            'note' => 'Note',
        ];
        $result = $this->adminApi->note_create($data);

        expect($result)->toBeInt();
    });

    test('note delete', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['noteRm'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('noteRm')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicketNote());

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);
        $this->adminApi->setIdentity(new \Model_Admin());

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->note_delete($data);

        expect($result)->toBeTrue();
    });

    test('task complete', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketTaskComplete'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketTaskComplete')
            ->willReturn(true);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->task_complete($data);

        expect($result)->toBeTrue();
    });

    test('batch delete', function () {
        $activityMock = $this->getMockBuilder(\Box\Mod\Support\Api\Admin::class)->onlyMethods(['ticket_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('ticket_delete')->willReturn(true);

        $di = container();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete(['ids' => [1, 2, 3]]);
        expect($result)->toBeTrue();
    });

    test('batch delete public', function () {
        $activityMock = $this->getMockBuilder(\Box\Mod\Support\Api\Admin::class)->onlyMethods(['public_ticket_delete'])->getMock();
        $activityMock->expects($this->atLeastOnce())->method('public_ticket_delete')->willReturn(true);

        $di = container();
        $activityMock->setDi($di);

        $result = $activityMock->batch_delete_public(['ids' => [1, 2, 3]]);
        expect($result)->toBeTrue();
    });

    /*
    * Knowledge Base Tests.
    */

    test('kb article get list', function () {
        $di = container();

        $adminApi = new \Box\Mod\Support\Api\Admin();
        $adminApi->setDi($di);

        $data = [
            'status' => 'status',
            'search' => 'search',
            'cat' => 'category',
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbSearchArticles'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbSearchArticles')
            ->willReturn(['list' => []]);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get_list($data);
        expect($result)->toBeArray();
    });

    test('kb article get', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticle());

        $admin = new \Model_Admin();
        $admin->loadBean(new \Tests\Helpers\DummyBean());

        $admin->id = 5;

        $di = container();
        $di['loggedin_admin'] = $admin;
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbToApiArray'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_get($data);
        expect($result)->toBeArray();
    });

    test('kb article get not found exception', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);
        expect(fn () => $adminApi->kb_article_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb article create', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'kb_article_category_id' => 1,
            'title' => 'Title',
        ];

        $id = 1;

        $di = container();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCreateArticle'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateArticle')
            ->willReturn($id);
        $adminApi->setService($kbService);

        $adminApi->setDi($di);

        $result = $adminApi->kb_article_create($data);
        expect($result)->toBeInt();
        expect($result)->toEqual($id);
    });

    test('kb article update', function () {
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

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateArticle'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateArticle')
            ->willReturn(true);
        $di = container();

        $adminApi->setDi($di);

        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_update($data);
        expect($result)->toBeTrue();
    });

    test('kb article delete', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $data = [
            'id' => 1,
        ];

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticle());

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbRm'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbRm')
        ;
        $adminApi->setService($kbService);

        $result = $adminApi->kb_article_delete($data);
        expect($result)->toBeTrue();
    });

    test('kb article delete not found exception', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = container();
        $di['db'] = $db;

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbRm'])->getMock();
        $kbService->expects($this->never())
            ->method('kbRm')
        ;
        $adminApi->setService($kbService);

        expect(fn () => $adminApi->kb_article_delete(['id' => 1]))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get list', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $willReturn = [
            'pages' => 5,
            'page' => 2,
            'per_page' => 2,
            'total' => 10,
            'list' => [],
        ];

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetSearchQuery'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetSearchQuery')
            ->willReturn(['String', []]);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $pager->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($willReturn);

        $di = container();
        $di['pager'] = $pager;

        $adminApi->setDi($di);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_list([]);
        expect($result)->toBeArray();
        expect($result)->toEqual($willReturn);
    });

    test('kb category get', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryToApiArray'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];
        $result = $adminApi->kb_category_get($data);
        expect($result)->toBeArray();
    });

    test('kb category get id not set exception', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_get', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category get not found exception', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = container();

        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();
        $adminApi->setDi($di);

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryToApiArray'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryToApiArray')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $data = [
            'id' => 1,
        ];

        expect(fn () => $adminApi->kb_category_get($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category create', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCreateCategory'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCreateCategory')
            ->willReturn(1);
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

    test('kb category update', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateCategory'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbUpdateCategory')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

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

    test('kb category update id not set', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_update', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category update not found', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbUpdateCategory'])->getMock();
        $kbService->expects($this->never())
            ->method('kbUpdateCategory')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

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

    test('kb category delete', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryRm'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryRm')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportKbArticleCategory());

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

    test('kb category delete id not set', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $apiHandler = new \Api_Handler(new \Model_Admin());
        $reflection = new \ReflectionClass($apiHandler);
        $method = $reflection->getMethod('validateRequiredParams');

        expect(fn () => $method->invokeArgs($apiHandler, [$adminApi, 'kb_category_delete', []]))
            ->toThrow(\FOSSBilling\InformationException::class);
    });

    test('kb category delete not found', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryRm'])->getMock();
        $kbService->expects($this->never())
            ->method('kbCategoryRm')
            ->willReturn(true);
        $adminApi->setService($kbService);

        $db = $this->createMock('Box_Database');
        $db->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(false);

        $di = container();
        $di['db'] = $db;
        $di['validator'] = new \FOSSBilling\Validate();

        $adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];

        expect(fn () => $adminApi->kb_category_delete($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('kb category get pairs', function () {
        $adminApi = new \Box\Mod\Support\Api\Admin();

        $kbService = $this->getMockBuilder(\Box\Mod\Support\Service::class)->onlyMethods(['kbCategoryGetPairs'])->getMock();
        $kbService->expects($this->atLeastOnce())
            ->method('kbCategoryGetPairs')
            ->willReturn([]);
        $adminApi->setService($kbService);

        $result = $adminApi->kb_category_get_pairs([]);
        expect($result)->toBeArray();
    });
