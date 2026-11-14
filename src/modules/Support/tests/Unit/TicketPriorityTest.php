<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\TicketPriority;

test('priority maps input to the stored number', function (): void {
    expect(TicketPriority::fromInput(null))->toBe(100);
    expect(TicketPriority::fromInput(''))->toBe(100);
    expect(TicketPriority::fromInput('low'))->toBe(50);
    expect(TicketPriority::fromInput(' Medium '))->toBe(100);
    expect(TicketPriority::fromInput('HIGH'))->toBe(150);
    expect(TicketPriority::fromInput(50))->toBe(50);
    expect(TicketPriority::fromInput('150'))->toBe(150);
});

test('priority rejects unknown input', function (mixed $value): void {
    expect(fn (): int => TicketPriority::fromInput($value))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Low, Medium or High');
})->with([['urgent'], [99], ['101'], [-1], [[50]], [1.5]]);

test('priority reads stored numbers as the nearest level', function (): void {
    expect(TicketPriority::level(1))->toBe('low');
    expect(TicketPriority::level(50))->toBe('low');
    expect(TicketPriority::level(99))->toBe('low');
    expect(TicketPriority::level(100))->toBe('medium');
    expect(TicketPriority::level(null))->toBe('medium');
    expect(TicketPriority::level(101))->toBe('high');
    expect(TicketPriority::level(200))->toBe('high');
});

test('priority filter covers the whole scale', function (): void {
    expect(TicketPriority::filter('low'))->toBe(['<', 100]);
    expect(TicketPriority::filter('MEDIUM'))->toBe(['=', 100]);
    expect(TicketPriority::filter(' high '))->toBe(['>', 100]);
    expect(fn (): array => TicketPriority::filter('urgent'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
});
