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

test('can activate extension', function () {
    $result = Tests\Helpers\ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'massmailer']);
    expect($result->wasSuccessful())->toBeTrue($result);
});

test('can deactivate extension', function () {
    $result = Tests\Helpers\ApiClient::request('admin/extension/deactivate', ['type' => 'mod', 'id' => 'massmailer']);
    expect($result->wasSuccessful())->toBeTrue($result);
});

test('language management', function () {
    $result = Tests\Helpers\ApiClient::request('admin/extension/languages');
    expect($result->wasSuccessful())->toBeTrue($result);
    $this->assertNotCount(0, $result->getResult());

    $result = Tests\Helpers\ApiClient::request('admin/extension/languages', ['disabled' => true]);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toHaveCount(0);

    $result = Tests\Helpers\ApiClient::request('admin/extension/toggle_language', ['locale_id' => 'en_US']);
    expect($result->wasSuccessful())->toBeTrue($result);

    $result = Tests\Helpers\ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false]);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toContain('en_US');

    $result = Tests\Helpers\ApiClient::request('admin/extension/languages');
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->not->toContain('en_US');

    $result = Tests\Helpers\ApiClient::request('admin/extension/toggle_language', ['locale_id' => 'en_US']);
    expect($result->wasSuccessful())->toBeTrue($result);
});

test('language completion', function () {
    $locales = Tests\Helpers\ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult();
    foreach ($locales as $locale) {
        Tests\Helpers\ApiClient::request('admin/extension/toggle_language', ['locale_id' => $locale]);
    }

    expect(Tests\Helpers\ApiClient::request('admin/extension/languages', ['disabled' => true, 'details' => false])->getResult())->toBeEmpty();

    $locales = Tests\Helpers\ApiClient::request('admin/extension/languages', ['details' => false])->getResult();
    foreach ($locales as $locale) {
        $completionResult = Tests\Helpers\ApiClient::request('admin/extension/locale_completion', ['locale_id' => $locale]);
        if ($locale === 'en_US') {
            expect($completionResult->getResult())->toEqual(100);
        } else {
            expect($completionResult->getResult())->toBeGreaterThanOrEqual(25);
        }
    }
});
