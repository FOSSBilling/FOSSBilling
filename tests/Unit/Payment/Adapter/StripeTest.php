<?php

declare(strict_types=1);

use Payment_Adapter_Stripe;
use Stripe\StripeClient;
use Tests\Helpers\DummyBean;

use function Tests\Helpers\container;

beforeEach(function (): void {
    $this->adapter = new Payment_Adapter_Stripe([
        'test_mode' => true,
        'test_api_key' => 'sk_test_dummy',
        'test_pub_key' => 'pk_test_dummy',
    ]);
});

function setPrivateProperty(object $obj, string $property, mixed $value): void
{
    $reflection = new ReflectionClass($obj);
    $prop = $reflection->getProperty($property);
    $prop->setValue($obj, $value);
}

function invokePrivateMethod(object $obj, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($obj);
    $methodObj = $reflection->getMethod($method);
    $methodObj->setAccessible(true);

    return $methodObj->invokeArgs($obj, $args);
}

function buildTransaction(): Model_Transaction
{
    $tx = new Model_Transaction();
    $tx->loadBean(new DummyBean());

    return $tx;
}

describe('isStripeWebhook', function (): void {
    test('identifies customer.subscription.created webhook', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'customer.subscription.created'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies invoice.paid webhook', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'invoice.paid'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies invoice.payment_succeeded webhook', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'invoice.payment_succeeded'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies invoice_payment.paid webhook (API 2026-06-24+)', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'invoice_payment.paid'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies invoice_payment.failed webhook (API 2026-06-24+)', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'invoice_payment.failed'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies all Stripe webhook events for clean processing', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'payment_intent.created'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        // All events are recognized so they get marked processed instead of
        // leaving noisy $0.00 received transactions.
        expect($result)->toBeTrue();
    });

    test('identifies payment_intent.succeeded webhook', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'payment_intent.succeeded'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('identifies setup_intent.succeeded webhook', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'setup_intent.succeeded'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeTrue();
    });

    test('returns false for empty raw post data', function (): void {
        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [['http_raw_post_data' => null]]);

        expect($result)->toBeFalse();
    });
});

describe('handleSubscriptionCreated currency normalization', function (): void {
    test('uppercases lowercase currency from Stripe before passing to API', function (): void {
        $tx = buildTransaction();
        $gatewayId = 1;

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_123';
        $stripeSubscription->currency = 'usd';
        $stripeSubscription->plan = (object) ['amount' => 1000];
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '5',
            'client_id' => '10',
        ];

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $capturedSubscriptionData = null;

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_subscription_create')
            ->once()
            ->withArgs(function ($data) use (&$capturedSubscriptionData): bool {
                $capturedSubscriptionData = $data;

                return true;
            })
            ->andReturn(1);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->with('Subscription', 'sid = :sid', [':sid' => 'sub_123'])
            ->andReturn(null);
        $dbMock->shouldReceive('getCell')
            ->andReturn('1M');

        $di = container();
        $di['db'] = $dbMock;

        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleSubscriptionCreated', [
            $apiAdmin,
            $tx,
            $event,
            $gatewayId,
        ]);

        expect($capturedSubscriptionData)->not->toBeNull()
            ->and($capturedSubscriptionData['currency'])->toBe('USD')
            ->and($tx->invoice_id)->toBe('5');
    });

    test('preserves uppercase currency from Stripe', function (): void {
        $tx = buildTransaction();

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_456';
        $stripeSubscription->currency = 'EUR';
        $stripeSubscription->plan = (object) ['amount' => 2000];
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '7',
            'client_id' => '3',
        ];

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $capturedSubscriptionData = null;

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_subscription_create')
            ->once()
            ->withArgs(function ($data) use (&$capturedSubscriptionData): bool {
                $capturedSubscriptionData = $data;

                return true;
            })
            ->andReturn(2);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->andReturn(null);
        $dbMock->shouldReceive('getCell')
            ->andReturn('1M');

        $di = container();
        $di['db'] = $dbMock;

        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleSubscriptionCreated', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        expect($capturedSubscriptionData['currency'])->toBe('EUR');
    });

    test('skips creation when metadata is missing', function (): void {
        $tx = buildTransaction();

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_789';
        $stripeSubscription->metadata = (object) [];

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_subscription_create');

        $this->adapter->setDi(container());

        invokePrivateMethod($this->adapter, 'handleSubscriptionCreated', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        // Asserting no exception and no API call is sufficient
        expect(true)->toBeTrue();
    });
});

