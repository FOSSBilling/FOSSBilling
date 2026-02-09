<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

test('cron guest behavior', function () {
    \Tests\Helpers\ApiClient::request('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => false]);
    $result = \Tests\Helpers\ApiClient::request('guest/cron/run');
    expect($result->wasSuccessful())->toBeFalse();

    \Tests\Helpers\ApiClient::request('admin/extension/config_save', ['ext' => 'mod_cron', 'guest_cron' => true]);
    \Tests\Helpers\ApiClient::request('admin/system/update_params', ['last_cron_exec' => date('Y-m-d H:i:s', time() - 6400)]);
    $result = \Tests\Helpers\ApiClient::request('guest/cron/run');
    expect($result->wasSuccessful())->toBeTrue($result);

    $result = \Tests\Helpers\ApiClient::request('guest/cron/run');
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeFalse();
});
