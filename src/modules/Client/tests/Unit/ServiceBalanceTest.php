<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

test('getDi returns dependency injection container', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('deductFunds creates balance record', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $di = container();

    $service->setDi($di);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = 'Charged for product';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $result = $service->deductFunds($clientModel, $amount, $description, $extra);

    expect($result)->toBeInstanceOf(Box\Mod\Client\Entity\ClientBalance::class);
    expect($result->getAmount())->toEqual((string) (-$amount));
    expect($result->getDescription())->toEqual($description);
    expect($result->getRelId())->toEqual($extra['rel_id']);
    expect($result->getType())->toEqual('default');
});

test('deductFunds throws exception for invalid description', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = '    ';
    $amount = 5.55;

    $extra = [
        'rel_id' => 1,
    ];

    $service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(FOSSBilling\Exception::class, 'Funds description is invalid');

test('deductFunds throws exception for invalid amount', function (): void {
    $service = new Box\Mod\Client\ServiceBalance();
    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $description = 'Charged';
    $amount = '5.5adadzxc';

    $extra = [
        'rel_id' => 1,
    ];

    $service->deductFunds($clientModel, $amount, $description, $extra);
})->throws(FOSSBilling\Exception::class, 'Funds amount is invalid');
