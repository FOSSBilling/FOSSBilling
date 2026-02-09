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

test('get pairs', function () {
    $guestApi = new \Box\Mod\Currency\Api\Guest();

    $willReturn = [
        'EUR' => 'Euro',
        'USD' => 'US Dollar',
    ];

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock
    ->shouldReceive('getPairs')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $guestApi->setService($service);

    $result = $guestApi->get_pairs([]);
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

test('get', function ($data, $modelFlag, $expectsGetByCode, $expectsGetDefault) {
    $guestApi = new \Box\Mod\Currency\Api\Guest();

    $willReturn = [
        'code' => 'EUR',
        'title' => 'Euro',
        'conversion_rate' => 1,
        'format' => '{{price}}',
        'price_format' => 1,
        'default' => 1,
    ];

    $model = ($modelFlag === 'has_model')
        ? Mockery::mock('\\' . \Box\Mod\Currency\Entity\Currency::class)
        : null;

    if ($model !== null) {
        $model
    ->shouldReceive('toApiArray')
    ->atLeast()->once()
    ->andReturn($willReturn);
    }

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
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

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
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

test('get exception', function () {
    $guestApi = new \Box\Mod\Currency\Api\Guest();

    $repositoryMock = Mockery::mock('\\' . \Box\Mod\Currency\Repository\CurrencyRepository::class);
    $repositoryMock->shouldReceive("findOneByCode")->never();
    $repositoryMock
    ->shouldReceive('findDefault')
    ->atLeast()->once()
    ->andReturn(null);

    $service = Mockery::mock('\\' . \Box\Mod\Currency\Service::class);
    $service
    ->shouldReceive('getCurrencyRepository')
    ->atLeast()->once()
    ->andReturn($repositoryMock);

    $guestApi->setService($service);
    $this->expectException(\FOSSBilling\Exception::class);
    $result = $guestApi->get([]);
});

dataset('formatPriceFormatProvider', [
    [1, '€ 60000.00'],
    [2, '€ 60,000.00'],
    [3, '€ 60.000,00'],
    [4, '€ 60,000'],
    [5, '€ 60000'],
]);

test('format price format', function ($price_format, $expectedResult) {
    $willReturn = [
        'code' => 'EUR',
        'title' => 'Euro',
        'conversion_rate' => 0.6,
        'format' => '€ {{price}}',
        'price_format' => $price_format,
        'default' => 1,
    ];

    $data = [
        'code' => 'EUR',
        'price' => 100000,
        'without_currency' => false,
    ];
    $guestApi = Mockery::mock(\Box\Mod\Currency\Api\Guest::class)->makePartial();
    $guestApi
    ->shouldReceive('get')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $service = $this->createStub(\Box\Mod\Currency\Service::class);

    $di = container();

    $guestApi->setDi($di);
    $guestApi->setService($service);

    $result = $guestApi->format($data);
    expect($expectedResult)->toEqual($result);
})->with('formatPriceFormatProvider');

dataset('formatProvider', [
    [
        [
            'code' => 'EUR',
        ],
        '€ 0.00',
    ],
    [
        [
            'code' => 'EUR',
            'price' => 100000,
            'convert' => false,
        ],
        '€ 100000.00',
    ],
    [
        [
            'code' => 'EUR',
            'price' => 100000,
            'without_currency' => true,
        ],
        '60000.00',
    ],
]);

test('format', function ($data, $expectedResult) {
    $willReturn = [
        'code' => 'EUR',
        'title' => 'Euro',
        'conversion_rate' => 0.6,
        'format' => '€ {{price}}',
        'price_format' => 1,
        'default' => 1,
    ];

    $guestApi = Mockery::mock(\Box\Mod\Currency\Api\Guest::class)->makePartial();
    $guestApi
    ->shouldReceive('get')
    ->atLeast()->once()
    ->andReturn($willReturn);

    $service = $this->createStub(\Box\Mod\Currency\Service::class);

    $di = container();

    $guestApi->setDi($di);
    $guestApi->setService($service);

    $result = $guestApi->format($data);
    expect($expectedResult)->toEqual($result);
})->with('formatProvider');
