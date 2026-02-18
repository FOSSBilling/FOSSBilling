<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;

test('get list', function () {
    $clientApi = new Box\Mod\Email\Api\Client();
    $emailService = new Box\Mod\Email\Service();

    $willReturn = [
        'list' => [
            'id' => 1,
        ],
    ];
    $pager = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pager
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $di = container();
    $di['pager'] = $pager;

    $clientApi->setDi($di);
    $emailService->setDi($di);

    $service = $emailService;
    $clientApi->setService($service);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;
    $clientApi->setIdentity($client);

    $result = $clientApi->get_list([]);
    expect($result)->toBeArray();

    expect($result)->toHaveKey('list');
    expect($result['list'])->toBeArray();
});

test('get', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $model = new Model_ActivityClientEmail();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn($model);
    $service
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);
    $clientApi->setService($service);

    $di = container();
    $clientApi->setDi($di);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 1;
    $clientApi->setIdentity($client);

    $result = $clientApi->get(['id' => 1]);
    expect($result)->toBeArray();
});

test('get not found exception', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn(false);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;
    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\Exception::class);
    $result = $clientApi->get(['id' => 1]);
    expect($result)->toBeArray();
});

test('resend', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $model = new Model_ActivityClientEmail();
    $model->loadBean(new Tests\Helpers\DummyBean());

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn($model);
    $service
    ->shouldReceive('resend')
    ->atLeast()->once()
    ->andReturn(true);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;
    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $result = $clientApi->resend(['id' => 1]);
    expect($result)->toBeTrue();
});

test('resend not found exception', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn(false);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;

    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\Exception::class);
    $result = $clientApi->resend(['id' => 1]);
    expect($result)->toBeArray();
});

test('delete', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $di = container();

    $model = new Model_ActivityClientEmail();
    $model->loadBean(new Tests\Helpers\DummyBean());
    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn($model);
    $service
    ->shouldReceive('rm')
    ->atLeast()->once()
    ->andReturn(true);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;
    $clientApi->setIdentity($client);

    $clientApi->setDi($di);

    $clientApi->setService($service);

    $result = $clientApi->delete(['id' => 1]);
    expect($result)->toBeTrue();
});

test('delete not found exception', function () {
    $clientApi = new Box\Mod\Email\Api\Client();

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service
    ->shouldReceive('findOneForClientById')
    ->atLeast()->once()
    ->andReturn(false);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;

    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\Exception::class);
    $result = $clientApi->delete(['id' => 1]);
    expect($result)->toBeArray();
});
