<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Payment_Adapter_PayPalEmail;

use function Tests\Helpers\container;

function buildPayPalEmailAdapter(string $configuredEmail, string $postbackResponse): Payment_Adapter_PayPalEmail
{
    $response = Mockery::mock();
    $response->shouldReceive('getContent')->andReturn($postbackResponse);

    $httpClient = Mockery::mock();
    $httpClient->shouldReceive('withOptions')->andReturnSelf();
    $httpClient->shouldReceive('request')->with('POST', Mockery::any(), Mockery::any())->andReturn($response);

    $adapter = new Payment_Adapter_PayPalEmail(['email' => $configuredEmail, 'test_mode' => false]);
    $di = container();
    $di['http_client'] = $httpClient;
    $adapter->setDi($di);

    return $adapter;
}

function callIsIpnValid(Payment_Adapter_PayPalEmail $adapter, string $rawPostBody): bool
{
    $reflection = new ReflectionClass($adapter);

    return (bool) $reflection->getMethod('_isIpnValid')->invokeArgs($adapter, [['http_raw_post_data' => $rawPostBody]]);
}

describe('_isIpnValid receiver verification', function (): void {
    test('accepts a payment made to the configured merchant account', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'merchant@example.com',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeTrue();
    });

    test('accepts the business field when receiver_email is absent', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'business' => 'merchant@example.com',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeTrue();
    });

    test('accepts the business field when receiver_email is a different confirmed account email', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'primary@example.com',
            'business' => 'merchant@example.com',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeTrue();
    });

    test('rejects a payment made to a different account', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'attacker@evil.example',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeFalse();
    });

    test('matches the configured address case-insensitively', function (): void {
        $adapter = buildPayPalEmailAdapter('Merchant@Example.COM', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'merchant@example.com',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeTrue();
    });

    test('matches the configured address after trimming surrounding whitespace', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => '  merchant@example.com  ',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeTrue();
    });

    test('rejects a notification with no payee field', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        $result = callIsIpnValid($adapter, http_build_query([
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeFalse();
    });

    test('rejects a notification whose postback is not VERIFIED', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'INVALID');

        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'merchant@example.com',
            'mc_gross' => '10.00',
        ]));

        expect($result)->toBeFalse();
    });
});
