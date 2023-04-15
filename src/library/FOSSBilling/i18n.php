<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class FOSSBilling_i18n
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
        return $_COOKIE['BBLANG'] ?? self::getBrowserLocale() ?? $config['i18n']['locale'] ?? 'en_US';
    }

    /**
     * Retrieves the user's preferred language/locale based on the browser's Accept-Language header.
     *
     * @return string|null The user's preferred language/locale or null if not found.
     */
    private static function getBrowserLocale(): ?string
    {
        try {
            $detectedLocale = @Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $detectedLocale = @Locale::canonicalize($detectedLocale . '.utf8');
        } catch (Exception) {
            $detectedLocale = '';
        }

        if (empty($detectedLocale) || !$detectedLocale) {
            $detectedLocale = '';
        }

        $matchingLocale = Locale::lookup(self::getLocales(), $detectedLocale, false, null);

        /* The system was unable to match the browser locale to one of our local ones.
         * This is most likely because Locale::lookup will not match en with en_US. It will only match en_US with en.
         * As a workaround, let's see if one of the available locales starts with the detected locale.
         * If it does, return that.
         */
        if (empty($matchingLocale)) {
            foreach (self::getLocales() as $locale) {
                if (str_starts_with($locale, $detectedLocale)) {
                    return $locale;
                }
            }
        }

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
