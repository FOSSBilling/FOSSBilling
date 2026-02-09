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
use Box\Mod\Page\Api\Admin;

beforeEach(function (): void {
    $this->api = new Admin();
});

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $this->api->setDi($di);
    $getDi = $this->api->getDi();
    expect($getDi)->toEqual($di);
});

test('getPairs returns page pairs array', function (): void {
    $serviceMock = Mockery::mock(\Box\Mod\Page\Service::class);

    $serviceMock->shouldReceive('getPairs')
        ->atLeast()
        ->once()
        ->andReturn([]);

    $this->api->setService($serviceMock);
    $result = $this->api->get_pairs();
    expect($result)->toBeArray();
});
