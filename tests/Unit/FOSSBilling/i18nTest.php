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
use FOSSBilling\Http\CookieNames;
use FOSSBilling\Http\CookieQueue;
use FOSSBilling\i18n;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    Config::setProperty('i18n.timezone', 'UTC');
    Config::setProperty('i18n.locale', 'en_US');
});

afterEach(function (): void {
    Config::setProperty('i18n.timezone', 'UTC');
    Config::setProperty('i18n.locale', 'en_US');
});

function requestWithTimezoneCookie(?string $timezone = null, string $cookieName = CookieNames::TIMEZONE): Request
{
    $request = Request::create('/');
    if ($timezone !== null) {
        $request->cookies->set($cookieName, $timezone);
    }

    return $request;
}

test('getTimezoneList returns every PHP timezone identifier sorted', function (): void {
    $list = i18n::getTimezoneList();

    expect($list)->toBeArray();

    // Contains exactly all PHP timezone identifiers (order-independent).
    $expected = DateTimeZone::listIdentifiers();
    $sortedList = $list;
    $sortedExpected = $expected;
    sort($sortedList);
    sort($sortedExpected);
    expect($sortedList)->toEqual($sortedExpected);

    // Sorted ascending.
    $sorted = $list;
    sort($sorted);
    expect($list)->toEqual($sorted);
});

test('getTimezones groups identifiers by region with UTC separate', function (): void {
    $grouped = i18n::getTimezones();

    expect($grouped)->toBeArray();
    expect($grouped)->toHaveKey('UTC');
    expect($grouped['UTC'])->toContain('UTC');

    // The "UTC" group contains UTC, every other identifier lives under its region.
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

    expect(i18n::getActiveTimezone(Request::create('/')))->toBe('Europe/Paris');
});

test('getActiveTimezone returns the client timezone when provided and valid', function (): void {
    expect(i18n::getActiveTimezone(Request::create('/'), 'America/New_York'))->toBe('America/New_York');
});

test('getActiveTimezone returns the admin timezone when no client timezone is set', function (): void {
    expect(i18n::getActiveTimezone(Request::create('/'), null, 'Asia/Tokyo'))->toBe('Asia/Tokyo');
});

test('getActiveTimezone prefers the client timezone over the admin timezone', function (): void {
    expect(i18n::getActiveTimezone(Request::create('/'), 'America/New_York', 'Asia/Tokyo'))->toBe('America/New_York');
});

test('getActiveTimezone reads the namespaced timezone cookie when set and valid', function (): void {
    $request = requestWithTimezoneCookie('Europe/Berlin');

    expect(i18n::getActiveTimezone($request))->toBe('Europe/Berlin');
});

test('getActiveTimezone prefers explicit client/admin arguments over a valid timezone cookie', function (): void {
    $request = requestWithTimezoneCookie('Europe/Berlin');

    expect(i18n::getActiveTimezone($request, 'America/New_York', 'Asia/Tokyo'))->toBe('America/New_York');
    expect(i18n::getActiveTimezone($request, 'America/New_York', null))->toBe('America/New_York');
    expect(i18n::getActiveTimezone($request, null, 'Asia/Tokyo'))->toBe('Asia/Tokyo');
    expect(i18n::getActiveTimezone($request, null, null))->toBe('Europe/Berlin');
});

test('getActiveTimezone ignores an invalid timezone cookie', function (): void {
    $request = requestWithTimezoneCookie('Definitely/Not_Real');

    expect(i18n::getActiveTimezone($request))->toBe('UTC');
});

test('getActiveTimezone ignores an invalid timezone cookie when explicit arguments are valid', function (): void {
    $request = requestWithTimezoneCookie('Definitely/Not_Real');

    expect(i18n::getActiveTimezone($request, 'America/New_York', 'Asia/Tokyo'))->toBe('America/New_York');
    expect(i18n::getActiveTimezone($request, 'America/New_York', null))->toBe('America/New_York');
    expect(i18n::getActiveTimezone($request, null, 'Asia/Tokyo'))->toBe('Asia/Tokyo');
});

test('getActiveTimezone falls back to a valid timezone cookie when client timezone is invalid', function (): void {
    $request = requestWithTimezoneCookie('Europe/Berlin');

    expect(i18n::getActiveTimezone($request, 'Mars/Olympus_Mons', null))->toBe('Europe/Berlin');
    expect(i18n::getActiveTimezone($request, 'Mars/Olympus_Mons', 'Mars/Olympus_Mons'))->toBe('Europe/Berlin');
    expect(i18n::getActiveTimezone($request, null, 'Mars/Olympus_Mons'))->toBe('Europe/Berlin');
});

