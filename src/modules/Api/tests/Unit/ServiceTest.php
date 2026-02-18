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

test('getDi returns dependency injection container', function (): void {
    $service = new Box\Mod\Api\Service();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('getRequestCount returns integer', function (): void {
    $service = new Box\Mod\Api\Service();
    $since = 674_690_401; // timestamp == '1991-05-20 00:00:01';
    $ip = '1.2.3.4';

    $requestNumber = 11;

    $dbMock = Mockery::mock('\Box_Database');
    /** @var Mockery\Expectation $expectation */
    $expectation = $dbMock->shouldReceive('getCell');
    $expectation->atLeast()->once();
    $expectation->andReturn($requestNumber);

    $di = container();
    $di['db'] = $dbMock;

    $service->setDi($di);
    $result = $service->getRequestCount($since, $ip);

    expect($result)->toBeInt();
    expect($result)->toEqual($requestNumber);
});
