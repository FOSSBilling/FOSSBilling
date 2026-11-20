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
use Doctrine\DBAL\Platforms\MySQLPlatform;

use function Tests\Helpers\container;

/**
 * A real SQLite connection (not a mock) with minimal `client`/`client_order`/`invoice`/
 * `invoice_item`/`support_ticket` tables - just enough columns for getSummary()/getSummaryIncome()
 * to run their real SQL against. This is the regression test for the DATE_FORMAT()/CURDATE()/DATE_SUB()
 * portability fix: those MySQL-only functions would raise a syntax error on SQLite, so a plain
 * pass here already proves the rewritten day/month boundary queries are portable syntax. It also
 * pins the actual day-boundary math against real inserted rows.
 */
function statsConnectionWithRows(): Doctrine\DBAL\Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

    foreach (['client', 'client_order', 'support_ticket'] as $table) {
        $connection->executeStatement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, issued INTEGER DEFAULT 0, status TEXT, base_income REAL DEFAULT 0, base_refund REAL DEFAULT 0)");
    }
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, issued INTEGER DEFAULT 0, status TEXT, base_income REAL DEFAULT 0, base_refund REAL DEFAULT 0, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');

    $today = new DateTimeImmutable('today');
    $rows = [
        // table, created_at, [paid_at, issued, status, base_income, base_refund]
        // 3 months back is never inside "last month"'s single-month window, on any run date.
        ['client', $today->modify('-3 months'), []],
        // Exactly a day before midnight today - unconditionally "yesterday", on any run date.
        ['client', $today->modify('-1 day 12:00:00'), []],
        ['client', $today->modify('10:00:00'), []],        // today
        ['client', $today->modify('23:59:59'), []],        // today, end of day
        ['invoice', $today->modify('10:00:00'), ['paid_at' => $today->modify('10:00:00'), 'issued' => 1, 'status' => 'paid', 'base_income' => 100.0]],
    ];

    foreach ($rows as [$table, $createdAt, $extra]) {
        $connection->insert($table, [
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'paid_at' => isset($extra['paid_at']) ? $extra['paid_at']->format('Y-m-d H:i:s') : null,
            'issued' => $extra['issued'] ?? 0,
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
    $di['em']->shouldReceive('getConnection')->andReturn(statsConnectionWithRows());
    $service->setDi($di);

    $result = $service->getSummary();

    // "This month" always includes both today rows; it also includes the yesterday row unless
    // the test happens to run on the 1st of the month, when yesterday falls into the prior month.
    $today = new DateTimeImmutable('today');
    $yesterdayIsSameMonth = $today->modify('-1 day')->format('Y-m') === $today->format('Y-m');
    $expectedThisMonth = 2 + ($yesterdayIsSameMonth ? 1 : 0);
    $expectedLastMonth = $yesterdayIsSameMonth ? 0 : 1;

    expect($result['clients_total'])->toBe(4)
        ->and($result['clients_today'])->toBe(2)
        ->and($result['clients_yesterday'])->toBe(1)
        ->and($result['clients_this_month'])->toBe($expectedThisMonth)
        ->and($result['clients_last_month'])->toBe($expectedLastMonth);
});

test('getSummaryIncome sums paid invoices into the correct day bucket on SQLite', function (): void {
    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn(statsConnectionWithRows());
    $service->setDi($di);

    $result = $service->getSummaryIncome();

    // SQLite's COALESCE(SUM(...), 0) returns an integer 0 (not 0.0) when no rows match, since
    // there's no aggregated float value to infer a type from - a driver quirk, not a bug.
    expect($result['total'])->toBe(100.0)
        ->and($result['today'])->toBe(100.0)
        ->and((float) $result['yesterday'])->toBe(0.0);
});

test('income stats split mixed invoices and exclude pure deposits', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, issued INTEGER DEFAULT 0, status TEXT, base_income REAL, base_refund REAL, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');

    $today = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');

    // Pure 25 top-up: not revenue.
    $connection->insert('invoice', ['id' => 1, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 25.0, 'base_refund' => 0, 'currency_rate' => 1]);
    // Mixed 25 deposit + 25 service: only the service portion counts.
    $connection->insert('invoice', ['id' => 2, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 50.0, 'base_refund' => 0, 'currency_rate' => 1]);
    // Ordinary 30 service invoice.
    $connection->insert('invoice', ['id' => 3, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 30.0, 'base_refund' => 0, 'currency_rate' => 1]);
    // Mixed invoice in a foreign currency at rate 2: 10 deposit + 50 service.
    // Production base amounts divide by the rate, so the stored 30 backs out
    // to a 25 service share.
    $connection->insert('invoice', ['id' => 4, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 30.0, 'base_refund' => 0, 'currency_rate' => 2]);
    $connection->insert('invoice_item', ['id' => 1, 'invoice_id' => 1, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 2, 'invoice_id' => 2, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 3, 'invoice_id' => 2, 'type' => 'order', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 4, 'invoice_id' => 3, 'type' => 'order', 'price' => 30, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 5, 'invoice_id' => 4, 'type' => 'deposit', 'price' => 10, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 6, 'invoice_id' => 4, 'type' => 'order', 'price' => 50, 'quantity' => 1]);

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    // 0 + 25 + 30 + (30 - 10 / 2).
    $summary = $service->getSummaryIncome();
    expect((float) $summary['total'])->toBe(80.0);

    $totals = $service->incomeAndRefundStats([]);
    expect((float) $totals['income'])->toBe(80.0);

    $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $incomeByDay = $service->getIncome(['date_from' => $yesterday, 'date_to' => $tomorrow]);
    $dayTotal = array_sum(array_column($incomeByDay, 1));
    expect($dayTotal)->toBe(80.0);
});

