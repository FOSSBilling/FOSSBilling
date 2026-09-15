<?php

declare(strict_types=1);

use FOSSBilling\InformationException;
use FOSSBilling\Validation\NonNegativeIntegerValidator;

test('validates non-negative integers', function (mixed $value, int $expected): void {
    expect(NonNegativeIntegerValidator::validate($value, 'Invalid value'))->toBe($expected);
})->with([
    [0, 0],
    [5, 5],
    ['0', 0],
    ['5', 5],
    [(string) PHP_INT_MAX, PHP_INT_MAX],
]);

test('rejects values that are not non-negative integers', function (mixed $value): void {
    expect(fn (): int => NonNegativeIntegerValidator::validate($value, 'Invalid value'))
        ->toThrow(InformationException::class, 'Invalid value');
})->with([
    -1,
    '-1',
    1.5,
    '1.5',
    '01',
    PHP_INT_MAX . '0',
    true,
    null,
]);
