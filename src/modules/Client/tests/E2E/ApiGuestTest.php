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

test('can create and delete client', function () {
    $clientId = clientCreateClient();

    $result = Tests\Helpers\ApiClient::request('admin/client/delete', ['id' => $clientId]);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeTrue();
});

test('phone ccmust be greater than zero', function () {
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

test('phone ccmaximum limit', function () {
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

test('phone number length validation', function () {
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
    $result = Tests\Helpers\ApiClient::request('guest/client/create', [
        'email' => 'client_' . uniqid() . '@example.com',
        'first_name' => 'Test',
        'password' => $password,
        'password_confirm' => $password,
        'phone_cc' => 1,
        'phone' => '(216) 245-2368',
    ]);
    expect($result->wasSuccessful())->toBeTrue($result);
    expect($result->getResult())->toBeInt();

    return (int) $result->getResult();
}
