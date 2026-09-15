<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Datasets\periodCodes;

test('parses valid period codes correctly', function (string $code, string $expectedUnit, int $expectedQty, float $expectedDays): void {
    $period = new FOSSBilling\Period($code);

    expect($period->getUnit())->toBe($expectedUnit);
    expect($period->getQty())->toBe($expectedQty);
    expect($period->getDays())->toEqual($expectedDays);
    expect($period->getCode())->toBe($code);
})->with(periodCodes());

test('contains the quantity in period titles', function (): void {
    expect((new FOSSBilling\Period('1D'))->getCode())->toContain('1');
    expect((new FOSSBilling\Period('7D'))->getCode())->toContain('7');
    expect((new FOSSBilling\Period('1W'))->getCode())->toContain('1');
    expect((new FOSSBilling\Period('1M'))->getCode())->toContain('1');
    expect((new FOSSBilling\Period('1Y'))->getCode())->toContain('1');
});

test('calculates months correctly', function (): void {
    expect((new FOSSBilling\Period('1M'))->getMonths())->toBe(1);
    expect((new FOSSBilling\Period('3M'))->getMonths())->toBe(3);
    expect((new FOSSBilling\Period('6M'))->getMonths())->toBe(6);
    expect((new FOSSBilling\Period('1Y'))->getMonths())->toBe(12);
});

test('has expiration time in the future', function (): void {
    $now = time();
    $period = new FOSSBilling\Period('1M');

    $expiration = $period->getExpirationTime();
    expect($expiration)->toBeGreaterThanOrEqual($now);
    expect($expiration)->toBeLessThan($now + 35 * 24 * 60 * 60); // Should be less than 35 days
});

test('throws exception for a period code with no quantity', function (): void {
    expect(fn (): FOSSBilling\Period => new FOSSBilling\Period('D'))->toThrow(FOSSBilling\Exception::class, 'Invalid period code. Period definition must be a quantity followed by a unit letter');
});

test('throws exception for invalid period unit', function (): void {
    expect(fn (): FOSSBilling\Period => new FOSSBilling\Period('1Z'))->toThrow(FOSSBilling\Exception::class, 'Period Error. Unit Z is not defined');
});

test('throws exception for empty period code', function (): void {
    expect(fn (): FOSSBilling\Period => new FOSSBilling\Period(''))->toThrow(FOSSBilling\Exception::class, 'Invalid period code. Period definition must be a quantity followed by a unit letter');
});

test('throws exception for a period code with no unit letter', function (): void {
    expect(fn (): FOSSBilling\Period => new FOSSBilling\Period('123'))->toThrow(FOSSBilling\Exception::class, 'Invalid period code. Period definition must be a quantity followed by a unit letter');
});

test('accepts multi-digit quantities up to each unit\'s upper bound', function (): void {
    expect((new FOSSBilling\Period('45D'))->getQty())->toBe(45);
    expect((new FOSSBilling\Period('52W'))->getQty())->toBe(52);
    expect((new FOSSBilling\Period('24M'))->getQty())->toBe(24);
});

test('throws exception for a quantity beyond a unit\'s upper bound', function (): void {
    expect(fn (): FOSSBilling\Period => new FOSSBilling\Period('91D'))->toThrow(FOSSBilling\Exception::class, 'Invalid period quantity 91 for unit D. Allowed range is from 1 to 90');
});
