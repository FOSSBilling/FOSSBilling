<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Core\I18n;

use FOSSBilling\Core\Exception\InformationException;
use FOSSBilling\Core\Http\CookieNames;
use FOSSBilling\Core\Http\CookieQueue;
use FOSSBilling\Core\System\Config;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Locales;

class I18n
{
    private static ?Filesystem $filesystem = null;

    private static function getFilesystem(): Filesystem
    {
        return self::$filesystem ??= new Filesystem();
    }

    /**
     * Attempts to get the correct locale for the current user, or a suitable fallback option if it's unavailable.
     *
     * @param Request          $request    the current HTTP request (for cookie / Accept-Language header reads)
     * @param bool             $autoDetect indicates if the user's Accept-Language header should be used to select the correct locale for them
     * @param CookieQueue|null $cookies    cookie queue for caching the detected locale
     *
     * @return string the locale code to use for the system
     */
    public static function getActiveLocale(Request $request, bool $autoDetect = true, ?CookieQueue $cookies = null): string
    {
        $locale = null;

        $cookieLocale = $request->cookies->get(CookieNames::LOCALE);
        $legacyCookieLocale = $request->cookies->get(CookieNames::LEGACY_LOCALE);
        $cookieBBLANG = $request->cookies->get(CookieNames::LEGACY_BOX_LOCALE);
        $enabledLocales = [];
        if (!empty($cookieLocale) || !empty($legacyCookieLocale) || !empty($cookieBBLANG)) {
            $enabledLocales = self::getLocales();
        }

        /*
         * If the locale cookie is set and it's one of the enabled locales, use that.
         * Otherwise, fallback to auto-detection when enable.
         */
        if (!empty($cookieLocale) && in_array($cookieLocale, $enabledLocales, true)) {
            $locale = $cookieLocale;
        } elseif (!empty($legacyCookieLocale) && in_array($legacyCookieLocale, $enabledLocales, true)) {
            $locale = $legacyCookieLocale;
            $cookies?->queue(CookieNames::LOCALE, (string) $locale, strtotime('+1 month'), '/');
        } elseif (!empty($cookieBBLANG) && in_array($cookieBBLANG, $enabledLocales, true)) {
            $locale = $cookieBBLANG;
            $cookies?->queue(CookieNames::LOCALE, (string) $locale, strtotime('+1 month'), '/');
        } elseif ($autoDetect && self::isBrowserLocaleDetectionEnabled()) {
            $locale = self::getBrowserLocale($request, $cookies);
        }

        self::expireLegacyLocaleCookies($request, $cookies);

        // If we somehow still don't have a locale, use the default / fallback.
        if (!$locale) {
            return Config::getProperty('i18n.locale', 'en_US');
        }

        return $locale;
    }

    private static function isBrowserLocaleDetectionEnabled(): bool
    {
        return \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean(Config::getProperty('i18n.auto_detect_locale', true), true);
    }

    /**
     * Retrieves the user's preferred language/locale based on the browser's Accept-Language header.
     *
     * Delegates to Request::getPreferredLanguage(), which honors the q-values of every
     * header entry and prefix-matches language-only requests against the enabled locales
     * (e.g. "en" matches "en_US").
     *
     * @return string|null the user's preferred language/locale or null if not found
     */
    private static function getBrowserLocale(Request $request, ?CookieQueue $cookies = null): ?string
    {
        // CLI/cron requests and header-less clients send no Accept-Language.
        // Without this guard, getPreferredLanguage() returns the first passed
        // locale instead of null, overriding the configured i18n.locale default.
        if (trim((string) $request->headers->get('Accept-Language', '')) === '') {
            return null;
        }

        // The configured default goes first so an unsupported browser locale falls back to it.
        $locales = array_values(array_unique([
            Config::getProperty('i18n.locale', 'en_US'),
            ...self::getLocales(),
        ]));

        $detectedLocale = $request->getPreferredLanguage($locales);

        // A match that resolves to the configured default returns null without queueing a
        // cookie: the resulting page locale is identical, and staying cookie-free keeps
        // auto-detection responsive when the enabled locales change.
        if ($detectedLocale === null || $detectedLocale === $locales[0]) {
            return null;
        }

        $cookies?->queue(CookieNames::LOCALE, $detectedLocale, strtotime('+1 month'), '/');

        return $detectedLocale;
    }