test('refund stats split mixed invoices and exclude pure deposits', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, issued INTEGER DEFAULT 0, status TEXT, base_income REAL, base_refund REAL, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');

    $today = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');

    // Pure deposit top-up, later refunded: neither side counts.
    $connection->insert('invoice', ['id' => 10, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'base_income' => 25.0, 'base_refund' => 25.0, 'base_refund_tax' => 0, 'refund' => 25.0, 'currency_rate' => 1]);
    // Mixed invoice, fully refunded: 25 service in, 25 service out.
    $connection->insert('invoice', ['id' => 11, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'base_income' => 50.0, 'base_refund' => 50.0, 'base_refund_tax' => 0, 'refund' => 50.0, 'currency_rate' => 1]);
    // The refund paper trail mirrors the refunded lines' types with negative prices.
    $connection->insert('invoice', ['id' => 12, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'credit_note_for_invoice_id' => 11, 'currency_rate' => 1]);
    // Ordinary service invoice, partly refunded.
    $connection->insert('invoice', ['id' => 13, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 40.0, 'base_refund' => 10.0, 'base_refund_tax' => 0, 'refund' => 10.0, 'currency_rate' => 1]);
    // Pure deposit refunded through a synthetic custom line: still nothing.
    $connection->insert('invoice', ['id' => 14, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'base_income' => 25.0, 'base_refund' => 25.0, 'base_refund_tax' => 0, 'refund' => 25.0, 'currency_rate' => 1]);
    $connection->insert('invoice', ['id' => 15, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'credit_note_for_invoice_id' => 14, 'currency_rate' => 1]);
    // Mixed invoice with an untyped service line, fully refunded with mirrored lines.
    $connection->insert('invoice', ['id' => 16, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'base_income' => 50.0, 'base_refund' => 50.0, 'base_refund_tax' => 0, 'refund' => 50.0, 'currency_rate' => 1]);
    $connection->insert('invoice', ['id' => 17, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'credit_note_for_invoice_id' => 16, 'currency_rate' => 1]);
    $connection->insert('invoice_item', ['id' => 10, 'invoice_id' => 10, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 11, 'invoice_id' => 11, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 12, 'invoice_id' => 11, 'type' => 'order', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 13, 'invoice_id' => 12, 'type' => 'deposit', 'price' => -25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 14, 'invoice_id' => 12, 'type' => 'order', 'price' => -25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 15, 'invoice_id' => 13, 'type' => 'order', 'price' => 40, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 16, 'invoice_id' => 14, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 17, 'invoice_id' => 15, 'type' => 'custom', 'price' => -25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 18, 'invoice_id' => 16, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 19, 'invoice_id' => 16, 'type' => null, 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 20, 'invoice_id' => 17, 'type' => 'deposit', 'price' => -25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 21, 'invoice_id' => 17, 'type' => null, 'price' => -25, 'quantity' => 1]);

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    // Income 0 + 25 + 40 + 0 + 25, refunds 0 + 25 + 10 + 0 + 25.
    $summary = $service->getSummaryIncome();
    expect((float) $summary['total'])->toBe(30.0);

    $totals = $service->incomeAndRefundStats([]);
    expect((float) $totals['income'])->toBe(90.0)
        ->and((float) $totals['refund'])->toBe(60.0);

    $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $refundsByDay = $service->getRefunds(['date_from' => $yesterday, 'date_to' => $tomorrow]);
    expect(array_sum(array_column($refundsByDay, 1)))->toBe(60.0);
});

