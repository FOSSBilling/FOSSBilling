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
use Box\Mod\Servicedomain\Repository\TldRepository;

test('builds the legacy domain pricing array keyed by tld', function (): void {
    $registrar = (new TldRegistrar())->setName('Registrar A');
    $reflection = new ReflectionProperty($registrar, 'id');
    $reflection->setValue($registrar, 1);

    $com = (new Tld())
        ->setTld('.com')
        ->setRegistrar($registrar)
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

    $repository = Mockery::mock(TldRepository::class)->makePartial();
    $repository->shouldReceive('findAllActive')->once()->andReturn([$com, $org]);

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
