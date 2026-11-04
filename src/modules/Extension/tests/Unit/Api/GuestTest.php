<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Extension\Service;
use FOSSBilling\Core\Enums\ExtensionType;

test('is_on accepts the module key', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Guest());
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('isExtensionActive')
        ->once()
        ->with(ExtensionType::Module->value, 'cookieconsent')
        ->andReturnTrue();
    $api->setService($service);

    expect($api->is_on(['module' => 'cookieconsent']))->toBeTrue();
});

test('is_on accepts the legacy mod key', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Guest());
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('isExtensionActive')
        ->once()
        ->with(ExtensionType::Module->value, 'cookieconsent')
        ->andReturnTrue();
    $api->setService($service);

    expect($api->is_on(['mod' => 'cookieconsent']))->toBeTrue();
});

test('is_on passes id and type through', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Guest());
    $service = Mockery::mock(Service::class);
    $service->shouldReceive('isExtensionActive')
        ->once()
        ->with('theme', 'huraga')
        ->andReturnFalse();
    $api->setService($service);

    expect($api->is_on(['id' => 'huraga', 'type' => 'theme']))->toBeFalse();
});

test('is_on returns true when no extension is identified', function (): void {
    $api = apiEndpoint(new Box\Mod\Extension\Api\Guest());
    $service = Mockery::mock(Service::class);
    $service->shouldNotReceive('isExtensionActive');
    $api->setService($service);

    expect($api->is_on([]))->toBeTrue();
});