test('legacy refunds without credit notes split when fully backed, else unresolved', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, created_at TEXT, paid_at TEXT, issued INTEGER DEFAULT 0, status TEXT, base_income REAL, base_refund REAL, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');

    $today = (new DateTimeImmutable('today'))->format('Y-m-d H:i:s');

    // Mixed invoice with a legacy full refund and no credit-note paper trail:
    // the deposit share is reconstructed from the original lines. Legacy rows
    // carry a populated refund with unknown tax, so they stay out of the
    // classified refund totals and count as unresolved instead.
    $connection->insert('invoice', ['id' => 20, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'refunded', 'base_income' => 50.0, 'base_refund' => 50.0, 'refund' => 50.0, 'currency_rate' => 1]);
    // Mixed invoice with a legacy partial refund: unattributable, stays out.
    $connection->insert('invoice', ['id' => 21, 'created_at' => $today, 'paid_at' => $today, 'issued' => 1, 'status' => 'paid', 'base_income' => 50.0, 'base_refund' => 10.0, 'refund' => 10.0, 'currency_rate' => 1]);
    $connection->insert('invoice_item', ['id' => 20, 'invoice_id' => 20, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 21, 'invoice_id' => 20, 'type' => 'order', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 22, 'invoice_id' => 21, 'type' => 'deposit', 'price' => 25, 'quantity' => 1]);
    $connection->insert('invoice_item', ['id' => 23, 'invoice_id' => 21, 'type' => 'order', 'price' => 25, 'quantity' => 1]);

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    // Income 25 + 25, classified refunds 0 (legacy rows stay unresolved),
    // while the summary still nets the reconstructed full refund.
    $summary = $service->getSummaryIncome();
    expect((float) $summary['total'])->toBe(25.0);

    $totals = $service->incomeAndRefundStats([]);
    expect((float) $totals['income'])->toBe(50.0)
        ->and((float) $totals['refund'])->toBe(0.0)
        ->and((int) $totals['refund_tax_unresolved'])->toBe(2);
});

test('sales by country excludes only pure deposit invoices', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, buyer_country TEXT, status TEXT)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT)');

    // A pure deposit top-up is no sale, but a mixed invoice holds a real sale.
    $connection->insert('invoice', ['id' => 1, 'buyer_country' => 'PL', 'status' => 'paid']);
    $connection->insert('invoice', ['id' => 2, 'buyer_country' => 'PL', 'status' => 'paid']);
    $connection->insert('invoice', ['id' => 3, 'buyer_country' => 'PL', 'status' => 'paid']);
    $connection->insert('invoice_item', ['id' => 1, 'invoice_id' => 1, 'type' => 'deposit']);
    $connection->insert('invoice_item', ['id' => 2, 'invoice_id' => 2, 'type' => 'deposit']);
    $connection->insert('invoice_item', ['id' => 3, 'invoice_id' => 2, 'type' => 'order']);
    $connection->insert('invoice_item', ['id' => 4, 'invoice_id' => 3, 'type' => 'order']);

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    expect($service->getSalesByCountry([]))->toBe(['PL' => 2]);
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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);
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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);
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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);
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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);
    $service->setDi($di);

    $result = $service->incomeAndRefundStats([]);
    expect($result)->toBeArray()
        ->and($result)->toBe($res[0]);
});

