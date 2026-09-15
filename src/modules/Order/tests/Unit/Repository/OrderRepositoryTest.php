<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;

function normalizeOrderRepositorySql(string $sql): string
{
    return preg_replace('/\s+/', ' ', trim($sql));
}

function mockOrderRepositoryConnection(): Mockery\MockInterface
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(MySQLPlatform::class));

    return $connection;
}

test('expired orders use the order override before the product grace period', function (): void {
    $connection = mockOrderRepositoryConnection();
    $connection->shouldReceive('fetchFirstColumn')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = normalizeOrderRepositorySql($sql);

            return $parameters['status'] === Order::STATUS_ACTIVE
                && isset($parameters['now'])
                && str_contains($sql, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)')
                && str_contains($sql, 'GREATEST(COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0), 0)')
                && str_contains($sql, 'DATE_ADD(o.expires_at, INTERVAL (')
                && str_contains($sql, ') <= :now');
        })
        ->andReturn([]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = new OrderRepository($entityManager, new ClassMetadata(Order::class));

    expect($repository->getExpired())->toBe([]);
});

test('suspension warnings use positive grace periods in the final 24 hour window', function (): void {
    $connection = mockOrderRepositoryConnection();
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = normalizeOrderRepositorySql($sql);

            return $parameters['status'] === Order::STATUS_ACTIVE
                && isset($parameters['now'], $parameters['tomorrow'])
                && $parameters['tomorrow'] > $parameters['now']
                && str_contains($sql, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)')
                && str_contains($sql, 'due.grace_days > 0')
                && str_contains($sql, 'due.suspension_at > :now')
                && str_contains($sql, 'due.suspension_at <= :tomorrow');
        })
        ->andReturn([
            ['id' => '8', 'suspension_at' => '2026-08-01 12:00:00'],
        ]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = new OrderRepository($entityManager, new ClassMetadata(Order::class));

    expect($repository->getDueSuspensionWarnings())->toBe([
        ['id' => 8, 'suspension_at' => '2026-08-01 12:00:00'],
    ]);
});

test('stale unpaid orders are matched by pending-setup status, unpaid invoice overdue days, or an orphaned order past its own age', function (): void {
    $connection = mockOrderRepositoryConnection();
    $connection->shouldReceive('fetchFirstColumn')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = normalizeOrderRepositorySql($sql);
            $subqueryCount = substr_count($sql, 'FROM invoice i');

            return $parameters['status'] === Order::STATUS_PENDING_SETUP
                && $parameters['item_type'] === InvoiceItem::TYPE_ORDER
                && $parameters['paid_status'] === Invoice::STATUS_PAID
                && $parameters['unpaid_status'] === Invoice::STATUS_UNPAID
                // 5 days ago at midnight, the cutoff getStaleUnpaid(5) should compute.
                && $parameters['cutoff'] === (new DateTimeImmutable('today'))->modify('-5 days')->format('Y-m-d H:i:s')
                // Orders any paid invoice ever covered are never touched.
                && str_contains($sql, 'NOT EXISTS')
                && str_contains($sql, 'ii.rel_id = o.id AND ii.type = :item_type AND pi.status = :paid_status')
                // Still linked to a live, overdue unpaid invoice - falling back to the
                // order's own creation date if that invoice has no due date set.
                && str_contains($sql, 'i.id = o.unpaid_invoice_id')
                && str_contains($sql, 'i.status = :unpaid_status')
                && str_contains($sql, 'COALESCE(i.due_at, o.created_at) < :cutoff')
                // Or it no longer has one (removed, canceled, refunded, or never linked),
                // judged instead by the order's own age. Checked in a second subquery
                // against unpaid_invoice_id, distinct from the overdue-invoice one above.
                && $subqueryCount === 2
                && str_contains($sql, 'o.created_at < :cutoff');
        })
        ->andReturn([]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = new OrderRepository($entityManager, new ClassMetadata(Order::class));

    expect($repository->getStaleUnpaid(5))->toBe([]);
});
