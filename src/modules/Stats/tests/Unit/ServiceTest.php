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
    $this->service = new \Box\Mod\Stats\Service();
});

test('gets dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toBe($di);
});

test('gets order statuses', function () {
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    $orderServiceMock->shouldReceive('counter')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $this->service->setDi($di);

    $result = $this->service->getOrdersStatuses([]);
    expect($result)->toBeArray();
});

test('gets product summary', function () {
    $data = [];

    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);
    $result = $this->service->getProductSummary($data);
    expect($result)->toBeArray();
});

test('gets summary', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchOne')
        ->atLeast()->once()
        ->andReturn(null);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->service->setDi($di);

    $expected = [
        'clients_total' => null,
        'clients_today' => null,
        'clients_yesterday' => null,
        'clients_this_month' => null,
        'clients_last_month' => null,

        'orders_total' => null,
        'orders_today' => null,
        'orders_yesterday' => null,
        'orders_this_month' => null,
        'orders_last_month' => null,

        'invoices_total' => null,
        'invoices_today' => null,
        'invoices_yesterday' => null,
        'invoices_this_month' => null,
        'invoices_last_month' => null,

        'tickets_total' => null,
        'tickets_today' => null,
        'tickets_yesterday' => null,
        'tickets_this_month' => null,
        'tickets_last_month' => null,
    ];

    $result = $this->service->getSummary();
    expect($result)->toBeArray()
        ->and($result)->toBe($expected);
});

test('gets summary income', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchOne')
        ->atLeast()->once()
        ->andReturn(null);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->service->setDi($di);

    $expected = [
        'total' => null,
        'today' => null,
        'yesterday' => null,
        'this_month' => null,
        'last_month' => null,
    ];

    $result = $this->service->getSummaryIncome();
    expect($result)->toBeArray()
        ->and($result)->toBe($expected);
});

test('gets product sales', function () {
    $res = ['testProduct' => 1];
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn($res);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $this->service->getProductSales($data);
    expect($result)->toBeArray();
});

test('gets income and refund stats', function () {
    $res = [
        [
            'refund' => 0,
            'income' => 0,
        ],
    ];
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($res);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->service->setDi($di);

    $result = $this->service->incomeAndRefundStats([]);
    expect($result)->toBeArray()
        ->and($result)->toBe($res[0]);
});

test('gets refunds', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $this->service->getRefunds($data);
    expect($result)->toBeArray();
});

test('gets income', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $this->service->getIncome($data);
    expect($result)->toBeArray();
});

test('gets client countries', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);

    $result = $this->service->getClientCountries([]);
    expect($result)->toBeArray();
});

test('gets sales by country', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);

    $result = $this->service->getSalesByCountry([]);
    expect($result)->toBeArray();
});

test('gets table stats', function () {
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    $resultMock->shouldReceive('fetchAllKeyValue')
        ->atLeast()->once()
        ->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('executeQuery')
        ->atLeast()->once()
        ->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $this->service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $this->service->getTableStats('client', $data);
    expect($result)->toBeArray();
});
