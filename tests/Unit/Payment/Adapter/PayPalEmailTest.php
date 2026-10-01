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

function buildPayPalEmailAdapter(string $configuredEmail, string $postbackResponse, ?array &$capturedRequestBody = null, ?string &$capturedRawRequestBody = null): Payment_Adapter_PayPalEmail
{
    $response = Mockery::mock();
    $response->shouldReceive('getContent')->andReturn($postbackResponse);

    $httpClient = Mockery::mock();
    $httpClient->shouldReceive('withOptions')->andReturnSelf();
    $httpClient->shouldReceive('request')
        ->with('POST', Mockery::any(), Mockery::on(function (array $options) use (&$capturedRequestBody, &$capturedRawRequestBody): bool {
            $capturedRawRequestBody = (string) ($options['body'] ?? '');
            parse_str($capturedRawRequestBody, $capturedRequestBody);

            return true;
        }))
        ->andReturn($response);

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

function callValidateCurrency(Payment_Adapter_PayPalEmail $adapter, mixed $received, mixed $expected): void
{
    $reflection = new ReflectionClass($adapter);
    $reflection->getMethod('validateCurrency')->invokeArgs($adapter, [$received, $expected]);
}

describe('validateCurrency', function (): void {
    test('accepts a currency matching the invoice case-insensitively', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        expect(fn () => callValidateCurrency($adapter, 'usd', 'USD'))->not->toThrow(Payment_Exception::class);
    });

    test('rejects a currency that does not match the invoice', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        expect(fn () => callValidateCurrency($adapter, 'MXN', 'USD'))
            ->toThrow(Payment_Exception::class, 'PayPal payment currency MXN does not match invoice currency USD');
    });

    test('rejects a payment with no currency', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        expect(fn () => callValidateCurrency($adapter, null, 'USD'))
            ->toThrow(Payment_Exception::class, 'PayPal payment is missing currency details');
    });

    test('rejects a payment when the invoice currency is unknown', function (): void {
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED');

        expect(fn () => callValidateCurrency($adapter, 'USD', null))
            ->toThrow(Payment_Exception::class, 'PayPal payment is missing currency details');
    });
});

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

    test('sends field values back to PayPal unmodified, backslashes included', function (): void {
        $captured = [];
        $capturedRaw = null;
        $adapter = buildPayPalEmailAdapter('merchant@example.com', 'VERIFIED', $captured, $capturedRaw);

        $addressStreet = 'C:\\Users\\Test 1\\2 Foo St';
        $result = callIsIpnValid($adapter, http_build_query([
            'receiver_email' => 'merchant@example.com',
            'mc_gross' => '10.00',
            'address_street' => $addressStreet,
        ]));

        expect($result)->toBeTrue();
        expect($captured['address_street'])->toBe($addressStreet);
        // Assert on the raw wire body too, not just its parse_str()-decoded form,
        // so a bug in the percent-encoding itself (not just the decoded value)
        // would still be caught.
        expect($capturedRaw)->toContain('address_street=' . urlencode($addressStreet));
    });
});

function paypalProcessAdapter(object $di): Payment_Adapter_PayPalEmail
{
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
    $adapter->setDi($di);

    return $adapter;
}

function paypalDbMocks(?callable $findOne = null): Mockery\MockInterface
{
    $dbMock = Mockery::mock('\Box_Database');
    $dbMock->shouldReceive('findOne')->byDefault()->andReturnUsing(
        $findOne ?? static fn (): mixed => null
    );
    $dbMock->shouldReceive('getAll')->byDefault()->andReturn([]);

    return $dbMock;
}

function paypalBean(string $model, array $properties): object
{
    $bean = new $model();
    $bean->loadBean(new Tests\Helpers\DummyBean());
    foreach ($properties as $name => $value) {
        $bean->$name = $value;
    }

    return $bean;
}

/**
 * Build the query-string portion of a callback URL as issued post-fix:
 * invoice id plus its binding signature for the given gateway.
 */
function signedPayPalGet(int $invoiceId, int $gatewayId = 1, array $extra = []): array
{
    return array_merge(
        ['invoice_id' => $invoiceId, 'sig' => FOSSBilling\Tools::signCallbackParams($gatewayId, $invoiceId)],
        $extra
    );
}

