<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

namespace FOSSBilling\Tests\Traits;

class Fixtures
{
    public static function createOrderData(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 1,
            'product_id' => 1,
            'quantity' => 1,
            'price' => 10.00,
            'currency' => 'USD',
            'period' => '1M',
            'status' => 'pending_setup',
        ], $overrides);
    }

    public static function createClientData(array $overrides = []): array
    {
        return array_merge([
            'email' => 'test@example.com',
            'first_name' => 'Test',
            'last_name' => 'User',
            'currency' => 'USD',
            'password' => 'secure_password_123',
        ], $overrides);
    }

    public static function createProductData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Product',
            'slug' => 'test-product',
            'type' => 'custom',
            'status' => 'enabled',
            'price' => 10.00,
            'description' => 'A test product for unit testing',
        ], $overrides);
    }

    public static function createInvoiceData(array $overrides = []): array
    {
        return array_merge([
            'client_id' => 1,
            'currency' => 'USD',
            'status' => 'unpaid',
            'total' => 10.00,
            'due_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ], $overrides);
    }

    public static function createTicketData(array $overrides = []): array
    {
        return array_merge([
            'subject' => 'Test Ticket',
            'message' => 'This is a test ticket message',
            'priority' => 'high',
            'status' => 'open',
        ], $overrides);
    }

    public static function createCurrencyData(array $overrides = []): array
    {
        return array_merge([
            'code' => 'USD',
            'title' => 'US Dollar',
            'symbol' => '$',
            'conversion_rate' => 1.0,
            'format' => '{{price}} {{symbol}}',
            'precision' => 2,
        ], $overrides);
    }

    public static function createAddressData(array $overrides = []): array
    {
        return array_merge([
            'street' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'TS',
            'zip' => '12345',
            'country' => 'US',
        ], $overrides);
    }

    public static function createPaymentGatewayData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Payment',
            'code' => 'test',
            'type' => 'form',
            'supported_currencies' => ['USD', 'EUR'],
            'is_recurring' => false,
        ], $overrides);
    }

    public static function createTaxRateData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Tax',
            'rate' => 10.0,
            'country' => 'US',
            'state' => null,
            'tax_inclusive' => false,
        ], $overrides);
    }
}
