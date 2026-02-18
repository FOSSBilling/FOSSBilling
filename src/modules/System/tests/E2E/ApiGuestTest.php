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

test('template exists', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/template_exists', ['file' => 'layout_default.html.twig']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();
});

test('template does not exist', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/template_exists', ['file' => 'thisfiledoesnotexist.txt']);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeFalse();
});

test('periods', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/periods');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});

test('countries', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/countries');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});

test('countries eunion', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/countries_eunion');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});

test('states', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/states');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});

test('phone codes', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/phone_codes');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeArray();
});
