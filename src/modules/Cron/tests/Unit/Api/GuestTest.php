<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\container;
use Box\Mod\Cron\Api\Guest;

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $api = new Guest();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('settings returns module configuration array', function (): void {
    $modMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
    $modMock->expects($this->atLeastOnce())->method('getConfig')->willReturn([]);

    $api = new Guest();
    $api->setMod($modMock);

    $result = $api->settings();
    expect($result)->toBeArray();
});

test('isLate returns boolean indicating if cron is late', function (): void {
    $serviceMock = $this->createMock(\Box\Mod\Cron\Service::class);
    $serviceMock->expects($this->atLeastOnce())->method('isLate')->willReturn(true);

    $api = new Guest();
    $api->setService($serviceMock);

    $result = $api->is_late();
    expect($result)->toBeBool();
    expect($result)->toBeTrue();
});
