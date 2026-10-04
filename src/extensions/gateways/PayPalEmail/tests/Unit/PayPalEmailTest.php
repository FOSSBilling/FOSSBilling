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

function buildPayPalEmailAdapter(string $configuredEmail, string $postbackResponse, ?array &$capturedRequestBody = null, ?string &$capturedRawRequestBody = null): FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail
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

    $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => $configuredEmail, 'test_mode' => false]);
    $di = container();
    $di['http_client'] = $httpClient;
    $adapter->setDi($di);

    return $adapter;
}

function callIsIpnValid(FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail $adapter, string $rawPostBody): bool
{
    $reflection = new ReflectionClass($adapter);

    return (bool) $reflection->getMethod('_isIpnValid')->invokeArgs($adapter, [['http_raw_post_data' => $rawPostBody]]);
}

describe('_isIpnValid receiver verification', function (): void {
    test('rejects array fields without sending a verification request', function (string $body): void {
        $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
        $httpClient = Mockery::mock();
        $httpClient->shouldNotReceive('withOptions');
        $di = container();
        $di['http_client'] = $httpClient;
        $adapter->setDi($di);

        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            expect(callIsIpnValid($adapter, $body))->toBeFalse();
        } finally {
            restore_error_handler();
        }
    })->with([
        'array payee' => 'receiver_email[]=merchant%40example.com',
        'nested field' => 'receiver_email=merchant%40example.com&custom[invoice][id]=7',
    ]);

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
        $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
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
            'get' => signedPayPalGet(7),
        ], 1);

        expect($logger->calls)->toContain([
            'method' => 'error',
            'params' => ['Unknown PayPal transaction 42'],
        ]);
    });
});

