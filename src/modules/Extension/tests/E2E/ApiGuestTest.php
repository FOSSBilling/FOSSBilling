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

test('theme', function () {
    $result = \Tests\Helpers\ApiClient::request('guest/extension/theme');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
    expect($result->getResult())->toHaveKey('name');
    expect($result->getResult())->toHaveKey('version');
    expect($result->getResult()['author'])->toEqual('FOSSBilling');
});

test('settings', function () {
    $result = \Tests\Helpers\ApiClient::request('guest/extension/settings', ['ext' => 'index']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});

test('settings missing ext', function () {
    $result = \Tests\Helpers\ApiClient::request('guest/extension/settings', ['ext']);
    expect($result->wasSuccessful())->toBeFalse($result);
    expect($result->getErrorMessage())->toEqual('Parameter ext is missing');
});

test('extension is active', function () {
    $result = \Tests\Helpers\ApiClient::request('guest/extension/is_on', ['mod' => 'index']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();
});

test('extension is not active', function () {
    $result = \Tests\Helpers\ApiClient::request('guest/extension/is_on', ['mod' => 'serviceapikey']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeFalse();
});