test('getActiveTimezone ignores invalid client / admin values and falls back', function (): void {
    $request = Request::create('/');

    expect(i18n::getActiveTimezone($request, 'Mars/Olympus_Mons', null))->toBe('UTC');
    expect(i18n::getActiveTimezone($request, null, 'Mars/Olympus_Mons'))->toBe('UTC');
    expect(i18n::getActiveTimezone($request, 'Mars/Olympus_Mons', 'Mars/Olympus_Mons'))->toBe('UTC');
    expect(i18n::getActiveTimezone($request, 'Mars/Olympus_Mons', 'Asia/Tokyo'))->toBe('Asia/Tokyo');
});

test('getActiveTimezone treats empty string as not set', function (): void {
    $request = Request::create('/');

    expect(i18n::getActiveTimezone($request, '', null))->toBe('UTC');
    expect(i18n::getActiveTimezone($request, null, ''))->toBe('UTC');
});

test('getActiveTimezone falls back to UTC when no config exists', function (): void {
    // Simulate a missing config by pointing at a known-empty key.
    Config::setProperty('i18n.timezone', '');

    expect(i18n::getActiveTimezone(Request::create('/')))->toBe('UTC');
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

test('validateTimezone throws InformationException for invalid timezone identifiers', function (): void {
    foreach (['Mars/Olympus_Mons', 'Europe/'] as $timezone) {
        expect(fn (): ?string => i18n::validateTimezone($timezone))->toThrow(FOSSBilling\InformationException::class);
    }
});

test('getActiveLocale returns the namespaced locale cookie when it matches an enabled locale', function (): void {
    // Only en_US is guaranteed to be installed (translations are not fetched in CI),
    // so use a different config default to prove the cookie takes precedence.
    Config::setProperty('i18n.locale', 'de_DE');

    $request = Request::create('/');
    $request->cookies->set(CookieNames::LOCALE, 'en_US');

    expect(i18n::getActiveLocale($request, false))->toBe('en_US');
});

test('getActiveLocale ignores an invalid locale cookie and falls back to config default', function (): void {
    $request = Request::create('/');
    $request->cookies->set(CookieNames::LOCALE, 'xx_XX');

    expect(i18n::getActiveLocale($request, false))->toBe('en_US');
});

test('getActiveLocale falls back to config default when no cookie is set and autoDetect is false', function (): void {
    Config::setProperty('i18n.locale', 'de_DE');

    expect(i18n::getActiveLocale(Request::create('/'), false))->toBe('de_DE');
});

test('getActiveLocale auto-detects locale from Accept-Language header when enabled', function (): void {
    Config::setProperty('i18n.locale', 'de_DE');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

    expect(i18n::getActiveLocale($request, true))->toBe('en_US');
});

test('getActiveLocale returns the configured default when no cookie and no Accept-Language header', function (): void {
    expect(i18n::getActiveLocale(Request::create('/'), false))->toBe('en_US');
});

test('getActiveTimezone migrates and expires the legacy timezone cookie', function (): void {
    $request = requestWithTimezoneCookie('Europe/Berlin', CookieNames::LEGACY_TIMEZONE);
    $cookies = new CookieQueue();

    expect(i18n::getActiveTimezone($request, cookies: $cookies))->toBe('Europe/Berlin');

    $response = new Response();
    $cookies->applyToResponse($response);
    $queued = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $queued[$cookie->getName()] = $cookie;
    }

    expect($queued[CookieNames::TIMEZONE]->getValue())->toBe('Europe/Berlin')
        ->and($queued[CookieNames::LEGACY_TIMEZONE]->getExpiresTime())->toBeLessThan(time());
});

test('getActiveLocale migrates and expires the legacy locale cookie', function (): void {
    $request = Request::create('/');
    $request->cookies->set(CookieNames::LEGACY_LOCALE, 'en_US');
    $cookies = new CookieQueue();

    expect(i18n::getActiveLocale($request, false, $cookies))->toBe('en_US');

    $response = new Response();
    $cookies->applyToResponse($response);
    $queued = [];
    foreach ($response->headers->getCookies() as $cookie) {
        $queued[$cookie->getName()] = $cookie;
    }

    expect($queued[CookieNames::LOCALE]->getValue())->toBe('en_US')
        ->and($queued[CookieNames::LEGACY_LOCALE]->getExpiresTime())->toBeLessThan(time());
});
