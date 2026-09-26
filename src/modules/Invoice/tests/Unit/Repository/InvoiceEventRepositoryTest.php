<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\InvoiceEvent;
use Box\Mod\Invoice\Repository\InvoiceEventRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;

test('finds journal entries for an invoice oldest first', function (): void {
    $first = new InvoiceEvent();
    $second = new InvoiceEvent();

    $entityManager = Mockery::mock(EntityManager::class);
    $repository = Mockery::mock(InvoiceEventRepository::class . '[findBy]', [$entityManager, new ClassMetadata(InvoiceEvent::class)]);
    $repository->shouldReceive('findBy')
        ->once()
        ->with(['invoiceId' => 7], ['id' => 'ASC'])
        ->andReturn([$first, $second]);

    expect($repository->findByInvoiceId(7))->toBe([$first, $second]);
});

test('repository is wired to the InvoiceEvent entity', function (): void {
    $entityManager = Mockery::mock(EntityManager::class);
    $repository = new InvoiceEventRepository($entityManager, new ClassMetadata(InvoiceEvent::class));

    expect($repository->getClassName())->toBe(InvoiceEvent::class);
});