function paypalProcessAdapter(object $di): FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail
{
    $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
    $adapter->setDi($di);

    return $adapter;
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

function paypalEmMocks(?object $invoiceModel = null, ?object $existingSubscription = null, ?callable $findActiveByTxn = null): Mockery\MockInterface
{
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);
    $invoiceRepo->shouldReceive('find')->byDefault()->andReturn($invoiceModel);
    $subRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subRepo->shouldReceive('findOneBy')->byDefault()->andReturn($existingSubscription);
    $transactionRepo = Mockery::mock(Box\Mod\Invoice\Repository\TransactionRepository::class);
    $transactionRepo->shouldReceive('findActiveByTxnIdAndGatewayId')->byDefault()->andReturnUsing(
        $findActiveByTxn ?? static fn (): ?object => null
    );
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('quoteSingleIdentifier')->byDefault()->with('transaction')->andReturn('"transaction"');
    $connection->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
    $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->byDefault()->andReturnUsing(static fn (string $class): object => match ($class) {
        Box\Mod\Invoice\Entity\Invoice::class => $invoiceRepo,
        Box\Mod\Invoice\Entity\Subscription::class => $subRepo,
        Box\Mod\Invoice\Entity\Transaction::class => $transactionRepo,
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
        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldNotReceive('getSubscriptionPeriod');
        $di['mod_service'] = $di->protect(static fn (): object => $subscriptionService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_signup',
                'subscr_id' => 'I-ABC123',
                'mc_currency' => 'USD',
                'period3' => '1 Y',
                'amount3' => '120.00',
            ],
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
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
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
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
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

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
            'get' => signedPayPalGet(16, 2),
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
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
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
        $renewal->shouldReceive('isIssued')->byDefault()->andReturn(true);

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
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class);
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
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal payment is missing transaction details');
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
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal subscription payment is missing the subscription ID');
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

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $em = paypalEmMocks($invoiceModel);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $invoiceService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'web_accept',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-1',
                'mc_gross' => '120.00',
                'mc_currency' => 'USD',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment from the newer flow pays the original invoice', function (): void {
        $updates = [];
        $funds = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'recurring_payment', 'txn_id' => 'TXN-R1', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
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
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

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
                'amount_currency' => 'usd',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        expect($funds)->toHaveCount(1)
            ->and((float) $funds[0]['amount'])->toBe(120.00);
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment rejects a currency that does not match the invoice', function (): void {
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

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        expect(fn () => paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'recurring_payment',
                'payment_status' => 'Completed',
                'txn_id' => 'TXN-R1',
                'recurring_payment_id' => 'I-PROFILE1',
                'amount' => '120.00',
                'amount_currency' => 'MXN',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal payment currency MXN does not match invoice currency USD');
    });

    test('recurring_payment_profile_created stores the subscription', function (): void {
        $created = [];
        $updates = [];
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
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldReceive('getSubscriptionPeriod')->once()->with($invoiceModel)->andReturn('1M');

        $em = paypalEmMocks($invoiceModel);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $subscriptionService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'recurring_payment_profile_created',
                'recurring_payment_id' => 'I-PROFILE2',
                'amount' => '120.00',
                'amount_currency' => 'USD',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        expect($created)->toHaveCount(1)
            ->and($created[0]['sid'])->toBe('I-PROFILE2')
            ->and($created[0]['currency'])->toBe('USD')
            ->and($created[0]['amount'])->toBe('120.00')
            ->and($created[0]['period'])->toBe('1M');

        $linked = array_values(array_filter($updates, fn (array $u): bool => ($u['s_id'] ?? null) === 'I-PROFILE2'));
        expect($linked)->toHaveCount(1)
            ->and($linked[0]['s_period'])->toBe('1M');
    });

    test('recurring_payment_profile_created rejects a currency that does not match the invoice', function (): void {
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

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        expect(fn () => paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'recurring_payment_profile_created',
                'recurring_payment_id' => 'I-PROFILE2',
                'amount' => '120.00',
                'amount_currency' => 'MXN',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal payment currency MXN does not match invoice currency USD');
    });

    test('signup without a period stores null when the invoice is not subscribable', function (): void {
        $created = [];
        $updates = [];
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
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldReceive('getSubscriptionPeriod')->once()->with($invoiceModel)->andReturnNull();

        $em = paypalEmMocks($invoiceModel);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(static fn (): object => $subscriptionService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_signup',
                'subscr_id' => 'I-ABC999',
                'mc_currency' => 'USD',
                'amount3' => '120.00',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        expect($created)->toHaveCount(1)
            ->and(array_key_exists('period', $created[0]))->toBeTrue()
            ->and($created[0]['period'])->toBeNull();

        $linked = array_values(array_filter($updates, fn (array $u): bool => ($u['s_id'] ?? null) === 'I-ABC999'));
        expect($linked)->toHaveCount(1)
            ->and(array_key_exists('s_period', $linked[0]))->toBeTrue()
            ->and($linked[0]['s_period'])->toBeNull();
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
            'get' => signedPayPalGet(16, 2),
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
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment_profile_cancel cancels the stored subscription', function (): void {
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
            'post' => ['txn_type' => 'recurring_payment_profile_cancel', 'recurring_payment_id' => 'I-PROFILE1'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment_failed leaves the subscription active', function (): void {
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

        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $em = paypalEmMocks(null, $stored);
        $logger = new Tests\Helpers\TestLogger();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = $logger;

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'recurring_payment_failed', 'recurring_payment_id' => 'I-PROFILE1'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $linked = array_values(array_filter($updates, fn (array $u): bool => ($u['s_id'] ?? null) === 'I-PROFILE1'));
        expect($linked)->toHaveCount(1);
        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('subscr_failed for an unknown subscription logs a warning instead of throwing', function (): void {
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
            'post' => ['txn_type' => 'subscr_failed', 'subscr_id' => 'I-UNKNOWN'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('recurring_payment_skipped is acknowledged without touching the subscription', function (): void {
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
            'post' => ['txn_type' => 'recurring_payment_skipped', 'recurring_payment_id' => 'I-PROFILE1'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $warnings = array_filter($logger->calls, fn (array $c): bool => $c['method'] === 'warning');
        expect($warnings)->not->toBeEmpty();
        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('subscription payment for a different invoice throws before claiming', function (): void {
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

        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getRelType')->byDefault()->andReturn('invoice');
        $stored->shouldReceive('getRelId')->byDefault()->andReturn(99);
        $em = paypalEmMocks(null, $stored);
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
                'subscr_id' => 'I-OTHER',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2))->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'is not linked to invoice 16');
    });

    test('subscription payment matching the stored invoice proceeds', function (): void {
        $updates = [];
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->twice()->with(['id' => 42])->andReturn(
            ['invoice_id' => 16, 'type' => null, 'txn_id' => null, 'txn_status' => null, 'amount' => null, 'currency' => null, 'status' => 'received'],
            ['invoice_id' => 16, 'type' => 'subscr_payment', 'txn_id' => 'TXN-1', 'txn_status' => 'Completed', 'amount' => '120.00', 'currency' => 'USD', 'status' => 'processing']
        );
        $apiAdmin->shouldNotReceive('invoice_transaction_claim_for_processing');
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();
        $apiAdmin->shouldReceive('invoice_pay_with_credits')->once()->with(['id' => 16]);

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getRelType')->byDefault()->andReturn('invoice');
        $stored->shouldReceive('getRelId')->byDefault()->andReturn(16);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);
        $invoiceService->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment');

        $em = paypalEmMocks($invoiceModel, $stored);
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
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('subscr_signup without a currency fails cleanly instead of warning', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_subscription_create')->never();
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->andReturnTrue();

        $em = paypalEmMocks();
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldNotReceive('getSubscriptionPeriod');
        $di['mod_service'] = $di->protect(static fn (): object => $subscriptionService);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => [
                'txn_type' => 'subscr_signup',
                'subscr_id' => 'I-ABC123',
                'period3' => '1 Y',
                'amount3' => '120.00',
            ],
            'get' => signedPayPalGet(16, 2),
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal payment is missing currency details');

    test('isIpnDuplicate tolerates IPNs missing optional keys', function (): void {
        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('quoteSingleIdentifier')->with('transaction')->andReturn('"transaction"');
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([]);
        $em = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
        $em->shouldReceive('getConnection')->andReturn($connection);
        $di = container();
        $di['em'] = $em;

        $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false]);
        $adapter->setDi($di);

        expect($adapter->isIpnDuplicate(['txn_id' => 'ABC']))->toBeFalse();
    });
});

describe('PayPal callback invoice binding', function (): void {
    test('rejects a callback whose invoice id was swapped after signing', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        // Signature was issued for invoice 16; the callback URL now names 99.
        $sig = FOSSBilling\Tools::signCallbackParams(2, 16);

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 99, 'sig' => $sig],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

    test('rejects a callback with no signature', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 16],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

    test('rejects a signature issued for a different gateway', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_transaction_update', 'invoice_get');

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => ['invoice_id' => 16, 'sig' => FOSSBilling\Tools::signCallbackParams(7, 16)],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

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
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'unknown_event', 'payment_status' => 'Unknown'],
            'get' => signedPayPalGet(16, 2),
        ], 2);

        $bound = array_values(array_filter($updates, fn (array $u): bool => isset($u['invoice_id'])));
        expect($bound)->toHaveCount(1)
            ->and((int) $bound[0]['invoice_id'])->toBe(16);
    });

    test('accepts an unsigned renewal for a stored subscription on the same invoice', function (): void {
        $updates = [];
        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getId')->byDefault()->andReturn(5);
        $stored->shouldReceive('getRelType')->byDefault()->andReturn('invoice');
        $stored->shouldReceive('getRelId')->byDefault()->andReturn(16);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldReceive('invoice_get')->once()->with(['id' => 16])->andReturn([
            'id' => 16, 'currency' => 'USD', 'client' => ['id' => 9],
        ]);
        $apiAdmin->shouldReceive('invoice_subscription_update')->once()->with(['id' => 5, 'status' => 'canceled']);
        $apiAdmin->shouldReceive('invoice_transaction_update')->byDefault()->withArgs(function (array $data) use (&$updates): bool {
            $updates[] = $data;

            return true;
        });

        $di = container();
        $di['em'] = paypalEmMocks(null, $stored);
        $di['logger'] = new Tests\Helpers\TestLogger();

        // Legacy recurring profile: callback URL predates signing, so no sig.
        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'recurring_payment_profile_cancel', 'subscr_id' => 'I-LEGACY'],
            'get' => ['invoice_id' => 16],
        ], 2);

        $processed = array_values(array_filter($updates, fn (array $u): bool => ($u['status'] ?? null) === 'processed'));
        expect($processed)->toHaveCount(1);
    });

    test('rejects an unsigned callback naming a different invoice for a stored subscription', function (): void {
        $stored = Mockery::mock(Box\Mod\Invoice\Entity\Subscription::class);
        $stored->shouldReceive('getRelType')->byDefault()->andReturn('invoice');
        $stored->shouldReceive('getRelId')->byDefault()->andReturn(16);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 99, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get', 'invoice_subscription_update');

        $di = container();
        $di['em'] = paypalEmMocks(null, $stored);
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'recurring_payment_profile_cancel', 'subscr_id' => 'I-LEGACY'],
            'get' => ['invoice_id' => 99],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

    test('rejects an unsigned callback for an unknown subscription', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 16, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'subscr_cancel', 'subscr_id' => 'I-UNKNOWN'],
            'get' => ['invoice_id' => 16],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

    test('still requires an invoice when none is supplied', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => null, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed'],
            'get' => [],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal transaction is not associated with an invoice');

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

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getNr')->byDefault()->andReturn('00042');

        $di = container();
        $di['em'] = paypalEmMocks($invoiceModel);
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
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal item_number does not match invoice 16');

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

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getNr')->byDefault()->andReturn('00042');
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $di = container();
        $di['em'] = paypalEmMocks($invoiceModel);
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

    test('getInvoiceId resolves a signed callback and rejects a tampered one', function (): void {
        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail(['email' => 'merchant@example.com', 'test_mode' => false, 'gateway_id' => 2]);
        $adapter->setDi($di);

        expect($adapter->getInvoiceId(['get' => signedPayPalGet(16, 2)]))->toBe(16);
        expect($adapter->getInvoiceId(['get' => []]))->toBeNull();
        expect(fn (): mixed => $adapter->getInvoiceId(['get' => ['invoice_id' => 99, 'sig' => FOSSBilling\Tools::signCallbackParams(2, 16)]]))
            ->toThrow(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');
    });

    test('accepts an unsigned refund continuing a pre-upgrade payment', function (): void {
        $refunded = [];
        $earlierInvoice = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $earlierInvoice->shouldReceive('getId')->byDefault()->andReturn(16);
        $earlier = Mockery::mock(Box\Mod\Invoice\Entity\Transaction::class);
        $earlier->shouldReceive('getInvoice')->byDefault()->andReturn($earlierInvoice);

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

        $em = paypalEmMocks(null, null, static fn (string $txnId): ?object => $txnId === 'TXN-ORIG' ? $earlier : null);
        $di = container();
        $di['em'] = $em;
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
        $earlierInvoice = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $earlierInvoice->shouldReceive('getId')->byDefault()->andReturn(16);
        $earlier = Mockery::mock(Box\Mod\Invoice\Entity\Transaction::class);
        $earlier->shouldReceive('getInvoice')->byDefault()->andReturn($earlierInvoice);

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

        $invoiceModel = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $invoiceModel->shouldReceive('getId')->byDefault()->andReturn(16);
        $invoiceModel->shouldReceive('getStatus')->byDefault()->andReturn(Box\Mod\Invoice\Entity\Invoice::STATUS_UNPAID);
        $invoiceModel->shouldReceive('isIssued')->byDefault()->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(120.00);
        $invoiceService->shouldReceive('validatePaymentAmount')->once()->with(120.00, 120.00)->andReturnNull();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->andReturn(false);

        $em = paypalEmMocks($invoiceModel, null, static fn (): ?object => $earlier);
        $di = container();
        $di['em'] = $em;
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
        $earlierInvoice = Mockery::mock(Box\Mod\Invoice\Entity\Invoice::class);
        $earlierInvoice->shouldReceive('getId')->byDefault()->andReturn(16);
        $earlier = Mockery::mock(Box\Mod\Invoice\Entity\Transaction::class);
        $earlier->shouldReceive('getInvoice')->byDefault()->andReturn($earlierInvoice);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_transaction_get')->once()->with(['id' => 42])->andReturn([
            'invoice_id' => 99, 'type' => null, 'txn_id' => null,
            'txn_status' => null, 'amount' => null, 'currency' => null,
        ]);
        $apiAdmin->shouldNotReceive('invoice_get');

        $em = paypalEmMocks(null, null, static fn (): ?object => $earlier);
        $di = container();
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();

        paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [
            'post' => ['txn_type' => 'web_accept', 'payment_status' => 'Completed', 'txn_id' => 'TXN-X'],
            'get' => ['invoice_id' => 99],
        ], 2);
    })->throws(FOSSBilling\Extension\Contract\Payment\Exception::class, 'PayPal callback signature is invalid');

    test('payment forms sign the callback URL for the paying invoice', function (): void {
        $di = container();
        $di['em'] = paypalEmMocks();
        $di['logger'] = new Tests\Helpers\TestLogger();

        $adapter = new FOSSBilling\Extension\Gateway\PayPalEmail\PayPalEmail([
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

describe('processTransaction payload handling', function (): void {
    test('rejects a missing post payload with a clear error', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_transaction_get');

        $di = container();
        $di['em'] = paypalEmMocks();

        try {
            paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, [], 2);
            expect(false)->toBeTrue('missing PayPal payloads must throw');
        } catch (FOSSBilling\Extension\Contract\Payment\Exception $e) {
            expect($e->getMessage())->toBe('PayPal payment data is missing.')
                ->and($e->getCode())->toBe(7021);
        }
    });

    test('rejects a non-array post payload with a clear error', function (): void {
        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_transaction_get');

        $di = container();
        $di['em'] = paypalEmMocks();

        try {
            paypalProcessAdapter($di)->processTransaction($apiAdmin, 42, ['post' => 'corrupt'], 2);
            expect(false)->toBeTrue('corrupt PayPal payloads must throw');
        } catch (FOSSBilling\Extension\Contract\Payment\Exception $e) {
            expect($e->getMessage())->toBe('PayPal payment data is missing.')
                ->and($e->getCode())->toBe(7021);
        }
    });
});
