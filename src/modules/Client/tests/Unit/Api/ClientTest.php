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
    $api = new \Box\Mod\Client\Api\Client();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('balanceGetList returns array', function (): void {
    $api = new \Box\Mod\Client\Api\Client();
    $data = [];

    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Client\ServiceBalance::class);
    $serviceMock
    ->shouldReceive('getSearchQuery')
    ->atLeast()->once()
    ->andReturn(['sql', []]);
    $serviceMock
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn([]);

    $simpleResultArr = [
        'list' => [
            ['id' => 1],
        ],
    ];

    $pagerMock = Mockery::mock(\FOSSBilling\Pagination::class)->makePartial();
    $pagerMock
    ->shouldReceive('getPaginatedResultSet')
    ->atLeast()->once()
    ->andReturn($simpleResultArr);

    $model = new \Model_ClientBalance();
    $model->loadBean(new \Tests\Helpers\DummyBean());
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock
    ->shouldReceive('getExistingModelById')
    ->atLeast()->once()
    ->andReturn($model);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): \Mockery\MockInterface => $serviceMock);
    $di['pager'] = $pagerMock;
    $di['db'] = $dbMock;

    $api->setDi($di);
    $api->setService($serviceMock);
    $api->setIdentity($model);

    $result = $api->balance_get_list($data);

    expect($result)->toBeArray();
});

test('balanceGetTotal returns float', function (): void {
    $api = new \Box\Mod\Client\Api\Client();
    $balanceAmount = 0.00;
    $model = new \Model_Client();
    $model->loadBean(new \Tests\Helpers\DummyBean());

    $serviceMock = Mockery::mock(\Box\Mod\Client\ServiceBalance::class);
    $serviceMock
    ->shouldReceive('getClientBalance')
    ->atLeast()->once()
    ->andReturn($balanceAmount);

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name, $sub): \Mockery\MockInterface => $serviceMock);

    $api->setDi($di);
    $api->setIdentity($model);

    $result = $api->balance_get_total();

    expect($result)->toBeFloat();
    expect($result)->toEqual($balanceAmount);
});

test('isTaxable returns boolean', function (): void {
    $api = new \Box\Mod\Client\Api\Client();
    $clientIsTaxable = true;

    $serviceMock = Mockery::mock(\Box\Mod\Client\Service::class);
    $serviceMock
    ->shouldReceive('isClientTaxable')
    ->atLeast()->once()
    ->andReturn($clientIsTaxable);

    $client = new \Model_Client();
    $client->loadBean(new \Tests\Helpers\DummyBean());

    $api->setService($serviceMock);
    $api->setIdentity($client);

    $result = $api->is_taxable();
    expect($result)->toBeBool();
    expect($result)->toEqual($clientIsTaxable);
});
