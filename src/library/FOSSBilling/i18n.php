<?php declare(strict_types=1);
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling;

class i18n
{
    /**
     * Attempts to get the correct locale for the current user, or a suitable fallback option if it's unavailable.
     *
     * @return string The locale code to use for the system.
     */
    public static function getActiveLocale(): string
    {
        $config = include PATH_ROOT . '/config.php';

        /* We check in a few different spots for the active locale:
         * First: The BBLANG locale which will be defined after the end-user changes their locale
         * Then: If they haven't manually chosen one, we try to detect the browser locale
         * Next: if that didn't work, try to use the locale in the config file
         * Finally: As a last resort, default to en_US for the locale
         */
        return $_COOKIE['BBLANG'] ?: self::getBrowserLocale() ?: $config['i18n']['locale'] ?: 'en_US';
    }

    /**
     * Retrieves the user's preferred language/locale based on the browser's Accept-Language header.
     *
     * @return string|null The user's preferred language/locale or null if not found.
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
                    setcookie("BBLANG", $locale, ['expires' => strtotime("+1 month"), 'path' => "/"]);
                    return $locale;
                }
            }
        }

        setcookie("BBLANG", $matchingLocale, ['expires' => strtotime("+1 month"), 'path' => "/"]);
        return $matchingLocale;
    }

    /**
     * Retrieve a list of available locales, optionally including their details.
     *
     * @param bool $includeLocaleDetails (optional) Whether to include locale details or not. Defaults to false.
     *
     * @return array An array of locales, sorted alphabetically. If `$includeLocaleDetails` is true, the array will contain
     *               subarrays with the following keys: `locale` (string), `title` (string), `name` (string).
     *               If `$includeLocaleDetails` is false, the array will only contain the locale codes (strings).
     */
    public static function getLocales($includeLocaleDetails  = false): array
    {
        $locales = array_filter(glob(PATH_LANGS . DIRECTORY_SEPARATOR . '*'), 'is_dir');
        $locales = array_map('basename', $locales); // get only the directory name
        sort($locales);
        if (!$includeLocaleDetails) {
            return $locales;
        }
        $details = [];
        $array = include PATH_LANGS . DIRECTORY_SEPARATOR . 'locales.php';
        foreach ($locales as $locale) {
            $title = ($array[$locale] ?? $locale) . "($locale)";
            $details[] = [
                'locale' => $locale,
                'title' => $title,
                'name' => $array[$locale] ?? $locale,
            ];
        }
        return $details;
    }
}
