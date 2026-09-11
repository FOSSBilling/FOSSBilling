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

test('get pairs', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Currency\Api\Guest());

    $willReturn = [
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
    ];

    $repositoryMock = Mockery::mock('\\' . Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('getPairs')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $service = Mockery::mock('\\' . Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $guestApi->setService($service);

    $result = $guestApi->get_pairs();
    expect($willReturn)->toEqual($result);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('EUR');
    expect($result)->toHaveKey('USD');
});

dataset('getProvider', [
    [
        ['code' => 'EUR'],
        'has_model',
        'atLeastOnce',
        'never',
    ],
    [
        [],
        'has_model',
        'never',
        'atLeastOnce',
    ],
]);

test('get', function ($data, $modelFlag, $expectsGetByCode, $expectsGetDefault): void {
    $guestApi = apiEndpoint(new Box\Mod\Currency\Api\Guest());

    $willReturn = [
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'conversion_rate' => 1,
        'format_pattern' => null,
        'fraction_digits' => null,
        'default' => 1,
    ];

    $model = ($modelFlag === 'has_model')
        ? Mockery::mock('\\' . Box\Mod\Currency\Entity\Currency::class)
        : null;

    if ($model !== null) {
        $model
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn($willReturn);
    }

    $repositoryMock = Mockery::mock('\\' . Box\Mod\Currency\Repository\CurrencyRepository::class);
    if ($expectsGetByCode === 'atLeastOnce') {
        $repositoryMock
            ->shouldReceive('findOneByCode')
            ->atLeast()->once()
            ->andReturn($model);
    }
    if ($expectsGetDefault === 'atLeastOnce') {
        $repositoryMock
            ->shouldReceive('findDefault')
            ->atLeast()->once()
            ->andReturn($model);
    }

    $service = Mockery::mock('\\' . Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $guestApi->setService($service);
    $di = container();
    $guestApi->setDi($di);

    $result = $guestApi->get($data);
    expect($result)->toBeArray();
    expect($willReturn)->toEqual($result);
})->with('getProvider');

test('get exception', function (): void {
    $guestApi = apiEndpoint(new Box\Mod\Currency\Api\Guest());

    $repositoryMock = Mockery::mock('\\' . Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock->shouldReceive('findOneByCode')->never();
    $repositoryMock
    ->shouldReceive('findDefault')
    ->atLeast()->once()
    ->andReturn(null);

    $service = Mockery::mock('\\' . Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $guestApi->setService($service);
    $this->expectException(FOSSBilling\Core\Exception\BaseException::class);
    $result = $guestApi->get([]);
});

dataset('formatProvider', [
    [
        [
            'code' => 'EUR',
        ],
        'formatCurrency',
        0.0,
        '€0.00',
    ],
    [
        [
            'code' => 'EUR',
            'price' => 100000,
            'convert' => false,
        ],
        'formatCurrency',
        100000.0,
        '€100,000.00',
    ],
    [
        [
            'code' => 'EUR',
            'price' => 100000,
            'without_currency' => true,
        ],
        'formatNumber',
        60000.0,
        '60,000.00',
    ],
]);

test('format', function ($data, $formatMethod, $expectedAmount, $expectedResult): void {
    $willReturn = [
        'code' => 'EUR',
        'name' => 'Euro',
        'symbol' => '€',
        'conversion_rate' => 0.6,
        'format_pattern' => null,
        'fraction_digits' => null,
        'default' => 1,
    ];

    $guestApi = Mockery::mock(Box\Mod\Currency\Api\Guest::class)->makePartial();
    $guestApi
    ->shouldReceive('get')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $service = Mockery::mock(Box\Mod\Currency\Service::class);
    $service->shouldReceive($formatMethod)
        ->once()
        ->withArgs(fn ($amount, $code): bool => $amount === $expectedAmount && $code === 'EUR')
        ->andReturn($expectedResult);

    $di = container();

    $guestApi->setDi($di);
    $guestApi->setService($service);

    $result = $guestApi->format($data);
    expect($expectedResult)->toEqual($result);
})->with('formatProvider');
