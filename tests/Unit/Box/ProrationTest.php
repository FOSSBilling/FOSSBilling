<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Order\Proration;

test('parses billing periods correctly in proration', function (): void {
    expect(Proration::parsePeriod('1M'))->toBe([1, 'M']);
    expect(Proration::parsePeriod('3M'))->toBe([3, 'M']);
    expect(Proration::parsePeriod('6M'))->toBe([6, 'M']);
    expect(Proration::parsePeriod('1Y'))->toBe([1, 'Y']);
    expect(Proration::parsePeriod('1W'))->toBe([1, 'W']);
});

test('calculates same cycle proration correctly', function (): void {
    // 10/month moving to 20/month with 15 days left out of 31 days
    $quote = Proration::quote(10.0, '1M', '2026-11-16 00:00:00', 20.0, '1M', '2026-11-01 10:00:00');

    expect($quote['kind'])->toBe('upgrade');
    expect($quote['same_cycle'])->toBeTrue();
    expect($quote['days_left'])->toBe(15);
    expect($quote['credit'])->toBe(4.84);
    expect($quote['charge'])->toBe(9.68);
    expect($quote['amount'])->toBe(4.84);
});

test('calculates downgrade same cycle correctly', function (): void {
    // 20/month moving to 10/month with 15 days left out of 31 days
    $quote = Proration::quote(20.0, '1M', '2026-11-16 00:00:00', 10.0, '1M', '2026-11-01 10:00:00');

    expect($quote['kind'])->toBe('downgrade');
    expect($quote['same_cycle'])->toBeTrue();
    expect($quote['days_left'])->toBe(15);
    expect($quote['credit'])->toBe(9.68);
    expect($quote['charge'])->toBe(4.84);
    expect($quote['amount'])->toBe(-4.84);
});

test('calculates different cycle upgrade correctly', function (): void {
    // 10/month moving to 100/year
    $quote = Proration::quote(10.0, '1M', '2026-11-16 00:00:00', 100.0, '1Y', '2026-11-01 10:00:00');

    expect($quote['kind'])->toBe('upgrade');
    expect($quote['same_cycle'])->toBeFalse();
    expect($quote['days_left'])->toBe(15);
    expect($quote['credit'])->toBe(4.84);
    expect($quote['charge'])->toBe(100.0);
    expect($quote['amount'])->toBe(95.16);
});

test('handles service expiring today', function (): void {
    $quote = Proration::quote(10.0, '1M', '2026-11-16 00:00:00', 20.0, '1M', '2026-11-16 09:00:00');

    expect($quote['days_left'])->toBe(0);
    expect($quote['credit'])->toBe(0.0);
    expect($quote['charge'])->toBe(0.0);
    expect($quote['amount'])->toBe(0.0);
});
