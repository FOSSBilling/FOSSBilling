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

test('get available currencies', function () {
    $result = Tests\Helpers\ApiClient::request('admin/currency/get_pairs');
    expect($result->wasSuccessful())->toBeTrue();

    $list = $result->getResult();
    expect($list)->toHaveKey('USD');
    expect($list)->toHaveKey('EUR');
    expect($list)->toHaveKey('GBP');
    expect($list)->toHaveKey('JPY');
    expect($list)->toHaveKey('CHF');
    expect($list)->toHaveKey('AUD');
    expect($list)->toHaveKey('CAD');
    expect($list)->toHaveKey('NZD');
    expect($list)->toHaveKey('INR');
    expect($list)->toHaveKey('HKD');

    $this->assertArrayNotHasKey('XXX', $list);
    $this->assertArrayNotHasKey('XTS', $list);
    $this->assertArrayNotHasKey('VES', $list);
    $this->assertArrayNotHasKey('BZR', $list);
});
