<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

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

    test('logs unknown PayPal transactions through the application logger', function (): void {
        $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['logger'] = $logger;
        $adapter->setDi($di);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')
            ->once()
            ->with(['id' => 42])
            ->andReturn([
                'invoice_id' => 7,
                'type' => 'unknown_event',
                'txn_id' => 'paypal-transaction-42',
                'txn_status' => 'Unknown',
                'amount' => '10.00',
                'currency' => 'USD',
            ]);
        $apiAdmin->shouldReceive('invoice_get')
            ->once()
            ->with(['id' => 7])
            ->andReturn(['client' => ['id' => 9]]);
        $apiAdmin->shouldReceive('invoice_transaction_update')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => $data['id'] === 42 && $data['status'] === 'processed'));

        $adapter->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'unknown_event',
                'payment_status' => 'Unknown',
            ],
            'get' => [],
        ], 1);

        expect($logger->calls)->toContain([
            'method' => 'error',
            'params' => ['Unknown PayPal transaction 42'],
        ]);
    });
});

function paypalProcessAdapter(object $di): Payment_Adapter_PayPalEmail
{
    $adapter = new Payment_Adapter_PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
    $adapter->setDi($di);

    return $adapter;
}

function paypalEmMocks(?object $invoiceModel = null, ?object $existingSubscription = null): Mockery\MockInterface
{
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->byDefault()->andReturn($invoiceModel);
    $subRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subRepo->shouldReceive('findOneBy')->byDefault()->andReturn($existingSubscription);
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->byDefault()->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Invoice\Entity\Invoice::class => $invoiceRepo,
        Box\Mod\Invoice\Entity\Subscription::class => $subRepo,
        default => Mockery::mock(Doctrine\ORM\EntityRepository::class)->shouldIgnoreMissing(),
    });
    $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);

    return $em;
}

