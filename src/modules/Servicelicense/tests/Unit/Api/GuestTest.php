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
use Box\Mod\Servicelicense\Api\Guest;

beforeEach(function (): void {
    $this->api = new Guest();
});

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toEqual($di);
});

test('check returns license details array', function (): void {
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
    $serviceMock = Mockery::mock(\Box\Mod\Servicelicense\Service::class);
    $serviceMock->shouldReceive('checkLicenseDetails')
        ->atLeast()
        ->once()
        ->andReturn($licenseResult);

    $this->api->setService($serviceMock);
    $result = $this->api->check($data);

    expect($result)->toBeArray();
});
