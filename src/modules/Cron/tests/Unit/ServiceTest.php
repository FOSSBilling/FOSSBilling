<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Cron\Service;

use function Tests\Helpers\container;

test('getDi returns dependency injection container', function (): void {
    $di = container();
    $service = new Service();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toEqual($di);
});

test('getCronInfo returns cron information array', function (): void {
    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once();

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $systemServiceMock);
    $service = new Service();
    $service->setDi($di);

    $result = $service->getCronInfo();
    expect($result)->toBeArray();
});

test('getLastExecutionTime returns string timestamp', function (): void {
    $systemServiceMock = Mockery::mock(Box\Mod\System\Service::class);
    $systemServiceMock->shouldReceive('getParamValue')
        ->atLeast()->once()
        ->andReturn('2012-12-12 12:12:12');

    $di = container();
    $di['mod_service'] = $di->protect(fn ($name): Mockery\MockInterface => $systemServiceMock);
    $service = new Service();
    $service->setDi($di);

    $result = $service->getLastExecutionTime();
    expect($result)->toBeString();
});

test('isLate returns boolean indicating if cron execution is late', function (): void {
    $serviceMock = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $serviceMock->shouldReceive('getLastExecutionTime')
        ->atLeast()->once()
        ->andReturn(date('Y-m-d H:i:s'));

    $result = $serviceMock->isLate();
    expect($result)->toBeBool();
    expect($result)->toBeFalse();
});