describe('PayPal subscription IPN handling', function (): void {
    test('subscr_signup stores the subscription against the order invoice', function (): void {
        $updates = [];
        $created = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_subscription_create')->once()->withArgs(function (array $data) use (&$created): bool {
            $created[] = $data;

            return true;
        })->andReturn(7);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks();
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_signup',
                'subscr_id' => 'I-ABC123',
                'mc_currency' => 'USD',
                'period3' => '1 Y',
                'amount3' => '120.00',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($created)->toHaveCount(1)
            ->and($created[0]['sid'])->toBe('I-ABC123')
            ->and($created[0]['period'])->toBe('1Y')
            ->and($created[0]['rel_id'])->toBe(16);

        $transactionUpdates = array_values(array_filter($updates, fn (array $u): bool => ($u['s_id'] ?? null) === 'I-ABC123'));
        expect($transactionUpdates)->toHaveCount(1)
            ->and($transactionUpdates[0]['s_period'])->toBe('1Y');

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);

        $logged = array_column(array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'info'), 'params');
        expect($logged)->not->toBeEmpty();
    });

    test('repeated subscr_signup does not create a duplicate subscription', function (): void {
        $updates = [];
        $existing = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => 'subscr_signup', 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => 'USD',
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldNotReceive('invoice_subscription_create');
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks(null, $existing);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_signup',
                'subscr_id' => 'I-ABC123',
                'mc_currency' => 'USD',
                'period3' => '1 Y',
                'amount3' => '120.00',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('initial subscr_payment pays the original invoice without generating a renewal', function (): void {
        $updates = [];
        $funds = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'subscr_payment', 'txn_id' => 'TXN-1', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldReceive('invoice_transaction_claim_for_processing')->once()->with(['id' => 42])->andReturn(true);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('client_balance_add_funds')->once()->withArgs(function (array $data) use (&$funds): bool {
            $funds[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);
        $invoiceService->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment');

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isApproved')->byDefault()->andReturn(true);

        $em = paypalEmMocks($invoiceModel);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-1',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
                'subscr_id' => 'I-ABC123',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($funds)->toHaveCount(1)
            ->and((float) $funds[0]['amount'])->toBe(120.00);
        $reassigned = array_filter($updates, fn (array $u): bool => isset($u['invoice_id']) && (int) $u['invoice_id'] !== 16);
        expect($reassigned)->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('renewal subscr_payment generates a renewal invoice once the original is paid', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'subscr_payment', 'txn_id' => 'TXN-2', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldReceive('invoice_transaction_claim_for_processing')->once()->with(['id' => 42])->andReturn(true);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 99]);

        $paidInvoice = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $paidInvoice->shouldReceive('getId')->byDefault()->andReturn(16);
        $paidInvoice->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_PAID);
        $renewal = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $renewal->shouldReceive('getId')->byDefault()->andReturn(99);
        $renewal->shouldReceive('isApproved')->byDefault()->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('generateRenewalInvoiceForSubscriptionPayment')->once()->with('I-ABC123', 9)->andReturn($renewal);
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $em = paypalEmMocks($paidInvoice);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-2',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
                'subscr_id' => 'I-ABC123',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        $reassigned = array_values(array_filter($updates, fn (array $u): bool => isset($u['invoice_id']) && (int) $u['invoice_id'] === 99));
        expect($reassigned)->toHaveCount(1);
    });

    test('non-completed payments keep their error instead of being marked processed', function (): void {
        $updates = [];
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
        $apiAdmin->shouldNotReceive('client_balance_add_funds');
        $apiAdmin->shouldNotReceive('invoice_pay_with_credits');

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Pending'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $received = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'received'));
        expect($received)->toHaveCount(1)
            ->and($received[0]['error'])->toContain('Pending');
        $processed = array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed');
        expect($processed)->toBeEmpty();
    });

    test('subscr_signup without a subscription id throws instead of silently succeeding', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault();
        $apiAdmin->shouldNotReceive('invoice_subscription_create');

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        expect(fn (): mixed => paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_signup', 'mc_currency' => 'USD'],
            'get' => ['invoice_id' => 16],
        ], 2))->toThrow(Payment_Exception::class);
    });

    test('cancellation for an unknown subscription logs a warning instead of throwing', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldNotReceive('invoice_subscription_get');
        $apiAdmin->shouldNotReceive('invoice_subscription_update');
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks();
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_cancel', 'subscr_id' => 'I-UNKNOWN'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('cancellation updates the stored subscription', function (): void {
        $updates = [];
        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getId')->byDefault()->andReturn(7);
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldNotReceive('invoice_subscription_get');
        $apiAdmin->shouldReceive('invoice_subscription_update')->once()->with(['id' => 7, 'status' => 'canceled'])->andReturn(true);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks(null, $stored);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_cancel', 'subscr_id' => 'I-ABC123'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('completed payment missing transaction details throws before claiming', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received',
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault();
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        expect(fn (): mixed => paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 16],
        ], 2))->toThrow(Payment_Exception::class, 'PayPal payment is missing transaction details');
    });

    test('completed subscription payment missing the subscription id throws before claiming', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received',
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault();
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        expect(fn (): mixed => paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-1',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
            ],
            'get' => ['invoice_id' => 16],
        ], 2))->toThrow(Payment_Exception::class, 'PayPal subscription payment is missing the subscription ID');
    });

    test('contended transaction claims are logged instead of silently skipped', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received',
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault();
        $apiAdmin->shouldReceive('invoice_transaction_claim_for_processing')->once()->with(['id' => 42])->andReturn(false);
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        $em = paypalEmMocks();
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-1',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
    });

    test('recurring_payment from the newer flow pays the original invoice', function (): void {
        $updates = [];
        $funds = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'recurring_payment', 'txn_id' => 'TXN-R1', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldReceive('invoice_transaction_claim_for_processing')->once()->with(['id' => 42])->andReturn(true);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('client_balance_add_funds')->once()->withArgs(function (array $data) use (&$funds): bool {
            $funds[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isApproved')->byDefault()->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);
        $invoiceService->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment');

        $em = paypalEmMocks($invoiceModel);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'recurring_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-R1',
                'recurring_payment_id' => 'I-PROFILE1',
                'amount' => '120.00',
                'mc_currency' => 'USD',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($funds)->toHaveCount(1)
            ->and((float) $funds[0]['amount'])->toBe(120.00);
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment_profile_created stores the subscription', function (): void {
        $created = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_subscription_create')->once()->withArgs(function (array $data) use (&$created): bool {
            $created[] = $data;

            return true;
        })->andReturn(8);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault();

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'recurring_payment_profile_created',
                'recurring_payment_id' => 'I-PROFILE2',
                'amount' => '120.00',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($created)->toHaveCount(1)
            ->and($created[0]['sid'])->toBe('I-PROFILE2')
            ->and($created[0]['currency'])->toBe('USD')
            ->and($created[0]['amount'])->toBe('120.00');
    });

    test('subscr_modify updates the stored subscription terms', function (): void {
        $updates = [];
        $subscriptionUpdates = [];
        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getId')->byDefault()->andReturn(7);
        $stored->shouldReceive('getPeriod')->byDefault()->andReturn('1Y');
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_subscription_update')->once()->withArgs(function (array $data) use (&$subscriptionUpdates): bool {
            $subscriptionUpdates[] = $data;

            return true;
        })->andReturn(true);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks(null, $stored);
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_modify',
                'subscr_id' => 'I-ABC123',
                'amount3' => '150.00',
                'period3' => '1 Y',
            ],
            'get' => ['invoice_id' => 16],
        ], 2);

        expect($subscriptionUpdates)->toHaveCount(1)
            ->and($subscriptionUpdates[0]['id'])->toBe(7)
            ->and($subscriptionUpdates[0]['amount'])->toBe('150.00')
            ->and($subscriptionUpdates[0]['period'])->toBe('1Y');
        $linked = array_values(array_filter($updates, fn (array $u): bool => ($u['s_id'] ?? null) === 'I-ABC123'));
        expect($linked)->toHaveCount(1);
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('subscr_modify for an unknown subscription logs a warning instead of throwing', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldNotReceive('invoice_subscription_update');
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $em = paypalEmMocks();
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_modify', 'subscr_id' => 'I-UNKNOWN'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });
});
