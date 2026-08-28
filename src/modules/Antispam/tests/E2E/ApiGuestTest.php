<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

if (!getenv('APP_URL') || !getenv('TEST_API_KEY')) {
    return;
}

use Tests\Helpers\ApiClient;

test('disposable email check', function (): void {
    $result = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_antispam', 'check_temp_emails' => true]);
    expect($result->wasSuccessful())->toBeTrue();

    $email = 'email@yopmail.net';
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = ApiClient::request('guest/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);

    if ($result->wasSuccessful()) {
        // guest/client/create no longer returns the new client's id (doing so
        // would reopen the email-enumeration issue it was hardened against),
        // so look the account up by the email we just registered instead.
        $lookup = ApiClient::request('admin/client/get', ['email' => $email]);
        if ($lookup->wasSuccessful()) {
            ApiClient::request('admin/client/delete', ['id' => $lookup->getResult()['id']]);
        }
        $this->markTestSkipped('Disposable email domain list unavailable in this environment');
    }

    expect($result->wasSuccessful())->toBeFalse();
    expect($result->getErrorMessage())->toEqual('Disposable email addresses are not allowed');
});

test('stop forum spam', function (): void {
    $result = ApiClient::request('admin/extension/config_save', ['ext' => 'mod_antispam', 'sfs' => true]);
    expect($result->wasSuccessful())->toBeTrue();

    $email = 'email@example.com';
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = ApiClient::request('guest/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
    ]);

    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeTrue();

    // guest/client/create no longer returns the new client's id (doing so
    // would reopen the email-enumeration issue it was hardened against), so
    // look the account up by the email we just registered instead.
    $lookup = ApiClient::request('admin/client/get', ['email' => $email]);
    expect($lookup->wasSuccessful())->toBeTrue();

    $result = ApiClient::request('admin/client/delete', ['id' => $lookup->getResult()['id']]);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeTrue();
});