describe('handleInvoicePaymentSucceeded invoice linking', function (): void {
    test('links transaction to invoice before claim attempt', function (): void {
        $tx = buildTransaction();
        $tx->id = 42;
        $tx->invoice_id = null;

        $stripeInvoice = new stdClass();
        $stripeInvoice->id = 'in_123';
        $stripeInvoice->subscription = 'sub_abc';
        $stripeInvoice->billing_reason = 'subscription_create';
        $stripeInvoice->amount_paid = 1500;

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeInvoice];

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_abc';
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '99',
            'client_id' => '5',
        ];

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')
            ->with('sub_abc', [])
            ->andReturn($stripeSubscription);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $storeCalled = false;
        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('store')
            ->withArgs(function ($txArg) use (&$storeCalled): bool {
                // Verify invoice_id is set when store is called
                if ($txArg->invoice_id === 99) {
                    $storeCalled = true;
                }

                return true;
            })
            ->andReturn(42);

        $transactionService = Mockery::mock();
        $transactionService->shouldReceive('claimForProcessing')
            ->andReturn(false);

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($module, $service = null) use ($transactionService) {
            if ($service === 'Transaction') {
                return $transactionService;
            }

            return Mockery::mock();
        });

        $this->adapter->setDi($di);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        // Even though claimForProcessing returned false (causing early return),
        // the invoice_id should have been persisted.
        expect($storeCalled)->toBeTrue()
            ->and($tx->invoice_id)->toBe(99);
    });

    test('falls back to treating unpaid original invoice as initial payment', function (): void {
        $tx = buildTransaction();
        $tx->id = 50;

        $stripeInvoice = new stdClass();
        $stripeInvoice->id = 'in_456';
        $stripeInvoice->subscription = 'sub_def';
        // billing_reason is NOT subscription_create - tests the fallback
        $stripeInvoice->billing_reason = 'cycle';
        $stripeInvoice->amount_paid = 2500;

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeInvoice];

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_def';
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '77',
            'client_id' => '8',
        ];

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')
            ->andReturn($stripeSubscription);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $originalInvoice = new Model_Invoice();
        $originalInvoice->loadBean(new DummyBean());
        $originalInvoice->id = 77;
        $originalInvoice->status = Model_Invoice::STATUS_UNPAID;

        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 77;
        $invoiceModel->status = Model_Invoice::STATUS_UNPAID;
        $invoiceModel->approved = 0;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('store')->andReturn($tx->id);
        // findOne is called for the fallback check
        $dbMock->shouldReceive('findOne')
            ->with('Invoice', 'id = :id', [':id' => 77])
            ->andReturn($originalInvoice);
        $dbMock->shouldReceive('getExistingModelById')
            ->with('Invoice', 77)
            ->andReturn($invoiceModel);

        $transactionService = Mockery::mock();
        $transactionService->shouldReceive('claimForProcessing')
            ->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->andReturn(false);
        $invoiceService->shouldReceive('approveInvoice')
            ->with($invoiceModel, ['use_credits' => false])
            ->andReturn(true);
        $invoiceService->shouldReceive('payInvoiceWithCredits')
            ->with($invoiceModel)
            ->andReturn(true);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($module, $service = null) use ($transactionService, $invoiceService) {
            return match ($service) {
                'Transaction' => $transactionService,
                default => $invoiceService,
            };
        });

        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        // The unpaid original invoice should be approved and paid via the fallback
        expect($tx->invoice_id)->toBe(77);
    });
});