describe('PayPal callback invoice binding', function (): void {
    test('rejects a callback whose invoice id was swapped after signing', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        // Signature was issued for invoice 16; the callback URL now names 99.
        $sig = FOSSBilling\Tools::signCallbackParams(2, 16);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 99, 'sig' => $sig],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('rejects a callback with no signature', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 16],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('rejects a signature issued for a different gateway', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 16, 'sig' => FOSSBilling\Tools::signCallbackParams(7, 16)],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('binds an unbound transaction to the verified invoice', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'unknown_event', 'payment_status' => 'Unknown'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $bound = array_values(array_filter($updates, fn (array $u): bool => isset($u['invoice_id'])));
        expect($bound)->toHaveCount(1)
            ->and((int) $bound[0]['invoice_id'])->toBe(16);
    });

    test('accepts an unsigned callback for a stored subscription on the same invoice', function (): void {
        $updates = [];
        $stored = paypalBean(Model_Subscription::class, ['rel_type' => 'invoice', 'rel_id' => 16]);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $di = container();
        $di['db'] = paypalDbMocks(static fn (string $table): mixed => $table === 'Subscription' ? $stored : null);
        $di['logger'] = new Tests\Helpers\TestLogger();

        // Legacy recurring profile: callback URL predates signing, so no sig.
        // The subscr_id is only used for the binding fallback here; the
        // unknown txn type keeps the flow from touching subscriptions.
        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'unknown_event', 'subscr_id' => 'I-LEGACY'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('rejects an unsigned callback naming a different invoice for a stored subscription', function (): void {
        $stored = paypalBean(Model_Subscription::class, ['rel_type' => 'invoice', 'rel_id' => 16]);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 99, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks(static fn (string $table): mixed => $table === 'Subscription' ? $stored : null);
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'unknown_event', 'subscr_id' => 'I-LEGACY'],
            'get' => ['invoice_id' => 99],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('rejects an unsigned callback for an unknown subscription', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_cancel', 'subscr_id' => 'I-UNKNOWN'],
            'get' => ['invoice_id' => 16],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('still requires an invoice when none is supplied', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => [],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal transaction is not associated with an invoice');

    test('accepts an unsigned refund continuing a pre-upgrade payment', function (): void {
        $refunded = [];
        $earlier = paypalBean(Model_Transaction::class, ['invoice_id' => 16]);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->andReturnTrue();
        $apiAdmin->shouldReceive('invoice_refund')->once()->withArgs(function (array $data) use (&$refunded): bool {
            $refunded[] = $data;

            return true;
        });

        $di = container();
        $di['db'] = paypalDbMocks(static fn (string $table, string $where, array $bindings): mixed => $table === 'Transaction' && ($bindings[':txn'] ?? null) === 'TXN-ORIG' ? $earlier : null);
        $di['logger'] = new Tests\Helpers\TestLogger();

        // Refund IPN for a pre-upgrade payment: new txn id, unsigned URL,
        // but parent_txn_id names the original recorded payment.
        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Refunded',
                'txn_id' => 'REFUND-9',
                'parent_txn_id' => 'TXN-ORIG',
                'mc_gross' => '-120.00',
                'mc_currency' => 'USD',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($refunded)->toHaveCount(1)
            ->and($refunded[0]['id'])->toBe(16);
    });

    test('accepts an unsigned delayed completion with a recorded pending payment', function (): void {
        $funds = [];
        $earlier = paypalBean(Model_Transaction::class, ['invoice_id' => 16]);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'web_accept', 'txn_id' => 'TXN-E', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->andReturnTrue();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once()->withArgs(function (array $data) use (&$funds): bool {
            $funds[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceBean = paypalBean(Model_Invoice::class, [
            'id' => 16,
            'nr' => '00042',
            'status' => Model_Invoice::STATUS_UNPAID,
            'approved' => true,
        ]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $dbMock = paypalDbMocks(static fn (string $table): mixed => $table === 'Transaction' ? $earlier : null);
        $dbMock->shouldReceive('load')->with('Invoice', 16)->andReturn($invoiceBean);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        // eCheck-style delayed completion: unsigned pre-upgrade URL, but the
        // same PayPal transaction was already recorded for this invoice.
        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-E',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($funds)->toHaveCount(1);
    });

    test('rejects an unsigned callback whose earlier payment belongs to another invoice', function (): void {
        $earlier = paypalBean(Model_Transaction::class, ['invoice_id' => 16]);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 99, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['db'] = paypalDbMocks(static fn (string $table): mixed => $table === 'Transaction' ? $earlier : null);
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed', 'txn_id' => 'TXN-X'],
            'get' => ['invoice_id' => 99],
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal callback signature is invalid');

    test('rejects a completed payment whose echoed item_number names a different invoice', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'web_accept', 'txn_id' => 'TXN-9', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->andReturnTrue();
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        $invoiceBean = paypalBean(Model_Invoice::class, ['id' => 16, 'nr' => '00042']);

        $dbMock = paypalDbMocks();
        $dbMock->shouldReceive('load')->with('Invoice', 16)->andReturn($invoiceBean);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-9',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
                'item_number' => '99999',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);
    })->throws(Payment_Exception::class, 'PayPal item_number does not match invoice 16');

    test('accepts a completed payment whose echoed references match the invoice', function (): void {
        $funds = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'web_accept', 'txn_id' => 'TXN-9', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->andReturnTrue();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once()->withArgs(function (array $data) use (&$funds): bool {
            $funds[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceBean = paypalBean(Model_Invoice::class, [
            'id' => 16,
            'nr' => '00042',
            'status' => Model_Invoice::STATUS_UNPAID,
            'approved' => true,
        ]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $dbMock = paypalDbMocks();
        $dbMock->shouldReceive('load')->with('Invoice', 16)->andReturn($invoiceBean);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-9',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
                'item_number' => '00042',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        expect($funds)->toHaveCount(1);
    });

    test('processes a completed payment without re-claiming the row', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'web_accept', 'txn_id' => 'TXN-1', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceBean = paypalBean(Model_Invoice::class, [
            'id' => 16,
            'nr' => '00042',
            'status' => Model_Invoice::STATUS_UNPAID,
            'approved' => true,
        ]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $dbMock = paypalDbMocks();
        $dbMock->shouldReceive('load')->with('Invoice', 16)->andReturn($invoiceBean);
        $di = container();
        $di['db'] = $dbMock;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-1',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
                'item_number' => '00042',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('getInvoiceId resolves a signed callback and rejects a tampered one', function (): void {
        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false, 'gateway_id' => 2]);
        $adapter->setDi($di);

        expect($adapter->getInvoiceId(['get' => signedPayPalGet(16, 2)]))->toBe(16);
        expect($adapter->getInvoiceId(['get' => []]))->toBeNull();
        expect(fn (): mixed => $adapter->getInvoiceId(['get' => ['invoice_id' => 99, 'sig' => FOSSBilling\Tools::signCallbackParams(2, 16)]]))
            ->toThrow(Payment_Exception::class, 'PayPal callback signature is invalid');
    });

    test('payment forms sign the callback URL for the paying invoice', function (): void {
        $di = container();
        $di['db'] = paypalDbMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        $adapter = new Payment_Adapter_PayPalEmail([
            'email' => 'merchant@example.com',
            'test_mode' => false,
            'gateway_id' => 2,
            'thankyou_url' => 'https://example.com/thank-you',
            'cancel_url' => 'https://example.com/cancel',
            'notify_url' => 'https://example.com/ipn.php?gateway_id=2&invoice_id=16',
        ]);
        $adapter->setDi($di);

        $invoice = [
            'id' => 16,
            'nr' => '00042',
            'serie' => 'FB',
            'currency' => 'USD',
            'subtotal' => '100.00',
            'tax' => '20.00',
            'lines' => [['title' => 'Test product']],
        ];

        $fields = $adapter->getOneTimePaymentFields($invoice);
        parse_str((string) parse_url($fields['notify_url'], PHP_URL_QUERY), $query);

        expect((int) ($query['invoice_id'] ?? 0))->toBe(16)
            ->and(FOSSBilling\Tools::verifyCallbackSignature(2, 16, $query['sig'] ?? null))->toBeTrue();
    });
});
