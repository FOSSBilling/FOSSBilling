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

test('get list', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $willReturn = [
        'list' => [
            'id' => 1,
        ],
    ];
    $pager = Mockery::mock(FOSSBilling\Pagination::class)->makePartial();
    $pager
        ->shouldReceive('paginateDoctrineQuery')
        ->atLeast()->once()
        ->andReturn($willReturn);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $qb = Mockery::mock(Doctrine\ORM\QueryBuilder::class);
    $repo->shouldReceive('getSearchQueryBuilder')->andReturn($qb);

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $di = container();
    $di['pager'] = $pager;

    $clientApi->setDi($di);
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

test('get', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->atLeast()->once()
        ->andReturn($model);

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $service->shouldNotReceive('toApiArray');
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

test('get not found exception', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->andThrow(new FOSSBilling\InformationException('Email not found'));

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;
    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\InformationException::class);
    $result = $clientApi->get(['id' => 1]);
    expect($result)->toBeArray();
});

test('resend', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->atLeast()->once()
        ->andReturn($model);

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);
    $service->shouldReceive('resend')
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

test('resend not found exception', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->andThrow(new FOSSBilling\InformationException('Email not found'));

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;

    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\InformationException::class);
    $result = $clientApi->resend(['id' => 1]);
    expect($result)->toBeArray();
});

test('delete', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $di = container();

    $model = new Box\Mod\Email\Entity\ActivityClientEmail();
    \Tests\Helpers\setEntityId($model, 1);

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->atLeast()->once()
        ->andReturn($model);

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;
    $clientApi->setIdentity($client);

    $clientApi->setDi($di);

    $clientApi->setService($service);

    $result = $clientApi->delete(['id' => 1]);
    expect($result)->toBeTrue();
});

test('delete not found exception', function (): void {
    $clientApi = new Box\Mod\Email\Api\Client();

    $repo = Mockery::mock(Box\Mod\Email\Repository\ActivityClientEmailRepository::class);
    $repo->shouldReceive('findOneForClientByIdOrFail')
        ->andThrow(new FOSSBilling\InformationException('Email not found'));

    $service = Mockery::mock(Box\Mod\Email\Service::class)->makePartial();
    $service->shouldReceive('getActivityClientEmailRepository')->andReturn($repo);

    $client = new Model_Client();
    $client->loadBean(new Tests\Helpers\DummyBean());
    $client->id = 5;

    $clientApi->setIdentity($client);

    $di = container();
    $clientApi->setDi($di);

    $clientApi->setService($service);

    $this->expectException(FOSSBilling\InformationException::class);
    $result = $clientApi->delete(['id' => 1]);
    expect($result)->toBeArray();
});
