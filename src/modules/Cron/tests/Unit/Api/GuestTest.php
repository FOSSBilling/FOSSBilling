<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cron\Api\Guest;

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $api = new Guest();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('settings returns module configuration array', function (): void {
    $modMock = Mockery::mock('\\' . FOSSBilling\Module::class);
    $modMock->shouldReceive('getConfig')->atLeast()->once()->andReturn([]);

    $api = new Guest();
    $api->setMod($modMock);

    $result = $api->settings();
    expect($result)->toBeArray();
});

test('isLate returns boolean indicating if cron is late', function (): void {
    $serviceMock = Mockery::mock(Box\Mod\Cron\Service::class);
    $serviceMock->shouldReceive('isLate')->atLeast()->once()->andReturn(true);

    $api = new Guest();
    $api->setService($serviceMock);

    $result = $api->is_late();
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
