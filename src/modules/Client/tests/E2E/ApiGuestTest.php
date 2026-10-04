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

test('can create and delete client', function (): void {
    $clientId = clientCreateClient();

    $result = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeTrue();
});

test('phone cc must be greater than zero', function (): void {
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'test_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
        'phone_cc' => -1,
    ]);

    expect($result->wasSuccessful())->toBeFalse();
});

test('phone cc maximum limit', function (): void {
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'test_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
        'phone_cc' => 1000,
    ]);

    expect($result->wasSuccessful())->toBeFalse();
});

test('phone number length validation', function (): void {
    $password = 'A1a' . bin2hex(random_bytes(6));
    $result = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'test_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
        'phone' => '123456789101123',
    ]);

    expect($result->wasSuccessful())->toBeFalse();
});

function clientCreateClient(): int
{
    $password = 'A1a' . bin2hex(random_bytes(6));
    $email = 'client_' . uniqid() . '@example.com';
    $result = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => $email,
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
        'phone_cc' => 1,
        'phone' => '(216) 245-2368',
    ]);
    expect($result->wasSuccessful())->toBeTrue();
    expect($result->getResult())->toBeTrue();

    // guest/client/create no longer returns the new client's id (doing so
    // would reopen the email-enumeration issue it was hardened against), so
    // look the account up by the email we just registered instead.
    $lookup = Tests\Helpers\ApiClient::request('admin/client/get', ['email' => $email]);
    expect($lookup->wasSuccessful())->toBeTrue();

    return (int) $lookup->getResult()['id'];
}