test('income and refund stats count unresolved refund tax separately from zero tax', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, issued INTEGER, status TEXT, base_income REAL, base_refund REAL, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');
    $connection->executeStatement("INSERT INTO invoice (issued, status, base_income, base_refund, base_refund_tax, refund) VALUES (1, 'refunded', 100.0, 100.0, 20.0, 120.0)");
    $connection->executeStatement("INSERT INTO invoice (issued, status, base_income, base_refund, base_refund_tax, refund) VALUES (1, 'paid', 50.0, 0.0, 0.0, 0.0)");
    $connection->executeStatement("INSERT INTO invoice (issued, status, base_income, base_refund, base_refund_tax, refund) VALUES (1, 'refunded', 100.0, 120.0, NULL, 120.0)");
    $connection->executeStatement("INSERT INTO invoice (issued, status, base_income, base_refund, base_refund_tax, refund) VALUES (1, 'unpaid', 100.0, NULL, NULL, NULL)");

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->incomeAndRefundStats([]);
    // Unresolved rows stay out of the net/gross sums: only the classified refund
    // contributes, while the count flags the pending legacy row.
    expect((float) $result['refund'])->toBe(100.0)
        ->and((float) $result['income'])->toBe(250.0)
        ->and((float) $result['refund_tax'])->toBe(20.0)
        ->and((float) $result['refund_gross'])->toBe(120.0)
        ->and((int) $result['refund_tax_unresolved'])->toBe(1);
});

test('income and refund stats return zeros, not nulls, with no matching invoices', function (): void {
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, issued INTEGER, status TEXT, base_income REAL, base_refund REAL, base_refund_tax REAL, refund REAL, currency_rate REAL DEFAULT 1, credit_note_for_invoice_id INTEGER)');
    $connection->executeStatement('CREATE TABLE invoice_item (id INTEGER PRIMARY KEY, invoice_id INTEGER, type TEXT, price REAL, quantity INTEGER)');

    $service = new Box\Mod\Stats\Service();
    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->setDi($di);

    $result = $service->incomeAndRefundStats([]);
    expect($result['refund'])->not->toBeNull()
        ->and($result['income'])->not->toBeNull()
        ->and($result['refund_tax'])->not->toBeNull()
        ->and($result['refund_gross'])->not->toBeNull()
        ->and($result['refund_tax_unresolved'])->not->toBeNull()
        ->and((float) $result['refund'])->toBe(0.0)
        ->and((float) $result['income'])->toBe(0.0)
        ->and((float) $result['refund_tax'])->toBe(0.0)
        ->and((float) $result['refund_gross'])->toBe(0.0)
        ->and((int) $result['refund_tax_unresolved'])->toBe(0);
});

test('gets refunds', function (): void {
    $service = new Box\Mod\Stats\Service();
    $resultMock = Mockery::mock(Doctrine\DBAL\Result::class);
    /** @var Mockery\Expectation $expectation1 */
    $expectation1 = $resultMock->shouldReceive('fetchAllKeyValue');
    $expectation1->atLeast()->once();
    $expectation1->andReturn([]);

    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

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
    $dbalMock->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));
    /** @var Mockery\Expectation $expectation2 */
    $expectation2 = $dbalMock->shouldReceive('executeQuery');
    $expectation2->atLeast()->once();
    $expectation2->andReturn($resultMock);

    $di = container();
    $di['em']->shouldReceive('getConnection')->andReturn($dbalMock);

    $service->setDi($di);

    $data = [
        'date_from' => 'yesterday',
        'date_to' => 'now',
    ];
    $result = $service->getTableStats('client', $data);
    expect($result)->toBeArray();
});
