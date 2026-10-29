<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Core\Validation\PhoneValidator;

test('phone validation returns normalized digits without losing leading zeroes', function (string $number): void {
    expect(PhoneValidator::validatePhoneNumber($number))->toBe('0100000000');
})->with([
    'plain' => '0100000000',
    'formatted' => '(010) 000-0000',
    'direction marks' => "\u{202A}0100000000\u{202C}",
    'non-breaking spaces' => "010\u{00A0}000\u{00A0}0000",
]);

test('phone validation rejects invalid numbers', function (string $number): void {
    expect(fn (): string => PhoneValidator::validatePhoneNumber($number))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
})->with(['', 'no digits', '1234567890123', '+2250100000000']);
