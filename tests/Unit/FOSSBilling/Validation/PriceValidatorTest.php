<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Validation\PriceValidator;

dataset('validAmounts', fn (): array => [
    'integer' => [10, 10.0],
    'decimal string' => ['10.50', 10.50],
    'zero' => [0, 0.0],
]);

dataset('invalidAmounts', fn (): array => [
    'negative int' => [-1],
    'negative float' => [-0.01],
    'non-numeric string' => ['abc'],
    'empty string' => [''],
    'null' => [null],
    'array' => [[]],
    'boolean' => [true],
]);

dataset('validQuantities', fn (): array => [
    'integer' => [5, 5],
    'numeric string' => ['3', 3],
    'one' => [1, 1],
]);

dataset('flooredQuantities', fn (): array => [
    'zero' => [0, 1],
    'float zero' => [0.0, 1],
    'negative int' => [-5, 1],
    'negative float' => [-1.5, 1],
    'positive float' => [1.5, 1],
    'positive float above one' => [2.7, 2],
]);

dataset('invalidQuantities', fn (): array => [
    'non-numeric string' => ['abc'],
    'empty string' => [''],
    'null' => [null],
    'infinite' => [INF],
    'overflowed numeric string' => ['1e999'],
    'integer overflow' => [PHP_INT_MAX . '0'],
    'array' => [[]],
    'boolean' => [true],
]);

test('validateAmount accepts valid amounts', function (mixed $input, float $expected): void {
    $result = PriceValidator::validateAmount($input);

    expect($result)->toBeFloat();
    expect($result)->toEqual($expected);
})->with('validAmounts');

test('validateAmount rejects invalid amounts', function (mixed $input): void {
    expect(fn (): float => PriceValidator::validateAmount($input))
        ->toThrow(InformationException::class);
})->with('invalidAmounts');

test('validateAmount uses custom field name in error', function (): void {
    expect(fn (): float => PriceValidator::validateAmount(-5, 'Setup fee'))
        ->toThrow(InformationException::class, 'Setup fee cannot be negative.');
});

test('validateAmount rejects non-numeric with field name', function (): void {
    expect(fn (): float => PriceValidator::validateAmount('abc', 'Unit price'))
        ->toThrow(InformationException::class, 'Unit price must be a valid number.');
});

test('validateSignedAmount accepts negative adjustments', function (): void {
    expect(PriceValidator::validateSignedAmount('-10.50'))->toBe(-10.50);
});

test('validateSignedAmount accepts positive amounts', function (): void {
    expect(PriceValidator::validateSignedAmount('10.50'))->toBe(10.50);
});

test('validateSignedAmount accepts zero', function (): void {
    expect(PriceValidator::validateSignedAmount(0))->toBe(0.0);
});

test('validateSignedAmount rejects non-numeric values', function (): void {
    expect(fn (): float => PriceValidator::validateSignedAmount('not-a-price'))
        ->toThrow(InformationException::class, 'Price must be a valid number.');
});

test('validateSignedAmount uses custom field name in error', function (): void {
    expect(fn (): float => PriceValidator::validateSignedAmount('not-a-price', 'Adjustment'))
        ->toThrow(InformationException::class, 'Adjustment must be a valid number.');
});

test('validateQuantity accepts valid quantities', function (mixed $input, int $expected): void {
    $result = PriceValidator::validateQuantity($input);

    expect($result)->toBeInt();
    expect($result)->toEqual($expected);
})->with('validQuantities');

test('validateQuantity floors to minimum 1', function (mixed $input, int $expected): void {
    $result = PriceValidator::validateQuantity($input);

    expect($result)->toBeInt();
    expect($result)->toEqual($expected);
})->with('flooredQuantities');

test('validateQuantity rejects invalid values', function (mixed $input): void {
    expect(fn (): int => PriceValidator::validateQuantity($input))
        ->toThrow(InformationException::class);
})->with('invalidQuantities');
