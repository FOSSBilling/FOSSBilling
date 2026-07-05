<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\Config;
use FOSSBilling\i18n;

beforeEach(function (): void {
    unset($_COOKIE['fb_locale'], $_COOKIE['fb_timezone']);
    Config::setProperty('i18n.timezone', 'UTC');
});

test('getTimezoneList returns every PHP timezone identifier sorted', function (): void {
    $list = i18n::getTimezoneList();

    expect($list)->toBeArray();
    expect($list)->toEqual(DateTimeZone::listIdentifiers());

    // Sorted ascending.
    $sorted = $list;
    sort($sorted);
    expect($list)->toEqual($sorted);
});

test('getTimezones groups identifiers by region with UTC separate', function (): void {
    $grouped = i18n::getTimezones();

    expect($grouped)->toBeArray();
    expect($grouped)->toHaveKey('UTC');
    expect($grouped['UTC'])->toBe(['UTC']);

    // The "UTC" group contains only UTC, every other identifier lives under its region.
    expect($grouped)->toHaveKey('America');
    expect($grouped['America'])->toContain('America/New_York');

    expect($grouped)->toHaveKey('Europe');
    expect($grouped['Europe'])->toContain('Europe/Berlin');

    // Every value should be a sorted, non-empty list of strings.
    foreach ($grouped as $identifiers) {
        expect($identifiers)->toBeArray()->not->toBeEmpty();
        $sorted = $identifiers;
        sort($sorted);
        expect($identifiers)->toEqual($sorted);
    }
});

test('getActiveTimezone returns the system config default when no user is set', function (): void {
    Config::setProperty('i18n.timezone', 'Europe/Paris');

    expect(i18n::getActiveTimezone())->toBe('Europe/Paris');
});

test('getActiveTimezone returns the client timezone when provided and valid', function (): void {
    expect(i18n::getActiveTimezone('America/New_York'))->toBe('America/New_York');
});

test('getActiveTimezone returns the admin timezone when no client timezone is set', function (): void {
    expect(i18n::getActiveTimezone(null, 'Asia/Tokyo'))->toBe('Asia/Tokyo');
});

test('getActiveTimezone prefers the client timezone over the admin timezone', function (): void {
    expect(i18n::getActiveTimezone('America/New_York', 'Asia/Tokyo'))->toBe('America/New_York');
});

test('getActiveTimezone reads the fb_timezone cookie when set and valid', function (): void {
    $_COOKIE['fb_timezone'] = 'Europe/Berlin';

    expect(i18n::getActiveTimezone())->toBe('Europe/Berlin');
});

test('getActiveTimezone ignores an invalid fb_timezone cookie', function (): void {
    $_COOKIE['fb_timezone'] = 'Definitely/Not_Real';

    expect(i18n::getActiveTimezone())->toBe('UTC');
});

test('getActiveTimezone ignores invalid client / admin values and falls back', function (): void {
    expect(i18n::getActiveTimezone('Mars/Olympus_Mons', null))->toBe('UTC');
    expect(i18n::getActiveTimezone(null, 'Mars/Olympus_Mons'))->toBe('UTC');
});

test('getActiveTimezone treats empty string as not set', function (): void {
    expect(i18n::getActiveTimezone('', null))->toBe('UTC');
    expect(i18n::getActiveTimezone(null, ''))->toBe('UTC');
});

test('getActiveTimezone falls back to UTC when no config exists', function (): void {
    // Simulate a missing config by pointing at a known-empty key.
    Config::setProperty('i18n.timezone', '');
    $_COOKIE = [];

    expect(i18n::getActiveTimezone())->toBe('UTC');
});

test('validateTimezone returns null for null and empty input', function (): void {
    expect(i18n::validateTimezone(null))->toBeNull();
    expect(i18n::validateTimezone(''))->toBeNull();
});

test('validateTimezone returns the value when it is a known IANA identifier', function (): void {
    expect(i18n::validateTimezone('America/New_York'))->toBe('America/New_York');
    expect(i18n::validateTimezone('Europe/Berlin'))->toBe('Europe/Berlin');
    expect(i18n::validateTimezone('UTC'))->toBe('UTC');
});

test('validateTimezone throws InformationException for an unknown identifier', function (): void {
    expect(fn (): ?string => i18n::validateTimezone('Mars/Olympus_Mons'))->toThrow(FOSSBilling\InformationException::class);
});
