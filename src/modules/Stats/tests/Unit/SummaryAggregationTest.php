<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Stats\Service;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use FOSSBilling\Core\Doctrine\DebugMiddleware;

use function Tests\Helpers\container;

test('five aggregate queries preserve all summary results from the individual queries', function (bool $withRows): void {
    $configuration = new Configuration();
    $configuration->setMiddlewares([new DebugMiddleware()]);
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
    $tables = ['clients' => 'client', 'orders' => 'client_order', 'invoices' => 'invoice', 'tickets' => 'support_ticket'];
    foreach ($tables as $table) {
        $connection->executeStatement("CREATE TABLE {$table} (created_at TEXT, paid_at TEXT, issued INTEGER, status TEXT, base_income REAL, base_refund REAL)");
    }

    $today = new DateTimeImmutable('today');
    $month = $today->modify('first day of this month');
    $dates = [
        null,
        $month->modify('-2 months'),
        $month->modify('-1 month'),
        $month->modify('-1 second'),
        $month,
        $today->modify('-1 day'),
        $today->modify('-1 second'),
        $today,
        $today->modify('+1 day -1 second'),
        $today->modify('+1 day'),
        $month->modify('+1 month'),
    ];
    if ($withRows) {
        foreach ($tables as $table) {
            foreach ($dates as $date) {
                $connection->insert($table, [
                    'created_at' => $date?->format('Y-m-d H:i:s'),
                    'paid_at' => $date?->format('Y-m-d H:i:s'),
                    'issued' => 1,
                    'status' => 'paid',
                    'base_income' => 100,
                    'base_refund' => 5,
                ]);
            }
        }
        // Invoice income uses paid_at, excludes drafts/unpaid invoices, includes refunds,
        // and treats NULL income and refund amounts independently.
        foreach ([
            [1, 'refunded', 100, 150],
            [1, 'paid', null, 7],
            [1, 'paid', 11, null],
            [1, 'paid', null, null],
            [0, 'paid', 1000, 0],
            [1, 'unpaid', 1000, 0],
            [1, 'cancelled', 1000, 0],
        ] as [$issued, $status, $income, $refund]) {
            $connection->insert('invoice', [
                'created_at' => $month->modify('-2 months')->format('Y-m-d H:i:s'),
                'paid_at' => $today->format('Y-m-d H:i:s'),
                'issued' => $issued, 'status' => $status, 'base_income' => $income, 'base_refund' => $refund,
            ]);
        }
    }

    // Use the previous individual queries as a regression oracle, including exact
    // midnight/month boundaries, future and NULL dates, and native scalar types.
    $ranges = [
        'today' => [$today, $today->modify('+1 day')],
        'yesterday' => [$today->modify('-1 day'), $today],
        'this_month' => [$month, $month->modify('+1 month')],
        'last_month' => [$month->modify('-1 month'), $month],
    ];
    $expected = [];
    foreach ($tables as $prefix => $table) {
        $expected["{$prefix}_total"] = $connection->fetchOne("SELECT COUNT(1) FROM {$table}");
        foreach ($ranges as $period => [$start, $end]) {
            $expected["{$prefix}_{$period}"] = $connection->fetchOne(
                "SELECT COUNT(1) FROM {$table} WHERE created_at >= ? AND created_at < ?",
                [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')],
            );
        }
    }
    $incomeSql = "SELECT COALESCE(SUM(base_income), 0) - COALESCE(SUM(base_refund), 0) FROM invoice WHERE issued = true AND (status = 'paid' OR status = 'refunded')";
    $expectedIncome = ['total' => $connection->fetchOne($incomeSql)];
    foreach ($ranges as $period => [$start, $end]) {
        $expectedIncome[$period] = $connection->fetchOne($incomeSql . ' AND paid_at >= ? AND paid_at < ?', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
    }

    $pdo = $connection->getNativeConnection();
    $pdo->resetExecutedStatements();
    $di = container();
    $di['dbal'] = $connection;
    $service = new Service();
    $service->setDi($di);

    expect($service->getSummary())->toBe($expected)
        ->and($pdo->getExecutedStatements())->toHaveCount(4)
        ->and($service->getSummaryIncome())->toBe($expectedIncome)
        ->and($pdo->getExecutedStatements())->toHaveCount(5);
})->with(['empty database' => false, 'boundary and refund fixtures' => true]);