describe('resolveStripeInvoice', function (): void {
    test('passes through legacy invoice objects unchanged', function (): void {
        $invoice = new stdClass();
        $invoice->object = 'invoice';
        $invoice->id = 'in_123';
        $invoice->subscription = 'sub_abc';

        $result = invokePrivateMethod($this->adapter, 'resolveStripeInvoice', [$invoice]);

        expect($result)->toBe($invoice);
    });

    test('retrieves full invoice for invoice_payment objects', function (): void {
        $paymentObject = new stdClass();
        $paymentObject->object = 'invoice_payment';
        $paymentObject->id = 'inpay_123';
        $paymentObject->invoice = 'in_456';

        $fullInvoice = new stdClass();
        $fullInvoice->object = 'invoice';
        $fullInvoice->id = 'in_456';
        $fullInvoice->subscription = 'sub_def';

        $invoicesMock = Mockery::mock();
        $invoicesMock->shouldReceive('retrieve')
            ->with('in_456', [])
            ->andReturn($fullInvoice);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->invoices = $invoicesMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $result = invokePrivateMethod($this->adapter, 'resolveStripeInvoice', [$paymentObject]);

        expect($result)->toBe($fullInvoice)
            ->and($result->subscription)->toBe('sub_def');
    });

    test('returns null when invoice_payment has no invoice reference', function (): void {
        $paymentObject = new stdClass();
        $paymentObject->object = 'invoice_payment';
        $paymentObject->id = 'inpay_789';

        $result = invokePrivateMethod($this->adapter, 'resolveStripeInvoice', [$paymentObject]);

        expect($result)->toBeNull();
    });
});

describe('extractSubscriptionId', function (): void {
    test('reads top-level subscription from legacy API invoices', function (): void {
        $invoice = new stdClass();
        $invoice->subscription = 'sub_legacy_123';

        $result = invokePrivateMethod($this->adapter, 'extractSubscriptionId', [$invoice]);

        expect($result)->toBe('sub_legacy_123');
    });

    test('reads nested subscription from new API (2026-06-24+) invoices', function (): void {
        $invoice = new stdClass();
        $invoice->parent = (object) [
            'type' => 'subscription_details',
            'subscription_details' => (object) [
                'subscription' => 'sub_new_456',
                'metadata' => ['client_id' => '1', 'invoice_id' => '95'],
            ],
        ];

        $result = invokePrivateMethod($this->adapter, 'extractSubscriptionId', [$invoice]);

        expect($result)->toBe('sub_new_456');
    });

    test('reads subscription from line items as fallback', function (): void {
        $invoice = new stdClass();
        $invoice->lines = (object) [
            'data' => [
                (object) [
                    'parent' => (object) [
                        'type' => 'subscription_item_details',
                        'subscription_item_details' => (object) [
                            'subscription' => 'sub_line_789',
                        ],
                    ],
                ],
            ],
        ];

        $result = invokePrivateMethod($this->adapter, 'extractSubscriptionId', [$invoice]);

        expect($result)->toBe('sub_line_789');
    });

    test('returns null when no subscription reference exists', function (): void {
        $invoice = new stdClass();

        $result = invokePrivateMethod($this->adapter, 'extractSubscriptionId', [$invoice]);

        expect($result)->toBeNull();
    });

    test('returns null for null input', function (): void {
        $result = invokePrivateMethod($this->adapter, 'extractSubscriptionId', [null]);

        expect($result)->toBeNull();
    });
});

