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
    $this->api = new \Box\Mod\Hook\Api\Admin();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toEqual($di);
});

test('getList returns array', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Hook\Service::class);

    $serviceMock->shouldReceive('getSearchQuery')
        ->atLeast()
        ->once()
        ->andReturn(['SqlString', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock->shouldAllowMockingProtectedMethods();
    $paginatorMock->shouldReceive('getPaginatedResultSet')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $result = $this->api->get_list([]);
    expect($result)->toBeArray();
});

test('call fires event', function () {
    $data['event'] = 'testEvent';

    $eventManager = Mockery::mock('\Box_EventManager');
    $eventManager->shouldReceive('fire')
        ->atLeast()
        ->once()
        ->andReturn(1);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventManager;

    $this->api->setDi($di);
    $result = $this->api->call($data);
    expect($result)->not->toBeEmpty();
});

test('call returns false when event param is missing', function () {
    $data['event'] = null;

    $result = $this->api->call($data);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('batchConnect returns result', function () {
    $serviceMock = Mockery::mock(\Box\Mod\Hook\Service::class);

    $serviceMock->shouldReceive('batchConnect')
        ->atLeast()
        ->once()
        ->andReturn(true);

    $di = container();

    $this->api->setDi($di);

    $this->api->setService($serviceMock);
    $result = $this->api->batch_connect([]);
    expect($result)->not->toBeEmpty();
});
