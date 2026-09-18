<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Product\Entity\PromoRedemption;

test('locking checkout-application check requires a transaction', function (): void {
    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturn(false);
    $emMock->shouldReceive('getConnection')->andReturn($connection);

    $repository = new Box\Mod\Product\Repository\PromoRedemptionRepository(
        $emMock,
        new Doctrine\ORM\Mapping\ClassMetadata(PromoRedemption::class)
    );

    expect(fn () => $repository->clientHasActiveCheckoutApplicationForUpdate(5, 1))
        ->toThrow(FOSSBilling\Exception::class);
});

test('locking checkout-application check mutexes the client row before reading', function (): void {
    // The COUNT alone cannot serialize insert-only rows; the client-row lock is what makes
    // concurrent checkouts wait for each other. Pin the order: mutex first, read second.
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturn(true);
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with('SELECT id FROM client WHERE id = :client_id FOR UPDATE', ['client_id' => 1])
        ->andReturn(1);
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with(
            'SELECT COUNT(pr.id) FROM promo_redemption pr WHERE pr.promo_id = :promo_id AND pr.client_id = :client_id AND pr.phase = :phase AND pr.status IN (:statuses) FOR UPDATE',
            [
                'promo_id' => 5,
                'client_id' => 1,
                'phase' => PromoRedemption::PHASE_CHECKOUT,
                'statuses' => [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED],
            ],
            ['statuses' => Doctrine\DBAL\ArrayParameterType::STRING]
        )
        ->andReturn(0);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connection);

    $repository = new Box\Mod\Product\Repository\PromoRedemptionRepository(
        $emMock,
        new Doctrine\ORM\Mapping\ClassMetadata(PromoRedemption::class)
    );

    expect($repository->clientHasActiveCheckoutApplicationForUpdate(5, 1))->toBeFalse();
});
