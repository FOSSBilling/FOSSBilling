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
    $this->adminApi = new \Box\Mod\Cart\Api\Admin();
});

test('getList returns array', function (): void {
    $api = new \Box\Mod\Cart\Api\Admin();
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

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('getSearchQuery')->atLeast()->once()
        ->andReturn(['query', []]);
    $serviceMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $model = new \Model_Cart();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['pager'] = $paginatorMock;
    $di['db'] = $dbMock;

    $this->adminApi->setDi($di);

    $this->adminApi->setService($serviceMock);

    $data = [];
    $result = $this->adminApi->get_list($data);

    expect($result)->toBeArray();
});

test('get returns array', function (): void {
    $api = new \Box\Mod\Cart\Api\Admin();
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn(new \Model_Cart());

    $serviceMock = Mockery::mock(\Box\Mod\Cart\Service::class)->makePartial();
    $serviceMock->shouldReceive('toApiArray')->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['db'] = $dbMock;
    $this->adminApi->setDi($di);

    $this->adminApi->setService($serviceMock);

    $data = [
        'id' => 1,
    ];
    $result = $this->adminApi->get($data);

    expect($result)->toBeArray();
});

test('batchExpire returns true', function (): void {
    $api = new \Box\Mod\Cart\Api\Admin();

    $logStub = $this->createStub('\Box_Log');

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getAssoc')
    ->atLeast()->once()
    ->andReturn([1, date('Y-m-d H:i:s')]);
    $dbMock
    ->shouldReceive('exec')
    ->atLeast()->once()
    ->andReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = $logStub;
    $this->adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];
    $result = $this->adminApi->batch_expire($data);

    expect($result)->toBeTrue();
});
