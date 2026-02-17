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

test('ticket get list', function (): void {
    $api = new \Box\Mod\Support\Api\Client();
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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
            ->andReturn(new \Model_SupportTicket());
        $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
            ->andReturn([]);

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('helpdeskGetPairs')->atLeast()->once()
            ->andReturn([0 => 'General']);

        $this->clientApi->setService($serviceMock);

        $result = $this->clientApi->helpdesk_get_pairs();

        expect($result)->toBeArray();
    });

    test('ticket create', function () {
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('ticketCreateForClient')->atLeast()->once()
            ->andReturn(1);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportHelpdesk());

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
            ->andReturn(true);
        $serviceMock->shouldReceive('ticketReply')->atLeast()->once()
            ->andReturn(1);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('canBeReopened')->atLeast()->once()
            ->andReturn(false);
        $serviceMock->shouldReceive('ticketReply')
            ->andReturn(1);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock
    ->shouldReceive('findOne')
    ->atLeast()->once()
    ->andReturn(new \Model_SupportTicket());

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
        $serviceMock = Mockery::mock(\Box\Mod\Support\Service::class)->makePartial();
        $serviceMock->shouldReceive('findOneByClient')->atLeast()->once()
            ->andReturn(new \Model_SupportTicket());
        $serviceMock->shouldReceive('closeTicket')->atLeast()->once()
            ->andReturn(true);

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
