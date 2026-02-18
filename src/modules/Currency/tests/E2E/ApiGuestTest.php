<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

test('currency defaults', function () {
    $result = Tests\Helpers\ApiClient::request('guest/currency/get_currency_defaults', ['code' => 'USD']);
    expect($result->wasSuccessful())->toBeTrue();

    $defaults = $result->getResult();
    expect('USD')->toEqual($defaults['code']);
    expect('US Dollar')->toEqual($defaults['name']);
    expect('$')->toEqual($defaults['symbol']);
    expect(2)->toEqual($defaults['minorUnits']);
});
