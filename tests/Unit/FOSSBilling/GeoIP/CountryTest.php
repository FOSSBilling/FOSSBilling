<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use FOSSBilling\GeoIP\Country;
use FOSSBilling\GeoIP\IncompleteRecord;
use PrinsFrank\Standards\Language\LanguageAlpha2;

test('unknown ISO codes surface as incomplete records', function (): void {
    new Country(['iso_code' => 'EQ'], LanguageAlpha2::English);
})->throws(IncompleteRecord::class);

test('non-string ISO codes surface as incomplete records', function (): void {
    new Country(['iso_code' => null], LanguageAlpha2::English);
})->throws(IncompleteRecord::class);

test('known ISO codes resolve', function (): void {
    $country = new Country(['iso_code' => 'US'], LanguageAlpha2::English);

    expect($country->name)->not->toBeEmpty()
        ->and($country->currencies)->not->toBeEmpty();
});
