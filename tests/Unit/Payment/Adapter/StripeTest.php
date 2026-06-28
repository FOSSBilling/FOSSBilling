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

    test('does not identify non-subscription webhook events', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'payment_intent.succeeded'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        expect($result)->toBeFalse();
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
