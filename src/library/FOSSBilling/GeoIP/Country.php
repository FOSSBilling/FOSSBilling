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

namespace FOSSBilling\GeoIP;

use PrinsFrank\Standards\Country\CountryAlpha2;
use PrinsFrank\Standards\Language\LanguageAlpha2;

class Country implements \JsonSerializable
{
    /**
     * Country name, translated based on the LanguageAlpha2 provided.
     */
    public readonly string $name;
    /**
     * A flag emoji for the country. Does not display correctly on Windows.
     */
    public readonly string $flag;
    /**
     * An array of Alpha3 currency codes for the given country.
     *
     * @var string[]
     */
    public readonly array $currencies;

    public function __construct(array $countyRecord, LanguageAlpha2 $language)
    {
        if (!array_key_exists('iso_code', $countyRecord)) {
            throw new IncompleteRecord('The is no country information for the provided IP address');
        }

        // Instance the language
        $country = CountryAlpha2::from($countyRecord['iso_code']);
        $this->name = $country->getNameInLanguage($language) ?? 'Unknown';
        $this->flag = $country->getFlagEmoji() ?? '';

        foreach ($country->getCurrenciesAlpha3() as $currency) {
            $currencies[] = $currency->value;
        }

        $this->currencies = $currencies ?? [];
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'flag' => $this->flag,
            'currencies' => $this->currencies,
        ];
    }
}
