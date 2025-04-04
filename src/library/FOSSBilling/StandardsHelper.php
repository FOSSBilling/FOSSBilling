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

use PrinsFrank\Standards\Language\LanguageAlpha2;

class StandardsHelper
{
    public static function getLanguageObject(bool $exactMatch = false, ?string $locale = null): LanguageAlpha2
    {
        // Automatically select the locale that's currently in use if not otherwise specified.
        if ($locale === null) {
            $locale = i18n::getActiveLocale();
        }

        $original = $locale;

        // Handle our locale tags and turn them into the closes Alpha 2 match.
        if (!$exactMatch && str_contains($locale, '_')) {
            $locale = explode('_', $locale)[0];
        } elseif (str_contains($locale, '_')) {
            throw new InformationException('Exact matches cannot be provided unless you provide an Alpha2 language code.');
        }

        if (strlen($locale) === 2) {
            return LanguageAlpha2::from($locale);
        }

        throw new \ValueError("No matching Alpha2 language found for {$original}");
    }
}
