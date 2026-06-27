<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace Tests\Datasets;

/**
 * Common country codes (ISO 3166-1 alpha-2).
 *
 * @return array<int, string>
 */
function countryCodes(): array
{
    return [
        'US', // United States
        'GB', // United Kingdom
        'CA', // Canada
        'AU', // Australia
        'DE', // Germany
        'FR', // France
        'ES', // Spain
        'IT', // Italy
        'NL', // Netherlands
        'JP', // Japan
        'CN', // China
        'IN', // India
        'BR', // Brazil
        'MX', // Mexico
        'RU', // Russia
        'ZA', // South Africa
    ];
}

/**
 * Common currency codes (ISO 4217).
 *
 * @return array<int, string>
 */
function currencyCodes(): array
{
    return [
        'USD', // US Dollar
        'EUR', // Euro
        'GBP', // British Pound
        'JPY', // Japanese Yen
        'CAD', // Canadian Dollar
        'AUD', // Australian Dollar
        'CHF', // Swiss Franc
        'CNY', // Chinese Yuan
        'INR', // Indian Rupee
        'BRL', // Brazilian Real
        'MXN', // Mexican Peso
        'RUB', // Russian Ruble
        'ZAR', // South African Rand
    ];
}

/**
 * Currency data with code and rate pairs.
 *
 * @return array<int, array{0: string, 1: float}>
 */
function currencyRates(): array
{
    return [
        ['USD', 1.0],
        ['EUR', 0.85],
        ['GBP', 0.73],
        ['JPY', 110.0],
        ['CAD', 1.25],
        ['AUD', 1.35],
        ['CHF', 0.92],
    ];
}
