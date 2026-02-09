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
    $this->clientApi = new \Box\Mod\Support\Api\Client();
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

        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [];
        $result = $this->clientApi->ticket_get_list($data);

        expect($result)->toBeArray();
    });

    test('ticket get', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['findOneByClient', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->willReturn(new \Model_SupportTicket());
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $di = container();
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'id' => 1,
        ];
        $result = $this->clientApi->ticket_get($data);

        expect($result)->toBeArray();
    });

    test('helpdesk get pairs', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['helpdeskGetPairs'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('helpdeskGetPairs')
            ->willReturn([0 => 'General']);

        $this->clientApi->setService($serviceMock);

        $result = $this->clientApi->helpdesk_get_pairs();

        expect($result)->toBeArray();
    });

    test('ticket create', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['ticketCreateForClient'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('ticketCreateForClient')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_SupportHelpdesk());

        $di = container();
        $di['db'] = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'subject' => 'Subject',
            'support_helpdesk_id' => 1,
        ];
        $result = $this->clientApi->ticket_create($data);

        expect($result)->toBeInt();
    });

    test('ticket reply', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['canBeReopened', 'ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->willReturn(true);
        $serviceMock->expects($this->atLeastOnce())->method('ticketReply')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => 1,
        ];
        $result = $this->clientApi->ticket_reply($data);

        expect($result)->toBeBool();
        expect($result)->toBeTrue();
    });

    test('ticket reply can not be reopened exception', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['canBeReopened', 'ticketReply'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('canBeReopened')
            ->willReturn(false);
        $serviceMock->expects($this->never())->method('ticketReply')
            ->willReturn(1);

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('findOne')
            ->willReturn(new \Model_SupportTicket());

        $di = container();
        $di['db'] = $dbMock;

        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => 1,
        ];
        expect(fn () => $this->clientApi->ticket_reply($data))->toThrow(\FOSSBilling\Exception::class);
    });

    test('ticket close', function () {
        $serviceMock = $this->getMockBuilder(\Box\Mod\Support\Service::class)
            ->onlyMethods(['findOneByClient', 'closeTicket'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('findOneByClient')
            ->willReturn(new \Model_SupportTicket());
        $serviceMock->expects($this->atLeastOnce())->method('closeTicket')
            ->willReturn(true);

        $di = container();
        $this->clientApi->setDi($di);

        $client = new \Model_Client();
        $client->loadBean(new \Tests\Helpers\DummyBean());
        $client->id = 1;

        $this->clientApi->setService($serviceMock);
        $this->clientApi->setIdentity($client);

        $data = [
            'content' => 'Content',
            'id' => 1,
        ];
        $result = $this->clientApi->ticket_close($data);

        expect($result)->toBeTrue();
    });