describe('handleInvoicePaymentSucceeded with invoice_payment event (API 2026-06-24+)', function (): void {
    test('processes invoice_payment.paid by retrieving full invoice and subscription', function (): void {
        $tx = buildTransaction();
        $tx->id = 101;

        // This mirrors the actual webhook payload from API 2026-06-24
        $invoicePayment = new stdClass();
        $invoicePayment->object = 'invoice_payment';
        $invoicePayment->id = 'inpay_1TnBdD';
        $invoicePayment->invoice = 'in_1TnBdC';
        $invoicePayment->amount_paid = 7194;

        $event = new stdClass();
        $event->data = (object) ['object' => $invoicePayment];

        // Full invoice returned by invoices->retrieve (API 2026-06-24 format)
        $fullInvoice = new stdClass();
        $fullInvoice->object = 'invoice';
        $fullInvoice->id = 'in_1TnBdC';
        // New API: subscription is nested under parent.subscription_details
        $fullInvoice->parent = (object) [
            'type' => 'subscription_details',
            'subscription_details' => (object) [
                'subscription' => 'sub_abc',
                'metadata' => ['client_id' => '5', 'invoice_id' => '42'],
            ],
        ];
        $fullInvoice->billing_reason = 'subscription_create';
        $fullInvoice->amount_paid = 7194;

        // Subscription returned by subscriptions->retrieve
        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_abc';
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '42',
            'client_id' => '7',
        ];

        $invoicesMock = Mockery::mock();
        $invoicesMock->shouldReceive('retrieve')
            ->with('in_1TnBdC', [])
            ->andReturn($fullInvoice);

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')
            ->with('sub_abc', [])
            ->andReturn($stripeSubscription);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->invoices = $invoicesMock;
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 42;
        $invoiceModel->status = Model_Invoice::STATUS_UNPAID;
        $invoiceModel->approved = 0;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('store')->andReturn($tx->id);
        $dbMock->shouldReceive('getExistingModelById')
            ->with('Invoice', 42)
            ->andReturn($invoiceModel);

        $transactionService = Mockery::mock();
        $transactionService->shouldReceive('claimForProcessing')
            ->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')
            ->andReturn(false);
        $invoiceService->shouldReceive('approveInvoice')
            ->andReturn(true);
        $invoiceService->shouldReceive('payInvoiceWithCredits')
            ->andReturn(true);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($module, $service = null) use ($transactionService, $invoiceService) {
            return match ($service) {
                'Transaction' => $transactionService,
                default => $invoiceService,
            };
        });

        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [
            $apiAdmin,
            $tx,
            $event,
            4,
        ]);

        expect($tx->invoice_id)->toBe(42);
    });
});

describe('handlePaymentIntentSucceededWebhook', function (): void {
    test('skips processing when payment already handled via redirect flow', function (): void {
        $tx = buildTransaction();
        $tx->id = 200;

        $paymentIntent = new stdClass();
        $paymentIntent->id = 'pi_existing';
        $paymentIntent->object = 'payment_intent';
        $paymentIntent->status = 'succeeded';
        $paymentIntent->amount = 1500;
        $paymentIntent->currency = 'usd';
        $paymentIntent->metadata = (object) ['invoice_id' => '10', 'client_id' => '3'];

        $event = new stdClass();
        $event->data = (object) ['object' => $paymentIntent];

        // Simulate an already-processed transaction from the redirect flow
        $existingTx = buildTransaction();
        $existingTx->id = 199;
        $existingTx->status = Model_Transaction::STATUS_PROCESSED;
        $existingTx->invoice_id = 10;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->with('Transaction', 'txn_id = :txn_id AND status = :status', Mockery::any())
            ->andReturn($existingTx);
        $dbMock->shouldReceive('store')->andReturn($tx->id);

        $di = container();
        $di['db'] = $dbMock;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handlePaymentIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->invoice_id)->toBe(10)
            ->and($tx->txn_id)->toBe('pi_existing');
    });

    test('processes one-time payment when not already handled', function (): void {
        $tx = buildTransaction();
        $tx->id = 201;

        $paymentIntent = new stdClass();
        $paymentIntent->id = 'pi_new';
        $paymentIntent->object = 'payment_intent';
        $paymentIntent->status = 'succeeded';
        $paymentIntent->amount = 2999;
        $paymentIntent->currency = 'usd';
        $paymentIntent->metadata = (object) ['invoice_id' => '15', 'client_id' => '7'];

        $event = new stdClass();
        $event->data = (object) ['object' => $paymentIntent];

        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 15;
        $invoiceModel->approved = 1;
        $invoiceModel->client_id = 7;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->andReturn(null);
        $dbMock->shouldReceive('store')->andReturn($tx->id);
        $dbMock->shouldReceive('getExistingModelById')
            ->with('Invoice', 15)
            ->andReturn($invoiceModel);

        $transactionService = Mockery::mock();
        $transactionService->shouldReceive('claimForProcessing')
            ->andReturn(true);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->andReturn(29.99);
        $invoiceService->shouldReceive('validatePaymentAmount')->andReturn(null);
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->andReturn(false);
        $invoiceService->shouldReceive('payInvoiceWithCredits')->andReturn(true);

        $clientService = Mockery::mock();
        $clientService->shouldReceive('addFunds')->once();

        $clientModel = new Model_Client();
        $clientModel->loadBean(new DummyBean());
        $clientModel->id = 7;

        $dbMock->shouldReceive('getExistingModelById')
            ->with('Client', 7)
            ->andReturn($clientModel);

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($module, $service = null) use ($transactionService, $invoiceService, $clientService) {
            return match (true) {
                $service === 'Transaction' => $transactionService,
                $module === 'client' => $clientService,
                default => $invoiceService,
            };
        });

        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handlePaymentIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->invoice_id)->toBe(15)
            ->and($tx->status)->toBe(Model_Transaction::STATUS_PROCESSED);
    });
});

