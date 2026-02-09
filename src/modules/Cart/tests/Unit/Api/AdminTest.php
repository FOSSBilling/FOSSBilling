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

test('getList returns array', function () {
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

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
        ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
    $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
        ->willReturn(['query', []]);
    $serviceMock->expects($this->atLeastOnce())
        ->method('toApiArray')
        ->willReturn([]);

    $model = new \Model_Cart();
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
    $result = $this->adminApi->get_list($data);

    expect($result)->toBeArray();
});

test('get returns array', function () {
    $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn(new \Model_Cart());

    $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
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
    $result = $this->adminApi->get($data);

    expect($result)->toBeArray();
});

test('batchExpire returns true', function () {
    $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
    $dbMock->expects($this->atLeastOnce())
        ->method('getAssoc')
        ->willReturn([1, date('Y-m-d H:i:s')]);
    $dbMock->expects($this->atLeastOnce())
        ->method('exec')
        ->willReturn(null);

    $di = container();
    $di['db'] = $dbMock;
    $di['logger'] = $this->createStub('Box_Log');
    $this->adminApi->setDi($di);

    $data = [
        'id' => 1,
    ];
    $result = $this->adminApi->batch_expire($data);

    expect($result)->toBeTrue();
});
