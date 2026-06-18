<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cron\Api\Admin;

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $api_admin = new Admin();
    $api_admin->setDi($di);
    $getDi = $api_admin->getDi();
    expect($getDi)->toEqual($di);
});

test('info returns cron information array', function (): void {
    $serviceMock = Mockery::mock(Box\Mod\Cron\Service::class);
    $serviceMock->shouldReceive('getCronInfo')->atLeast()->once()->andReturn([]);

    $api_admin = new Admin();
    $api_admin->setService($serviceMock);

    $result = $api_admin->info([]);
    expect($result)->toBeArray();
});
