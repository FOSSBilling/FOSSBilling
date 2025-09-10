<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class i18n
{
    /**
     * Attempts to get the correct locale for the current user, or a suitable fallback option if it's unavailable.
     *
     * @param bool $autoDetect indicates if the user's Accept-Language header should be used to select the correct locale for them
     *
     * @return string the locale code to use for the system
     */
    public static function getActiveLocale(bool $autoDetect = true): string
    {
        $locale = null;

        /*
         * If the locale cookie is set and it's one of the enabled locales, use that.
         * Otherwise, fallback to auto-detection when enable.
         */
        if (!empty($_COOKIE['BBLANG']) && in_array($_COOKIE['BBLANG'], self::getLocales())) {
            $locale = $_COOKIE['BBLANG'];
        } elseif ($autoDetect) {
            $locale = self::getBrowserLocale();
        }

        // If we somehow still don't have a locale, use the default / fallback.
        if (!$locale) {
            return Config::getProperty('i18n.locale', 'en_US');
        }

        return $locale;
    }

    /**
     * Retrieves the user's preferred language/locale based on the browser's Accept-Language header.
     *
     * @return string|null the user's preferred language/locale or null if not found
     */
    private static function getBrowserLocale(): ?string
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

        try {
            $detectedLocale = @\Locale::acceptFromHttp($header);
            $detectedLocale = @\Locale::canonicalize($detectedLocale . '.utf8');
        } catch (\Exception) {
            $detectedLocale = '';
        }

        if (empty($detectedLocale) || !$detectedLocale) {
            $detectedLocale = '';
        }

        try {
            $matchingLocale = \Locale::lookup(self::getLocales(), $detectedLocale, false, null);
        } catch (\Exception) {
            $matchingLocale = null;
        }

        /* The system was unable to match the browser locale to one of our local ones.
         * This is most likely because Locale::lookup will not match en with en_US. It will only match en_US with en.
         * As a workaround, let's see if one of the available locales starts with the detected locale.
         * If it does, return that.
         */
        if (empty($matchingLocale)) {
            if (strlen($detectedLocale) < 2) {
                return null;
            }
            foreach (self::getLocales() as $locale) {
                if (str_starts_with($locale, substr($detectedLocale, 0, 2))) {
                    if (!headers_sent()) {
                        setcookie('BBLANG', $locale, ['expires' => strtotime('+1 month'), 'path' => '/']);
                    }

                    return $locale;
                }
            }
        }

        if (!headers_sent()) {
            setcookie('BBLANG', $matchingLocale, ['expires' => strtotime('+1 month'), 'path' => '/']);
        }

        return $matchingLocale;
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
        $filesystem = new Filesystem();
        $locales = self::getLocaleList($disabled);
        if (!$includeLocaleDetails) {
            return $locales;
        }
        $details = [];

        // Handle when FOSSBilling is running with a dummy locale folder.
        $localePhpPath = Path::join(PATH_LANGS, 'locales.php');
        $array = ($filesystem->exists($localePhpPath)) ? include $localePhpPath : ['en_US' => 'English'];

        foreach ($locales as $locale) {
            $title = ($array[$locale] ?? $locale) . " ($locale)";
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
        $filesystem = new Filesystem();
        $basePath = Path::join(PATH_LANGS, $locale);
        if (!$filesystem->exists($basePath)) {
            throw new InformationException('Unable to enable / disable the locale as it is not present in the locale folder.');
        }

        $disablePath = Path::join($basePath, '.disabled');

        // Reverse the status of the locale
        if ($filesystem->exists($disablePath)) {
            $filesystem->remove($disablePath);

            return true;
        } else {
            $filesystem->dumpFile($disablePath, '');

            return $filesystem->exists($disablePath);
        }
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
        $filesystem = new Filesystem();
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
        $filesystem = new Filesystem();

        $finder = new Finder();
        $finder->directories()->in(PATH_LANGS)->depth('== 0');

        $locales = iterator_to_array($finder);
        $locales = ($disabled) ? array_filter($locales, fn ($locale): bool => $filesystem->exists(Path::join($locale->getPathname(), '.disabled')))
                               : array_filter($locales, fn ($locale): bool => !$filesystem->exists(Path::join($locale->getPathname(), '.disabled')));
        $locales = array_map(basename(...), $locales);
        sort($locales);

        return $locales;
    }
}
