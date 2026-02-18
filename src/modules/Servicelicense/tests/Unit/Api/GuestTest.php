<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Servicelicense\Api\Guest;

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $api = new Guest();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('check returns license details array', function (): void {
    $api = new Guest();
    $data = [
        'license' => 'license1234',
        'host' => 'fossbilling.org',
        'version' => 1,
    ];

    $licenseResult = [
        'licensed_to' => 'fossbilling.org',
        'created_at' => '2011-12-31',
        'expires_at' => '2020-01+01',
        'valid' => true,
    ];
    $serviceMock = Mockery::mock(Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('checkLicenseDetails')
        ->atLeast()
        ->once()
        ->andReturn($licenseResult);

    $api->setService($serviceMock);
    $result = $api->check($data);

    expect($result)->toBeArray();
});
