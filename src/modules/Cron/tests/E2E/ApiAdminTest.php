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

test('cron execution', function () {
    $result = Tests\Helpers\ApiClient::request('admin/cron/info');
    expect($result->wasSuccessful())->toBeTrue($result);

    if (!empty($result->getResult()['last_cron_exec'])) {
        $firstDate = new DateTime($result->getResult()['last_cron_exec']);
    } else {
        $firstDate = new DateTime();
        $firstDate->modify('-1 hour');
    }

    $result = Tests\Helpers\ApiClient::request('admin/cron/run');
    expect($result->wasSuccessful())->toBeTrue($result);

    sleep(2);

    $result = Tests\Helpers\ApiClient::request('admin/cron/info');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult()['last_cron_exec'])->not->toBeEmpty();

    $newDate = new DateTime($result->getResult()['last_cron_exec']);
    expect($newDate)->toBeGreaterThan($firstDate);
});
