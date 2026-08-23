<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

test('stale unpaid orders are matched by pending-setup status, unpaid invoice overdue days, or an orphaned order past its own age', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchFirstColumn')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = preg_replace('/\s+/', ' ', $sql);
            $subqueryCount = substr_count($sql, 'FROM invoice i');

            return $parameters === [
                'status' => Order::STATUS_PENDING_SETUP,
                'item_type' => Model_InvoiceItem::TYPE_ORDER,
                'paid_status' => Model_Invoice::STATUS_PAID,
                'unpaid_status' => Model_Invoice::STATUS_UNPAID,
                'days' => 5,
            ]
                // Orders any paid invoice ever covered are never touched.
                && str_contains($sql, 'NOT EXISTS')
                && str_contains($sql, 'ii.rel_id = o.id AND ii.type = :item_type AND pi.status = :paid_status')
                // Still linked to a live, overdue unpaid invoice - falling back to the
                // order's own creation date if that invoice has no due date set.
                && str_contains($sql, 'i.id = o.unpaid_invoice_id')
                && str_contains($sql, 'i.status = :unpaid_status')
                && str_contains($sql, 'DATEDIFF(NOW(), COALESCE(i.due_at, o.created_at)) > :days')
                // Or it no longer has one (removed, canceled, refunded, or never linked),
                // judged instead by the order's own age. Checked in a second subquery
                // against unpaid_invoice_id, distinct from the overdue-invoice one above.
                && $subqueryCount === 2
                && str_contains($sql, 'DATEDIFF(NOW(), o.created_at) > :days');
        })
        ->andReturn([]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = new OrderRepository($entityManager, new ClassMetadata(Order::class));

    expect($repository->getStaleUnpaid(5))->toBe([]);
});
