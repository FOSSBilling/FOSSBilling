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

test('getDi returns dependency injection container', function (): void {
    $api = new \Box\Mod\Hook\Api\Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('getList returns array', function (): void {
    $api = new \Box\Mod\Hook\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Hook\Service::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $serviceMock->shouldReceive('getSearchQuery');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(['SqlString', []]);

    $paginatorMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $paginatorMock->shouldAllowMockingProtectedMethods();
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $paginatorMock->shouldReceive('getPaginatedResultSet');
    $expectation2->atLeast()->once();
    $expectation2->andReturn([]);

    $di = container();
    $di['pager'] = $paginatorMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $result = $api->get_list([]);
    expect($result)->toBeArray();
});

test('call fires event', function (): void {
    $api = new \Box\Mod\Hook\Api\Admin();
    $data['event'] = 'testEvent';

    $eventManager = Mockery::mock('\Box_EventManager');
    /** @var \Mockery\Expectation $expectation */
    $expectation = $eventManager->shouldReceive('fire');
    $expectation->atLeast()->once();
    $expectation->andReturn(1);

    $di = container();
    $di['logger'] = new \Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventManager;

    $api->setDi($di);
    $result = $api->call($data);
    expect($result)->not->toBeEmpty();
});

test('call returns false when event param is missing', function (): void {
    $api = new \Box\Mod\Hook\Api\Admin();
    $data['event'] = null;

    $result = $api->call($data);
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});

test('batchConnect returns result', function (): void {
    $api = new \Box\Mod\Hook\Api\Admin();
    $serviceMock = Mockery::mock(\Box\Mod\Hook\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('batchConnect');
    $expectation->atLeast()->once();
    $expectation->andReturn(true);

    $di = container();

    $api->setDi($di);

    $api->setService($serviceMock);
    $result = $api->batch_connect([]);
    expect($result)->not->toBeEmpty();
});
