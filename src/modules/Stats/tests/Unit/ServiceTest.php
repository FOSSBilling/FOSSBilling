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

test('gets dependency injection container', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets order statuses', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $orderServiceMock = Mockery::mock(\Box\Mod\Order\Service::class);
    /** @var \Mockery\Expectation $expectation */
    $expectation = $orderServiceMock->shouldReceive('counter');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): \Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->getOrdersStatuses([]);
    expect($result)->toBeArray();
});

test('gets product summary', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $data = [];

    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllAssociative');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);
    $result = $service->getProductSummary($data);
    expect($result)->toBeArray();
});

test('gets summary', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

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

    $result = $service->getSummary();
    expect($result)->toBeArray()
        ->and($result)->toBe($expected);
});

test('gets summary income', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $expected = [
        'total' => null,
        'today' => null,
        'yesterday' => null,
        'this_month' => null,
        'last_month' => null,
    ];

    $result = $service->getSummaryIncome();
    expect($result)->toBeArray()
        ->and($result)->toBe($expected);
});

test('gets product sales', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $res = ['testProduct' => 1];
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($res);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $service->getProductSales($data);
    expect($result)->toBeArray();
});

test('gets income and refund stats', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $res = [
        [
            'refund' => 0,
            'income' => 0,
        ],
    ];
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllAssociative');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($res);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $result = $service->incomeAndRefundStats([]);
    expect($result)->toBeArray()
        ->and($result)->toBe($res[0]);
});

test('gets refunds', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $service->getRefunds($data);
    expect($result)->toBeArray();
});

test('gets income', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $service->getIncome($data);
    expect($result)->toBeArray();
});

test('gets client countries', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $result = $service->getClientCountries([]);
    expect($result)->toBeArray();
});

test('gets sales by country', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $result = $service->getSalesByCountry([]);
    expect($result)->toBeArray();
});

test('gets table stats', function (): void {
    $service = new \Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(\Doctrine\DBAL\Result::class);
    /** @var \Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(\Doctrine\DBAL\Connection::class);
    /** @var \Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['dbal'] = $dbalMock;

    $service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $service->getTableStats('client', $data);
    expect($result)->toBeArray();
});
