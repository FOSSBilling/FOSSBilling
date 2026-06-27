<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Page\Api\Admin;

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $api = new Admin();
    $di = container();
    $api->setDi($di);
    $getDi = $api->getDi();
    expect($getDi)->toEqual($di);
});

test('getPairs returns page pairs array', function (): void {
    $api = new Admin();
    $serviceMock = Mockery::mock(Box\Mod\Page\Service::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $serviceMock->shouldReceive('getPairs');
    $expectation->atLeast()->once();
    $expectation->andReturn([]);

    $api->setService($serviceMock);
    $result = $api->get_pairs();
    expect($result)->toBeArray();
});
