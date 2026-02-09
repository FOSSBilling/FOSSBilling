<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Datasets\periodCodes;

it('parses valid period codes correctly', function (string $code, string $expectedUnit, int $expectedQty, float $expectedDays) {
    $period = new Box_Period($code);

    expect($period->getUnit())->toBe($expectedUnit);
    expect($period->getQty())->toBe($expectedQty);
    expect($period->getDays())->toEqual($expectedDays);
    expect($period->getCode())->toBe($code);
})->with(periodCodes());

it('contains the quantity in period titles', function () {
    expect((new Box_Period('1D'))->getCode())->toContain('1');
    expect((new Box_Period('7D'))->getCode())->toContain('7');
    expect((new Box_Period('1W'))->getCode())->toContain('1');
    expect((new Box_Period('1M'))->getCode())->toContain('1');
    expect((new Box_Period('1Y'))->getCode())->toContain('1');
});

it('calculates months correctly', function () {
    expect((new Box_Period('1M'))->getMonths())->toBe(1);
    expect((new Box_Period('3M'))->getMonths())->toBe(3);
    expect((new Box_Period('6M'))->getMonths())->toBe(6);
    expect((new Box_Period('1Y'))->getMonths())->toBe(12);
});

it('has expiration time in the future', function () {
    $now = time();
    $period = new Box_Period('1M');

    $expiration = $period->getExpirationTime();
    expect($expiration)->toBeGreaterThanOrEqual($now);
    expect($expiration)->toBeLessThan($now + 35 * 24 * 60 * 60); // Should be less than 35 days
});

it('throws exception for invalid period code length', function () {
    new Box_Period('1');
})->throws(\FOSSBilling\Exception::class, 'Invalid period code. Period definition must be 2 chars length');

it('throws exception for invalid period unit', function () {
    new Box_Period('1Z');
})->throws(\FOSSBilling\Exception::class, 'Period Error. Unit Z is not defined');

it('throws exception for empty period code', function () {
    new Box_Period('');
})->throws(\FOSSBilling\Exception::class, 'Invalid period code. Period definition must be 2 chars length');

it('throws exception for period code that is too long', function () {
    new Box_Period('123');
})->throws(\FOSSBilling\Exception::class, 'Invalid period code. Period definition must be 2 chars length');
