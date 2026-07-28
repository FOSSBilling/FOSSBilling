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

function normalizeOrderRepositorySql(string $sql): string
{
    return preg_replace('/\s+/', ' ', trim($sql));
}

test('expired orders use the order override before the product grace period', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchFirstColumn')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = normalizeOrderRepositorySql($sql);

            return $parameters === ['status' => Order::STATUS_ACTIVE]
                && str_contains($sql, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)')
                && str_contains($sql, 'GREATEST(COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0), 0)')
                && str_contains($sql, ') <= NOW()');
        })
        ->andReturn([]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getConnection')->once()->andReturn($connection);

    $repository = new OrderRepository($entityManager, new ClassMetadata(Order::class));

    expect($repository->getExpired())->toBe([]);
});

test('suspension warnings use positive grace periods in the final 24 hour window', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->once()
        ->withArgs(function (string $sql, array $parameters): bool {
            $sql = normalizeOrderRepositorySql($sql);

            return $parameters === ['status' => Order::STATUS_ACTIVE]
                && str_contains($sql, 'COALESCE(o.suspension_grace_days, p.suspension_grace_days, 0)')
                && str_contains($sql, 'due.grace_days > 0')
                && str_contains($sql, 'due.suspension_at > NOW()')
                && str_contains($sql, 'due.suspension_at <= DATE_ADD(NOW(), INTERVAL 1 DAY)');
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
