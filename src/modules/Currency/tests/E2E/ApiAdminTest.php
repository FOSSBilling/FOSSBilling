<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Tests\Helpers\ApiClient;

if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

test('gets currency pairs', function (): void {
    $result = ApiClient::request('admin/currency/get_pairs');
    expect($result->wasSuccessful())->toBeTrue();

    $list = $result->getResult();

    foreach (['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'AUD', 'CAD', 'NZD', 'INR', 'HKD'] as $currencyCode) {
        expect($list)->toHaveKey($currencyCode);
    }

    foreach (['XXX', 'XTS'] as $currencyCode) {
        expect($list)->not->toHaveKey($currencyCode);
    }

    expect($list['USD'])->toMatch('/^USD \(.*\)$/');
});

test('currency defaults', function (): void {
    $result = ApiClient::request('admin/currency/get_default');
    expect($result->wasSuccessful())->toBeTrue();

    $defaults = $result->getResult();
    expect($defaults['code'])->toEqual('USD');
    expect($defaults['name'])->toEqual('US Dollar');
    expect($defaults['symbol'])->toEqual('$');
    expect($defaults)->toHaveKey('conversion_rate');
    expect($defaults['default'])->toBeTrue();
});
