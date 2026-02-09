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

test('disposable email check', function () {
    $result = \Tests\Helpers\ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
    expect($result->wasSuccessful())->toBeTrue($result);

    $result = \Tests\Helpers\ApiClient::request('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'check_temp_emails' => true]);
    expect($result->wasSuccessful())->toBeTrue($result);

    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = \Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'email@yopmail.net',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);

    expect($result->wasSuccessful())->toBeFalse();
    expect($result->getErrorMessage())->toEqual('Disposable email addresses are not allowed');

    if ($result->wasSuccessful()) {
        $id = intval($result->getResult());
        \Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $id]);
    }
});

test('stop forum spam', function () {
    $result = \Tests\Helpers\ApiClient::request('admin/extension/activate', ['type' => 'mod', 'id' => 'spamchecker']);
    expect($result->wasSuccessful())->toBeTrue($result);

    $result = \Tests\Helpers\ApiClient::request('admin/extension/config_save', ['ext' => 'mod_spamchecker', 'sfs' => true]);
    expect($result->wasSuccessful())->toBeTrue($result);

    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = \Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'email@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);

    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeNumeric();

    $id = intval($result->getResult());

    $result = \Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $id]);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();
});
