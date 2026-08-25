<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;

use function Tests\Helpers\container;

/**
 * A real SQLite connection (not a mock) with minimal `client`/`client_order`/`invoice`/
 * `support_ticket` tables - just enough columns for getSummary()/getSummaryIncome() to run their
 * real SQL against. This is the regression test for the DATE_FORMAT()/CURDATE()/DATE_SUB()
 * portability fix: those MySQL-only functions would raise a syntax error on SQLite, so a plain
 * pass here already proves the rewritten day/month boundary queries are portable syntax. It also
 * pins the actual day-boundary math against real inserted rows.
 */
function statsConnectionWithRows(): Doctrine\DBAL\Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    foreach (['client', 'client_order', 'invoice', 'support_ticket'] as $table) {
        $connection->executeStatement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, approved INTEGER DEFAULT 0, status TEXT, base_income REAL DEFAULT 0, base_refund REAL DEFAULT 0)");
    }

    $today = new DateTimeImmutable('today');
    $rows = [
        // table, created_at, [paid_at, approved, status, base_income, base_refund]
        // 3 months back is never inside "last month"'s single-month window, on any run date.
        ['client', $today->modify('-3 months'), []],
        // Exactly a day before midnight today - unconditionally "yesterday", on any run date.
        ['client', $today->modify('-1 day 12:00:00'), []],
        ['client', $today->modify('10:00:00'), []],        // today
        ['client', $today->modify('23:59:59'), []],        // today, end of day
        ['invoice', $today->modify('10:00:00'), ['paid_at' => $today->modify('10:00:00'), 'approved' => 1, 'status' => 'paid', 'base_income' => 100.0]],
    ];

    foreach ($rows as [$table, $createdAt, $extra]) {
        $connection->insert($table, [
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'paid_at' => isset($extra['paid_at']) ? $extra['paid_at']->format('Y-m-d H:i:s') : null,
            'approved' => $extra['approved'] ?? 0,
            'status' => $extra['status'] ?? null,
            'base_income' => $extra['base_income'] ?? 0,
            'base_refund' => $extra['base_refund'] ?? 0,
        ]);
    }

    return $connection;
}

test('getSummary counts client rows into the correct day/month buckets on SQLite', function (): void {
    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['dbal'] = statsConnectionWithRows();
    $service->setDi($di);

    $result = $service->getSummary();

    // "This month" always includes both today rows; it also includes the yesterday row unless
    // the test happens to run on the 1st of the month, when yesterday falls into the prior month.
    $today = new DateTimeImmutable('today');
    $yesterdayIsSameMonth = $today->modify('-1 day')->format('Y-m') === $today->format('Y-m');
    $expectedThisMonth = 2 + ($yesterdayIsSameMonth ? 1 : 0);

    expect($result['clients_total'])->toBe(4)
        ->and($result['clients_today'])->toBe(2)
        ->and($result['clients_yesterday'])->toBe(1)
        ->and($result['clients_this_month'])->toBe($expectedThisMonth)
        ->and($result['clients_last_month'])->toBe(0);
});

test('getSummaryIncome sums paid invoices into the correct day bucket on SQLite', function (): void {
    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['dbal'] = statsConnectionWithRows();
    $service->setDi($di);

    $result = $service->getSummaryIncome();

    // SQLite's COALESCE(SUM(...), 0) returns an integer 0 (not 0.0) when no rows match, since
    // there's no aggregated float value to infer a type from - a driver quirk, not a bug.
    expect($result['total'])->toBe(100.0)
        ->and($result['today'])->toBe(100.0)
        ->and((float) $result['yesterday'])->toBe(0.0);
});

test('gets dependency injection container', function (): void {
    $service = new Box\Mod\Stats\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('gets order statuses', function (): void {
    $service = new Box\Mod\Stats\Service();
    $orderServiceMock = Mockery::mock(Box\Mod\Order\Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $orderServiceMock->shouldReceive('counter');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $di = container();
    $di['mod_service'] = $di->protect(fn (): Mockery\MockInterface => $orderServiceMock);

    $service->setDi($di);

    $result = $service->getOrdersStatuses([]);
    expect($result)->toBeArray();
});

test('gets product summary', function (): void {
    $service = new Box\Mod\Stats\Service();
    $data = [];

    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllAssociative');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchOne');
    $expectation1->atLeast()->once();
    $expectation1->andReturn(null);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $res = ['testProduct' => 1];
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($res);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $res = [
        [
            'refund' => 0,
            'income' => 0,
        ],
    ];
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllAssociative');
    $expectation1->atLeast()->once();
    $expectation1->andReturn($res);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    /** @var Mockery\Expectation $expectation2 */
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
