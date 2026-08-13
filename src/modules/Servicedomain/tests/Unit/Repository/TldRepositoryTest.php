<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\TldRegistrarRepository;
use Box\Mod\Servicedomain\Repository\TldRepository;
use Doctrine\ORM\EntityManagerInterface;

test('builds the legacy domain pricing array keyed by tld', function (): void {
    $com = (new Tld())
        ->setTld('.com')
        ->setTldRegistrarId(1)
        ->setPriceRegistration('10.00')
        ->setPriceRenew('12.00')
        ->setPriceTransfer('14.00')
        ->setActive(true)
        ->setAllowRegister(true)
        ->setAllowTransfer(true)
        ->setMinYears(1)
        ->setPeriods('1,2,5');

    $org = (new Tld())
        ->setTld('.org')
        ->setPriceRegistration('9.00')
        ->setPriceRenew('11.00')
        ->setPriceTransfer('13.00')
        ->setActive(true)
        ->setAllowRegister(false)
        ->setAllowTransfer(null)
        ->setMinYears(2);

    $registrar = (new TldRegistrar())->setName('Registrar A');
    $reflection = new ReflectionProperty($registrar, 'id');
    $reflection->setValue($registrar, 1);

    $registrarRepository = Mockery::mock(TldRegistrarRepository::class);
    $registrarRepository->shouldReceive('findAll')->once()->andReturn([$registrar]);

    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $entityManager->shouldReceive('getRepository')->once()->with(TldRegistrar::class)->andReturn($registrarRepository);

    $repository = Mockery::mock(TldRepository::class)->makePartial();
    $repository->shouldAllowMockingProtectedMethods();
    $repository->shouldReceive('findAllActive')->once()->andReturn([$com, $org]);
    $repository->shouldReceive('getEntityManager')->once()->andReturn($entityManager);

    $result = $repository->getActivePricing();

    expect($result)->toHaveKey('.com')
        ->and($result)->toHaveKey('.org')
        ->and($result['.com']['price_registration'])->toBe('10.00')
        ->and($result['.com']['active'])->toBe(1)
        ->and($result['.com']['allow_register'])->toBe(1)
        ->and($result['.com']['allow_transfer'])->toBe(1)
        ->and($result['.com']['min_years'])->toBe(1)
        ->and($result['.com']['periods'])->toBe([1, 2, 5])
        ->and($result['.com']['registrar'])->toBe(['id' => 1, 'title' => 'Registrar A'])
        ->and($result['.org']['periods'])->toBeNull()
        ->and($result['.org']['allow_register'])->toBe(0)
        ->and($result['.org']['allow_transfer'])->toBeNull()
        ->and($result['.org']['registrar'])->toBe(['id' => null, 'title' => null]);
});