describe('handleSetupIntentSucceededWebhook', function (): void {
    test('skips processing when setup already handled via redirect flow', function (): void {
        $tx = buildTransaction();
        $tx->id = 300;

        $setupIntent = new stdClass();
        $setupIntent->id = 'seti_existing';
        $setupIntent->object = 'setup_intent';
        $setupIntent->status = 'succeeded';
        $setupIntent->payment_method = 'pm_123';
        $setupIntent->metadata = (object) ['invoice_id' => '20'];

        $event = new stdClass();
        $event->data = (object) ['object' => $setupIntent];

        $existingTx = buildTransaction();
        $existingTx->id = 299;
        $existingTx->status = Model_Transaction::STATUS_PROCESSED;
        $existingTx->invoice_id = 20;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->with('Transaction', 'txn_id = :txn_id AND status = :status', Mockery::any())
            ->andReturn($existingTx);

        $di = container();
        $di['db'] = $dbMock;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleSetupIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->invoice_id)->toBe(20);
    });

    test('creates subscription when not already handled', function (): void {
        $tx = buildTransaction();
        $tx->id = 301;

        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_new',
            'object' => 'setup_intent',
            'status' => 'succeeded',
            'payment_method' => 'pm_456',
            'metadata' => ['invoice_id' => '25'],
        ]);

        $event = new stdClass();
        $event->data = (object) ['object' => $setupIntent];

        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 25;
        $invoiceModel->currency = 'USD';
        $invoiceModel->buyer_email = 'test@example.com';
        $invoiceModel->buyer_first_name = 'Test';
        $invoiceModel->buyer_last_name = 'User';

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->andReturn(null);
        $dbMock->shouldReceive('store')->andReturn($tx->id);
        $dbMock->shouldReceive('getExistingModelById')
            ->with('Invoice', 25)
            ->andReturn($invoiceModel);
        $dbMock->shouldReceive('getCell')->andReturn('1M');
        $dbMock->shouldReceive('getAll')->andReturn([['title' => 'Test Product']]);

        // Mock the Stripe client for customer/subscription creation
        $customer = Stripe\Customer::constructFrom(['id' => 'cus_test']);

        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->andReturn(
            Stripe\SearchResult::constructFrom(['data' => [$customer]])
        );

        $subscription = Stripe\Subscription::constructFrom(['id' => 'sub_new_123']);

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('create')->with(Mockery::any(), Mockery::any())->andReturn($subscription);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        $stripeMock->subscriptions = $subscriptionsMock;
        $product = Stripe\Product::constructFrom(['id' => 'prod_1']);
        $stripeMock->products = Mockery::mock();
        $stripeMock->products->shouldReceive('search')->andReturn(Stripe\SearchResult::constructFrom(['data' => [$product]]));
        $price = Stripe\Price::constructFrom(['id' => 'price_1']);
        $stripeMock->prices = Mockery::mock();
        $stripeMock->prices->shouldReceive('all')->andReturn(Stripe\Collection::constructFrom(['data' => [$price]]));
        $stripeMock->prices->shouldReceive('create')->andReturn($price);

        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $di = container();
        $di['db'] = $dbMock;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleSetupIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->invoice_id)->toBe(25)
            ->and($tx->s_id)->toBe('sub_new_123');
    });
});
