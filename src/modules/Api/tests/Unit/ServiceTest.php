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

beforeEach(function () {
    $this->service = new \Box\Mod\Api\Service();
});

test('getDi returns dependency injection container', function () {
    $di = container();
    $this->service->setDi($di);
    $getDi = $this->service->getDi();
    expect($getDi)->toEqual($di);
});

test('getRequestCount returns integer', function () {
    $since = 674_690_401; // timestamp == '1991-05-20 00:00:01';
    $ip = '1.2.3.4';

    $requestNumber = 11;

    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('getCell')
        ->atLeast()
        ->once()
        ->andReturn($requestNumber);

    $di = container();
    $di['db'] = $dbMock;

    $this->service->setDi($di);
    $result = $this->service->getRequestCount($since, $ip);

    expect($result)->toBeInt();
    expect($result)->toEqual($requestNumber);
});
