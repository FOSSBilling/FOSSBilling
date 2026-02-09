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
    $this->api = new \Box\Mod\Client\Api\Client();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toEqual($di);
});

test('balanceGetList returns array', function () {
    $data = [];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->createMock(\Box\Mod\Client\ServiceBalance::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getSearchQuery')
        ->willReturn(['sql', []]);

    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];

    $pagerMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
    ->onlyMethods(['getPaginatedResultSet'])
    ->disableOriginalConstructor()
    ->getMock();
    $pagerMock->expects($this->atLeastOnce())
        ->method('getPaginatedResultSet')
        ->willReturn($simpleResultArr);

    $model = new \Model_ClientBalance();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = $this->createMock('\Box_Database');
    $dbMock->expects($this->atLeastOnce())
        ->method('getExistingModelById')
        ->willReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);
    $di['pager'] = $pagerMock;
    $di['db'] = $dbMock;

    $this->api->setDi($di);
    $this->api->setService($serviceMock);
    $this->api->setIdentity($model);

    $result = $this->api->balance_get_list($data);

    expect($result)->toBeArray();
});

test('balanceGetTotal returns float', function () {
    $balanceAmount = 0.00;
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = $this->createMock(\Box\Mod\Client\ServiceBalance::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('getClientBalance')
        ->willReturn($balanceAmount);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name, $sub): \PHPUnit\Framework\MockObject\MockObject => $serviceMock);

    $this->api->setDi($di);
    $this->api->setIdentity($model);

    $result = $this->api->balance_get_total();

    expect($result)->toBeFloat();
    expect($result)->toEqual($balanceAmount);
});

test('isTaxable returns boolean', function () {
    $clientIsTaxable = true;

    $serviceMock = $this->createMock(\Box\Mod\Client\Service::class);
    $serviceMock->expects($this->atLeastOnce())
        ->method('isClientTaxable')
        ->willReturn($clientIsTaxable);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $this->api->setService($serviceMock);
    $this->api->setIdentity($client);

    $result = $this->api->is_taxable();
    expect($result)->toBeBool();
    expect($result)->toEqual($clientIsTaxable);
});
