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

test('settings', function (): void {
    $result = Tests\Helpers\ApiClient::request('guest/extension/settings', ['ext' => 'index']);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeArray();
});

test('settings missing ext', function (): void {
    $result = Tests\Helpers\ApiClient::request('guest/extension/settings', ['ext']);
    expect($result->wasSuccessful())->toBeFalse();
    expect($result->getErrorMessage())->toEqual('Parameter ext is missing');
});

test('extension is active', function (): void {
    $result = Tests\Helpers\ApiClient::request('guest/extension/is_on', ['mod' => 'index']);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeTrue();
});

test('extension is not active', function (): void {
    $result = Tests\Helpers\ApiClient::request('guest/extension/is_on', ['mod' => 'serviceapikey']);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeFalse();
});
