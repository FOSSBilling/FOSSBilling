<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

declare(strict_types=1);

use Box\Mod\Index\Service as IndexService;
use Box\Mod\Order\Service as OrderService;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;

test('dashboard service actions match the review list and exclude other clients, addons and settled renewals', function (): void {
    $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $db->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, client_id BIGINT, group_master INTEGER, status TEXT, invoice_option TEXT, period TEXT, expires_at TEXT, unpaid_invoice_id BIGINT)');
    $db->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, status TEXT, client_id BIGINT, issued BOOLEAN, hash TEXT)');
    $db->executeStatement('CREATE TABLE client_order_meta (client_order_id BIGINT, name TEXT, value TEXT)');
    $db->executeStatement('CREATE TABLE invoice_item (invoice_id BIGINT, rel_id BIGINT, type TEXT, task TEXT, status TEXT)');
    $soon = (new DateTimeImmutable('+5 days'))->format('Y-m-d H:i:s');
    $base = ['client_id' => 1, 'group_master' => 1, 'status' => 'active', 'invoice_option' => 'issue-invoice', 'period' => '1M', 'expires_at' => $soon, 'unpaid_invoice_id' => null];
    foreach ([
        1 => [], // A renewal the client can review.
        2 => ['status' => 'failed_renew'],
        3 => ['client_id' => 2, 'status' => 'suspended'],
        4 => ['group_master' => 0, 'status' => 'failed_setup'],
        5 => ['status' => 'canceled'],
        6 => ['status' => 'pending_setup'],
        7 => ['expires_at' => (new DateTimeImmutable('+90 days'))->format('Y-m-d H:i:s')],
        8 => ['unpaid_invoice_id' => 1],
        9 => [], // Cancellation already requested.
        10 => [], // Renewal already paid and queued.
        11 => ['status' => 'pending_setup', 'unpaid_invoice_id' => 3], // Another client's invoice.
        12 => ['status' => 'pending_setup', 'unpaid_invoice_id' => 4], // Unissued invoice.
        13 => ['status' => 'canceled', 'unpaid_invoice_id' => 1],
    ] as $id => $changes) {
        $db->insert('client_order', array_replace($base, ['id' => $id], $changes));
    }
    $db->insert('invoice', ['id' => 1, 'status' => 'unpaid', 'client_id' => 1, 'issued' => 1, 'hash' => 'unpaidhash']);
    $db->insert('invoice', ['id' => 2, 'status' => 'paid']);
    $db->insert('invoice', ['id' => 3, 'status' => 'unpaid', 'client_id' => 2, 'issued' => 1, 'hash' => 'otherclient']);
    $db->insert('invoice', ['id' => 4, 'status' => 'unpaid', 'client_id' => 1, 'issued' => 0, 'hash' => 'draft']);
    $db->insert('client_order_meta', ['client_order_id' => 9, 'name' => OrderService::META_CANCEL_AT_PERIOD_END, 'value' => '1']);
    $db->insert('invoice_item', ['invoice_id' => 2, 'rel_id' => 10, 'type' => 'order', 'task' => 'renew', 'status' => 'pending']);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getConnection')->andReturn($db);
    $orderService = new OrderService();
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')->with('invoice_issue_days_before_expire', 14)->andReturn(14);
    $di = new Pimple\Container(['em' => $em]);
    $di['mod_service'] = fn () => static fn ($name) => $name === 'order' ? $orderService : $system;
    $orderService->setDi($di);
    $index = new IndexService();
    $index->setDi($di);

    [$query, $params] = $orderService->getReviewOrdersQuery(1);
    $ids = array_column($db->fetchAllAssociative($query, $params), 'id');
    $counts = (new ReflectionMethod($index, 'getOrdersData'))->invoke($index, ['client_id' => 1]);
    expect($ids)->toBe([8, 2, 1])->and($counts['needs_review'])->toBe(count($ids))->and($counts['expiring'])->toBe(1);

    $actions = $orderService->getClientServiceActions($db->fetchAllAssociative('SELECT * FROM client_order ORDER BY id'), 1);
    $actions = array_column($actions, null, 'id');
    expect($actions[1]['renewal_due'])->toBeTrue()
        ->and($actions[7]['renewal_due'])->toBeFalse()
        ->and($actions[7]['can_renew'])->toBeTrue()
        ->and($actions[8]['unpaid_invoice_hash'])->toBe('unpaidhash')
        ->and($actions[8]['can_renew'])->toBeFalse()
        ->and($actions[9]['can_renew'])->toBeFalse()
        ->and($actions[10]['can_renew'])->toBeFalse()
        ->and($actions[3]['can_renew'])->toBeFalse()
        ->and($actions[11]['unpaid_invoice_hash'])->toBeNull()
        ->and($actions[12]['unpaid_invoice_hash'])->toBeNull()
        ->and($actions[13]['unpaid_invoice_hash'])->toBeNull();

    [$query, $params] = $orderService->getReviewOrdersQuery(99);
    expect($db->fetchAllAssociative($query, $params))->toBe([]);
});
