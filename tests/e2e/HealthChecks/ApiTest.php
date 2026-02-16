<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use function Tests\Helpers\assertApiSuccess;

// Skip E2E tests if environment is not configured
if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

$testUrls = [
    '' => 200,
    'order' => 200,
    'news' => 200,
    'privacy-policy' => 200,
    'sitemap.xml' => 200,
    'contact-us' => 200,
    'login' => 200,
    'signup' => 200,
    'password-reset' => 200,
];

test('urls return ok status', function () use ($testUrls) {
    $baseUrl = getenv('APP_URL') ?: 'http://localhost';
    $baseUrl = rtrim($baseUrl, '/');

    foreach ($testUrls as $url => $expectedCode) {
        $ch = curl_init($baseUrl . '/' . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        expect($responseCode)->toEqual($expectedCode, "URL '{$url}' should return {$expectedCode}");
    }
});

test('api is functional', function () {
    $result = Tests\Helpers\ApiClient::request('guest/system/company');
    assertApiSuccess($result);
    expect($result->getResult())->toBeArray();
});

test('system is up to date', function () {
    $result = Tests\Helpers\ApiClient::request('admin/system/is_behind_on_patches');
    assertApiSuccess($result);
    expect($result->getResult())->toBeFalse();
});