    /**
     * Returns the timezone the current request should be rendered in.
     *
     * Resolves in order: client -> admin -> `fossbilling_timezone` cookie -> `i18n.timezone` config -> `UTC`.
     * Invalid values are silently dropped so a stale cookie or corrupt DB value can't crash the date formatter.
     */
    public static function getActiveTimezone(
        Request $request,
        ?string $clientTimezone = null,
        ?string $adminTimezone = null,
        ?CookieQueue $cookies = null,
    ): string {
        $valid = self::getTimezoneList();
        $cookieTimezone = $request->cookies->get(CookieNames::TIMEZONE);
        $legacyCookieTimezone = $request->cookies->get(CookieNames::LEGACY_TIMEZONE);

        if ($request->cookies->has(CookieNames::LEGACY_TIMEZONE)) {
            if (
                (!is_string($cookieTimezone) || !in_array($cookieTimezone, $valid, true))
                && is_string($legacyCookieTimezone)
                && in_array($legacyCookieTimezone, $valid, true)
            ) {
                $cookies?->queue(CookieNames::TIMEZONE, $legacyCookieTimezone, strtotime('+1 year'), '/');
                $cookieTimezone = $legacyCookieTimezone;
            }

            $cookies?->queue(CookieNames::LEGACY_TIMEZONE, '', time() - 3600, '/');
        }

        foreach ([$clientTimezone, $adminTimezone, $cookieTimezone] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, $valid, true)) {
                return $candidate;
            }
        }

        $configured = Config::getProperty('i18n.timezone', 'UTC');
        if (is_string($configured) && in_array($configured, $valid, true)) {
            return $configured;
        }

        return 'UTC';
    }

    private static function expireLegacyLocaleCookies(Request $request, ?CookieQueue $cookies): void
    {
        foreach ([CookieNames::LEGACY_LOCALE, CookieNames::LEGACY_BOX_LOCALE] as $name) {
            if ($request->cookies->has($name)) {
                $cookies?->queue($name, '', time() - 3600, '/');
            }
        }
    }

    /**
     * IANA timezone identifiers grouped by region, suitable for rendering a `<select>` with `<optgroup>`.
     * UTC is always present in its own bucket.
     *
     * @return array<string, list<string>>
     */
    public static function getTimezones(): array
    {
        $identifiers = \DateTimeZone::listIdentifiers();
        sort($identifiers);

        $grouped = ['UTC' => ['UTC']];
        foreach ($identifiers as $identifier) {
            if ($identifier === 'UTC') {
                continue;
            }

            $region = strstr($identifier, '/', true) ?: 'Other';
            $grouped[$region] ??= [];
            $grouped[$region][] = $identifier;
        }

        return $grouped;
    }

    /**
     * Flat list of supported timezone identifiers, sorted alphabetically.
     *
     * @return list<string>
     */
    public static function getTimezoneList(): array
    {
        $list = \DateTimeZone::listIdentifiers();
        sort($list);

        return $list;
    }

    /**
     * Returns `$timezone` if it is a valid IANA identifier, `null` for empty input.
     *
     * @throws InformationException when the value is non-empty and not a known timezone
     */
    public static function validateTimezone(?string $timezone): ?string
    {
        if ($timezone === null || $timezone === '') {
            return null;
        }

        if (!in_array($timezone, self::getTimezoneList(), true)) {
            throw new InformationException('Invalid timezone: :tz', [':tz' => $timezone]);
        }

        return $timezone;
    }

    /**
     * Retrieve a list of available locales, optionally including their details.
     *
     * @param bool $includeLocaleDetails (optional) Whether to include locale details or not. Defaults to false.
     * @param bool $disabled             set to true if you want it to return a list of the disabled locales, defaults to false which will return the enabled locales
     *
     * @return array An array of locales, sorted alphabetically. If `$includeLocaleDetails` is true, the array will contain
     *               subarrays with the following keys: `locale` (string), `title` (string), `name` (string).
     *               If `$includeLocaleDetails` is false, the array will only contain the locale codes (strings).
     */
    public static function getLocales(bool $includeLocaleDetails = false, bool $disabled = false): array
    {
        $filesystem = self::getFilesystem();
        $locales = self::getLocaleList($disabled);
        if (!$includeLocaleDetails) {
            return $locales;
        }
        $details = [];

        // Handle when FOSSBilling is running with a dummy locale folder.
        $localePhpPath = Path::join(PATH_LANGS, 'locales.php');
        $array = ($filesystem->exists($localePhpPath)) ? include $localePhpPath : Locales::getNames(Config::getProperty('i18n.locale', 'en_US'));

        foreach ($locales as $locale) {
            $title = $array[$locale] ?? $locale;
            $details[] = [
                'locale' => $locale,
                'title' => $title,
                'name' => $array[$locale] ?? $locale,
            ];
        }

        return $details;
    }

    /**
     * Enables / disables a locale depending on it's current status.
     *
     * @param string $locale The locale code to toggle. (Example: `en_US`)
     *
     * @return bool To indicate if it was successful,
     *
     * @throws InformationException
     */
    public static function toggleLocale(string $locale): bool
    {
        $filesystem = self::getFilesystem();
        $availableLocales = array_merge(self::getLocaleList(), self::getLocaleList(true));
        if (!in_array($locale, $availableLocales, true)) {
            throw new InformationException('Unable to enable / disable the locale as it is not present in the locale folder.');
        }

        $basePath = Path::join(PATH_LANGS, $locale);
        $disablePath = Path::join($basePath, '.disabled');

        // Reverse the status of the locale
        if ($filesystem->exists($disablePath)) {
            $filesystem->remove($disablePath);

            return true;
        }
        $filesystem->dumpFile($disablePath, '');

        return $filesystem->exists($disablePath);
    }

    /**
     * Returns how complete a locale is.
     * Will return 0 if the `completion.php` doesn't exist or if it doesn't include the specified locale.
     *
     * @param string $locale The locale ID (Example: `en_US`)
     *
     * @return int the percentage complete for the specified locale
     */
    public static function getLocaleCompletionPercent(string $locale): int
    {
        $filesystem = self::getFilesystem();
        if ($locale === 'en_US') {
            return 100;
        }

        $completionFile = Path::join(PATH_LANGS, 'completion.php');
        if (!$filesystem->exists($completionFile)) {
            return 0;
        }

        $completion = include $completionFile;

        return intval($completion[$locale] ?? 0);
    }

    /**
     * Internal helper function that gets the list of locales off of the disk.
     *
     * @param bool $disabled Set to true to get the list of disabled locales. True returns the list of enabled locales.
     *
     * @return array the list of locale codes, sorted alphabetically
     */
    private static function getLocaleList(bool $disabled = false): array
    {
        $filesystem = self::getFilesystem();

        $finder = new Finder();
        $finder->directories()->in(PATH_LANGS)->depth('== 0');

        $locales = iterator_to_array($finder);
        $locales = ($disabled) ? array_filter($locales, fn ($locale): bool => $filesystem->exists(Path::join($locale->getPathname(), '.disabled')))
                               : array_filter($locales, fn ($locale): bool => !$filesystem->exists(Path::join($locale->getPathname(), '.disabled')));
        $locales = array_map(fn ($locale) => $locale->getBasename(), $locales);
        sort($locales);

        return $locales;
    }
}
