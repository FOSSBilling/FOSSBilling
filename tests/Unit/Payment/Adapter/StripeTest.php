<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\Entity\PayGatewayProduct;
use Box\Mod\Invoice\Repository\PayGatewayCustomerRepository;
use Box\Mod\Invoice\Repository\PayGatewayProductRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Payment_Adapter_Stripe;
use Stripe\StripeClient;
use Tests\Helpers\DummyBean;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

const TEST_WEBHOOK_SECRET = 'whsec_test_dummy';

beforeEach(function (): void {
    $this->adapter = new Payment_Adapter_Stripe([
        'test_mode' => true,
        'test_api_key' => 'sk_test_dummy',
        'test_pub_key' => 'pk_test_dummy',
        'test_webhook_secret' => TEST_WEBHOOK_SECRET,
        'gateway_id' => 1,
    ]);

    // cacheGatewayCustomer()/cacheGatewayProduct() open their own isolated
    // EntityManager rather than using $di['em'] - default that to a no-op
    // stub so tests don't hit a real database; tests that care about the
    // cache write override this.
    setPrivateProperty($this->adapter, 'entityManagerFactory', fn (): object => noOpEntityManagerMock());
});

function noOpEntityManagerMock(): Mockery\MockInterface
{
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->byDefault();
    $em->shouldReceive('flush')->byDefault();
    $em->shouldReceive('isOpen')->byDefault()->andReturn(true);

    return $em;
}

function uniqueConstraintViolationException(): UniqueConstraintViolationException
{
    $driverException = new class extends Exception implements Doctrine\DBAL\Driver\Exception {
        public function getSQLState(): ?string
        {
            return '23000';
        }
    };

    return new UniqueConstraintViolationException($driverException, null);
}

test('cancels a Stripe subscription', function (): void {
    $subscriptionsMock = Mockery::mock();
    $subscriptionsMock->shouldReceive('retrieve')
        ->once()
        ->with('sub_123', [])
        ->andReturn((object) ['status' => 'active']);
    $subscriptionsMock->shouldReceive('cancel')
        ->once()
        ->with('sub_123', []);

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->subscriptions = $subscriptionsMock;
    setPrivateProperty($this->adapter, 'stripe', $stripeMock);

    $this->adapter->cancelSubscription('sub_123');
});

test('does not cancel an already canceled Stripe subscription again', function (): void {
    $subscriptionsMock = Mockery::mock();
    $subscriptionsMock->shouldReceive('retrieve')
        ->once()
        ->with('sub_123', [])
        ->andReturn((object) ['status' => Stripe\Subscription::STATUS_CANCELED]);
    $subscriptionsMock->shouldReceive('cancel')->never();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->subscriptions = $subscriptionsMock;
    setPrivateProperty($this->adapter, 'stripe', $stripeMock);

    $this->adapter->cancelSubscription('sub_123');
});

test('schedules a Stripe subscription cancellation at period end', function (): void {
    $subscriptionsMock = Mockery::mock();
    $subscriptionsMock->shouldReceive('retrieve')
        ->once()
        ->with('sub_123', [])
        ->andReturn((object) ['status' => 'active', 'cancel_at_period_end' => false]);
    $subscriptionsMock->shouldReceive('update')
        ->once()
        ->with('sub_123', ['cancel_at_period_end' => true]);

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->subscriptions = $subscriptionsMock;
    setPrivateProperty($this->adapter, 'stripe', $stripeMock);

    $this->adapter->cancelSubscriptionAtPeriodEnd('sub_123');
});

test('does not reschedule a Stripe subscription already ending at period end', function (): void {
    $subscriptionsMock = Mockery::mock();
    $subscriptionsMock->shouldReceive('retrieve')
        ->once()
        ->with('sub_123', [])
        ->andReturn((object) ['status' => 'active', 'cancel_at_period_end' => true]);
    $subscriptionsMock->shouldReceive('update')->never();

    $stripeMock = Mockery::mock(StripeClient::class);
    $stripeMock->subscriptions = $subscriptionsMock;
    setPrivateProperty($this->adapter, 'stripe', $stripeMock);

    $this->adapter->cancelSubscriptionAtPeriodEnd('sub_123');
});

function setPrivateProperty(object $obj, string $property, mixed $value): void
{
    $reflection = new ReflectionClass($obj);
    $prop = $reflection->getProperty($property);
    $prop->setValue($obj, $value);
}

function expectPaymentIntentLock(Mockery\MockInterface $dbalMock, string $paymentIntentId, int $gatewayId): void
{
    $lockName = 'fb:stripe:' . substr(hash('sha256', $gatewayId . ':' . $paymentIntentId), 0, 54);
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:lock_name, 10)', ['lock_name' => $lockName])
        ->andReturn(1);
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT RELEASE_LOCK(:lock_name)', ['lock_name' => $lockName])
        ->andReturn(1);
}

function signStripeWebhookPayload(string $payload, string $secret = TEST_WEBHOOK_SECRET): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}

function invokePrivateMethod(object $obj, string $method, array $args = []): mixed
{
    $reflection = new ReflectionClass($obj);
    $methodObj = $reflection->getMethod($method);

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

    test('identifies all Stripe webhook events for dispatch', function (): void {
        $data = ['http_raw_post_data' => json_encode(['type' => 'payment_intent.created'])];

        $result = invokePrivateMethod($this->adapter, 'isStripeWebhook', [$data]);

        // All JSON payloads with a type field are recognized as webhooks.
        // Unhandled types are deleted in processWebhookEvent to prevent noise.
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

describe('handleSubscriptionCreated', function (): void {
    test('creates subscription via createOrUpdateSubscription helper', function (): void {
        $tx = buildTransaction();
        $gatewayId = 1;

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_123';
        $stripeSubscription->currency = 'usd';
        $stripeSubscription->metadata = (object) [
            'invoice_id' => '5',
            'client_id' => '10',
        ];

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 5;
        $invoiceModel->client_id = 10;
        $invoiceModel->currency = 'USD';

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
            ->with('Subscription', 'sid = :sid', Mockery::any())
            ->andReturn(null);
        $dbMock->shouldReceive('getExistingModelById')
            ->with('Invoice', 5)
            ->andReturn($invoiceModel);
        $dbMock->shouldReceive('getCell')->andReturn('1M');
        $dbMock->shouldReceive('getAll')->andReturn([['title' => 'Test Product']]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->andReturn(10.00);

        $subscriptionService = Mockery::mock();
        $subscriptionService->shouldReceive('getSubscriptionPeriod')->andReturn('1M');

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($name, $sub = '') use ($invoiceService, $subscriptionService) {
            if ($name === 'Invoice' && $sub === 'Subscription') {
                return $subscriptionService;
            }
            if ($name === 'Invoice') {
                return $invoiceService;
            }

            return Mockery::mock();
        });

        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'handleSubscriptionCreated', [
            $apiAdmin,
            $tx,
            $event,
            $gatewayId,
        ]);

        expect($result)->toBeFalse()
            ->and($capturedSubscriptionData)->not->toBeNull()
            ->and($capturedSubscriptionData['currency'])->toBe('USD')
            ->and($capturedSubscriptionData['sid'])->toBe('sub_123')
            ->and($tx->invoice_id)->toBe(5);
    });

    test('returns false when metadata is missing', function (): void {
        $tx = buildTransaction();

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_no_meta';
        $stripeSubscription->metadata = new stdClass();

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $di = container();
        $di['db'] = Mockery::mock('\Box_Database');
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'handleSubscriptionCreated', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($result)->toBeFalse();
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

test('syncs subscription webhook status through the internal service path', function (): void {
    $stripeSubscription = (object) [
        'id' => 'sub_123',
        'status' => 'canceled',
    ];
    $event = (object) ['data' => (object) ['object' => $stripeSubscription]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('invoice_subscription_get')
        ->once()
        ->with(['sid' => 'sub_123'])
        ->andReturn(['id' => 42]);
    $apiAdmin->shouldNotReceive('invoice_subscription_update');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('updateStatusFromGateway')
        ->once()
        ->with(42, 'canceled');

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(invokePrivateMethod($this->adapter, 'handleSubscriptionUpdated', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toBeFalse();
});

test('syncs end-of-period cancellation state from Stripe', function (): void {
    $event = (object) ['data' => (object) ['object' => (object) [
        'id' => 'sub_123',
        'status' => 'active',
        'cancel_at_period_end' => true,
    ]]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('invoice_subscription_get')->once()->andReturn(['id' => 42]);

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('updateStatusFromGateway')
        ->once()
        ->with(42, Box\Mod\Invoice\ServiceSubscription::STATUS_PENDING_CANCELLATION);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(invokePrivateMethod($this->adapter, 'handleSubscriptionUpdated', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toBeFalse();
});

test('finalizes local cancellation when Stripe deletes a subscription', function (): void {
    $event = (object) ['data' => (object) ['object' => (object) ['id' => 'sub_123']]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('invoice_subscription_get');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('findIdBySid')->once()->with('sub_123')->andReturn(42);
    $subscriptionService->shouldReceive('finalizeCancellationFromGateway')->once()->with(42);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(invokePrivateMethod($this->adapter, 'handleSubscriptionDeleted', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toBeFalse();
});

test('propagates local cancellation failures so Stripe retries the webhook', function (): void {
    $event = (object) ['data' => (object) ['object' => (object) ['id' => 'sub_123']]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('invoice_subscription_get');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('findIdBySid')->once()->with('sub_123')->andReturn(42);
    $subscriptionService->shouldReceive('finalizeCancellationFromGateway')
        ->once()
        ->with(42)
        ->andThrow(new RuntimeException('Service cancellation failed'));

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(fn (): mixed => invokePrivateMethod($this->adapter, 'handleSubscriptionDeleted', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toThrow(RuntimeException::class, 'Service cancellation failed');
});

test('propagates subscription lookup failures so Stripe retries the webhook', function (): void {
    $event = (object) ['data' => (object) ['object' => (object) ['id' => 'sub_123']]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('invoice_subscription_get');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('findIdBySid')
        ->once()
        ->with('sub_123')
        ->andThrow(new RuntimeException('Database unavailable'));

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(fn (): mixed => invokePrivateMethod($this->adapter, 'handleSubscriptionDeleted', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toThrow(RuntimeException::class, 'Database unavailable');
});

test('ignores deleted Stripe subscriptions without a local record', function (): void {
    $event = (object) ['data' => (object) ['object' => (object) ['id' => 'sub_missing']]];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldNotReceive('invoice_subscription_get');

    $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptionService->shouldReceive('findIdBySid')->once()->with('sub_missing')->andReturn(null);

    $di = container();
    $di['mod_service'] = $di->protect(fn () => $subscriptionService);
    $this->adapter->setDi($di);

    expect(invokePrivateMethod($this->adapter, 'handleSubscriptionDeleted', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toBeFalse();
});

test('skips subscription update webhooks without a local subscription', function (): void {
    $event = (object) [
        'data' => (object) [
            'object' => (object) [
                'id' => 'sub_missing',
                'status' => 'active',
            ],
        ],
    ];

    $apiAdmin = Mockery::mock();
    $apiAdmin->shouldReceive('invoice_subscription_get')
        ->once()
        ->with(['sid' => 'sub_missing'])
        ->andThrow(new Exception('Subscription not found'));

    expect(invokePrivateMethod($this->adapter, 'handleSubscriptionUpdated', [
        $apiAdmin,
        buildTransaction(),
        $event,
    ]))->toBeFalse();
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
        $dbMock->shouldReceive('findOne')->andReturn(null);

        $transactionService = Mockery::mock();
        $transactionService->shouldReceive('claimForProcessing')
            ->andReturn(false);

        $dbalMock = Mockery::mock();
        expectPaymentIntentLock($dbalMock, 'in_123', 1);

        $di = container();
        $di['db'] = $dbMock;
        $di['dbal'] = $dbalMock;
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
        // Duplicate-event check — no prior transaction processed this Stripe invoice.
        $dbMock->shouldReceive('findOne')
            ->with('Transaction', Mockery::any(), Mockery::any())
            ->andReturn(null);
        // findOne for the already-paid guard and the billing_reason fallback.
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

        $dbalMock = Mockery::mock();
        expectPaymentIntentLock($dbalMock, 'in_456', 1);

        $di = container();
        $di['db'] = $dbMock;
        $di['dbal'] = $dbalMock;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => match ($service) {
            'Transaction' => $transactionService,
            default => $invoiceService,
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
        $dbMock->shouldReceive('findOne')->andReturn(null);
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

        $dbalMock = Mockery::mock();
        expectPaymentIntentLock($dbalMock, 'in_1TnBdC', 4);

        $di = container();
        $di['db'] = $dbMock;
        $di['dbal'] = $dbalMock;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => match ($service) {
            'Transaction' => $transactionService,
            default => $invoiceService,
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
        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectPaymentIntentLock($dbalMock, 'pi_existing', 1);
        $dbMock->shouldReceive('findOne')
            ->with(
                'Transaction',
                'txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (:s1, :s2)',
                Mockery::on(fn (array $params): bool => $params[':txn_id'] === 'pi_existing'
                    && $params[':gateway_id'] === 1
                    && $params[':id'] === 200)
            )
            ->andReturn($existingTx);
        $dbMock->shouldReceive('store')->andReturn($tx->id);

        $di = container();
        $di['db'] = $dbMock;
        $di['dbal'] = $dbalMock;
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
        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectPaymentIntentLock($dbalMock, 'pi_new', 1);
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
        $invoiceService->shouldReceive('validatePaymentAmount')
            ->once()
            ->with(Mockery::on(fn (mixed $amount): bool => is_float($amount) && $amount === 29.99), 29.99)
            ->andReturn(null);
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
        $di['dbal'] = $dbalMock;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => match (true) {
            $service === 'Transaction' => $transactionService,
            $module === 'client' => $clientService,
            default => $invoiceService,
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

describe('processPaymentIntent', function (): void {
    test('deletes the redirect transaction when the webhook already recorded the PaymentIntent', function (): void {
        $tx = buildTransaction();
        $tx->id = 401;
        $tx->gateway_id = 4;

        $existingTx = buildTransaction();

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_webhook_first',
            'status' => 'succeeded',
            'amount' => 2500,
            'currency' => 'usd',
        ]);

        $paymentIntentsMock = Mockery::mock();
        $paymentIntentsMock->shouldReceive('retrieve')
            ->once()
            ->with('pi_webhook_first', [])
            ->andReturn($paymentIntent);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->paymentIntents = $paymentIntentsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $dbMock = Mockery::mock('\Box_Database');
        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectPaymentIntentLock($dbalMock, 'pi_webhook_first', 4);
        $dbMock->shouldReceive('findOne')
            ->once()
            ->with('Transaction', 'txn_id = :txn_id AND gateway_id = :gateway_id AND id != :id AND status IN (:s1, :s2, :s3)', Mockery::on(fn (array $params): bool => $params[':txn_id'] === 'pi_webhook_first'
                && $params[':gateway_id'] === 4
                && $params[':id'] === 401))
            ->andReturn($existingTx);
        $dbMock->shouldReceive('trash')->once()->with($tx);

        $di = container();
        $di['db'] = $dbMock;
        $di['dbal'] = $dbalMock;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'processPaymentIntent', [
            $tx,
            null,
            ['get' => ['payment_intent' => 'pi_webhook_first']],
        ]);

        expect($tx->txn_id)->toBe('pi_webhook_first');
    });
});

test('releases the PaymentIntent lock when processing fails', function (): void {
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    expectPaymentIntentLock($dbalMock, 'pi_failure', 2);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->adapter->setDi($di);

    expect(fn (): mixed => invokePrivateMethod($this->adapter, 'withStripeObjectLock', [
        'pi_failure',
        2,
        fn () => throw new RuntimeException('Processing failed'),
    ]))->toThrow(RuntimeException::class, 'Processing failed');
});

test('logs PaymentIntent lock timeouts with lock context', function (): void {
    $lockName = 'fb:stripe:' . substr(hash('sha256', '2:pi_timeout'), 0, 54);
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:lock_name, 10)', ['lock_name' => $lockName])
        ->andReturn(0);

    $logger = new Tests\Helpers\TestLogger();
    $di = container();
    $di['dbal'] = $dbalMock;
    $di['logger'] = $logger;
    $this->adapter->setDi($di);

    expect(fn (): mixed => invokePrivateMethod($this->adapter, 'withStripeObjectLock', [
        'pi_timeout',
        2,
        fn (): null => null,
    ]))->toThrow(FOSSBilling\Exception::class, 'Timed out waiting to process this Stripe payment')
        ->and($logger->calls)->toHaveCount(1)
        ->and($logger->calls[0]['method'])->toBe('warning')
        ->and($logger->calls[0]['params'][0])->toContain('Timed out after')
        ->and($logger->calls[0]['params'][2])->toBe($lockName);
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
            ->with('Transaction', 'txn_id = :txn_id AND status IN (:s1, :s2)', Mockery::any())
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

describe('processSetupIntent', function (): void {
    test('rejects a setup intent whose metadata invoice_id does not match the resolved invoice', function (): void {
        // Regression coverage: unlike the webhook flow (which derives the invoice from the setup
        // intent's own metadata), the redirect flow resolves $invoice and $setupIntent
        // independently, from separate query parameters on the redirect URL (see
        // resolveInvoice()). Without this check, a request naming a victim's invoice_id alongside
        // the requester's own completed setup intent would subscribe/charge the requester but
        // credit and mark paid whatever invoice_id was supplied.
        $victimInvoice = new Model_Invoice();
        $victimInvoice->loadBean(new DummyBean());
        $victimInvoice->id = 90;
        $victimInvoice->client_id = 5;

        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_attacker',
            'status' => 'succeeded',
            'payment_method' => 'pm_attacker',
            'customer' => 'cus_attacker',
            'metadata' => ['invoice_id' => '999', 'gateway_id' => '1', 'price_id' => 'price_attacker'],
        ]);

        $setupIntentsMock = Mockery::mock();
        $setupIntentsMock->shouldReceive('retrieve')->once()->with('seti_attacker', [])->andReturn($setupIntent);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->setupIntents = $setupIntentsMock;
        $stripeMock->subscriptions = Mockery::mock(); // no expectations - must not be touched
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $tx = buildTransaction();
        $tx->id = 700;
        $this->adapter->setDi(container());

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_subscription_create');
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        invokePrivateMethod($this->adapter, 'processSetupIntent', [
            $apiAdmin,
            $tx,
            $victimInvoice,
            ['get' => ['setup_intent' => 'seti_attacker']],
            1,
        ]);

        expect($tx->status)->toBe(Model_Transaction::STATUS_ERROR);
    });

    test('rejects a setup intent whose metadata gateway_id does not match the current gateway', function (): void {
        $invoice = new Model_Invoice();
        $invoice->loadBean(new DummyBean());
        $invoice->id = 91;
        $invoice->client_id = 5;

        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_wrong_gateway',
            'status' => 'succeeded',
            'payment_method' => 'pm_x',
            'customer' => 'cus_x',
            'metadata' => ['invoice_id' => '91', 'gateway_id' => '2', 'price_id' => 'price_x'],
        ]);

        $setupIntentsMock = Mockery::mock();
        $setupIntentsMock->shouldReceive('retrieve')->once()->with('seti_wrong_gateway', [])->andReturn($setupIntent);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->setupIntents = $setupIntentsMock;
        $stripeMock->subscriptions = Mockery::mock();
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $tx = buildTransaction();
        $tx->id = 701;
        $this->adapter->setDi(container());

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_subscription_create');

        invokePrivateMethod($this->adapter, 'processSetupIntent', [
            $apiAdmin,
            $tx,
            $invoice,
            ['get' => ['setup_intent' => 'seti_wrong_gateway']],
            1,
        ]);

        expect($tx->status)->toBe(Model_Transaction::STATUS_ERROR);
    });

    test('proceeds when the setup intent metadata matches the resolved invoice and gateway', function (): void {
        $invoice = new Model_Invoice();
        $invoice->loadBean(new DummyBean());
        $invoice->id = 92;
        $invoice->client_id = 5;
        $invoice->currency = 'USD';

        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_matching',
            'status' => 'succeeded',
            'payment_method' => 'pm_match',
            'customer' => 'cus_match',
            'metadata' => ['invoice_id' => '92', 'gateway_id' => '1', 'price_id' => 'price_match'],
        ]);

        $setupIntentsMock = Mockery::mock();
        $setupIntentsMock->shouldReceive('retrieve')->once()->with('seti_matching', [])->andReturn($setupIntent);

        $subscription = Stripe\Subscription::constructFrom(['id' => 'sub_match', 'latest_invoice' => null]);
        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('create')->once()->andReturn($subscription);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->setupIntents = $setupIntentsMock;
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $tx = buildTransaction();
        $tx->id = 702;
        $this->adapter->setDi(container());

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_subscription_create')->once()->andReturn(1);

        invokePrivateMethod($this->adapter, 'processSetupIntent', [
            $apiAdmin,
            $tx,
            $invoice,
            ['get' => ['setup_intent' => 'seti_matching']],
            1,
        ]);

        expect($tx->status)->toBe(Model_Transaction::STATUS_PROCESSED)
            ->and($tx->s_id)->toBe('sub_match');
    });
});

describe('getOrCreateCustomer', function (): void {
    test('returns the cached customer ID without touching Stripe', function (): void {
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 50;
        $invoiceModel->client_id = 9;

        $cached = createEntity(PayGatewayCustomer::class, ['external_customer_id' => 'cus_cached']);
        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 9, true)->andReturn($cached);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = Mockery::mock(); // no expectations - must not be called
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $di = container();
        $di['em']->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($customerRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreateCustomer', [$invoiceModel]);

        expect($result)->toBe('cus_cached');
    });

    test('resolves and caches a new customer on a cache miss', function (): void {
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 51;
        $invoiceModel->client_id = 10;
        $invoiceModel->buyer_email = 'newcustomer@example.com';
        $invoiceModel->buyer_first_name = 'New';
        $invoiceModel->buyer_last_name = 'Customer';

        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 10, true)->andReturn(null);

        $customer = Stripe\Customer::constructFrom(['id' => 'cus_fresh']);
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $customersMock->shouldReceive('create')->once()->andReturn($customer);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $isolatedEm = noOpEntityManagerMock();
        $isolatedEm->shouldReceive('persist')->once()->with(Mockery::type(PayGatewayCustomer::class));
        $isolatedEm->shouldReceive('flush')->once();
        setPrivateProperty($this->adapter, 'entityManagerFactory', fn (): object => $isolatedEm);

        $di = container();
        $di['em']->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($customerRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreateCustomer', [$invoiceModel]);

        expect($result)->toBe('cus_fresh');
    });

    test('returns the winning row instead of its own resolved ID when a concurrent request caches first', function (): void {
        // Two requests for the same (gateway, client) can both miss the cache read in
        // getOrCreateCustomer() and each resolve their own Stripe customer before either persists.
        // The unique constraint stops both rows from existing; cacheGatewayCustomer() must then
        // re-read and return the winner's ID rather than the one this request resolved, so every
        // caller converges on one customer.
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 52;
        $invoiceModel->client_id = 11;
        $invoiceModel->buyer_email = 'raced@example.com';
        $invoiceModel->buyer_first_name = 'Raced';
        $invoiceModel->buyer_last_name = 'Customer';

        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 11, true)->andReturn(null);

        $customer = Stripe\Customer::constructFrom(['id' => 'cus_this_request']);
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $customersMock->shouldReceive('create')->once()->andReturn($customer);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $winningRow = createEntity(PayGatewayCustomer::class, ['external_customer_id' => 'cus_other_request_won']);
        $isolatedEmRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $isolatedEmRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 11, true)->andReturn($winningRow);
        $isolatedEm = noOpEntityManagerMock();
        $isolatedEm->shouldReceive('flush')->once()->andThrow(uniqueConstraintViolationException());
        $isolatedEm->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($isolatedEmRepo);
        setPrivateProperty($this->adapter, 'entityManagerFactory', fn (): object => $isolatedEm);

        $di = container();
        $di['em']->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($customerRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreateCustomer', [$invoiceModel]);

        expect($result)->toBe('cus_other_request_won');
    });
});

describe('getOrCreatePriceId', function (): void {
    test('returns the cached price ID without touching Stripe', function (): void {
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 60;
        $invoiceModel->currency = 'USD';

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', '1']));
        $cached = createEntity(PayGatewayProduct::class, ['external_price_id' => 'price_cached']);
        $productRepo = Mockery::mock(PayGatewayProductRepository::class);
        $productRepo->shouldReceive('findOneByGatewayAndCacheKey')->once()->with(1, $cacheKey)->andReturn($cached);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->products = Mockery::mock(); // no expectations - must not be called
        $stripeMock->prices = Mockery::mock(); // no expectations - must not be called
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('getAll')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['db'] = $dbMock;
        $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreatePriceId', [$invoiceModel]);

        expect($result)->toBe('price_cached');
    });

    test('resolves and caches a new product/price on a cache miss', function (): void {
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 61;
        $invoiceModel->currency = 'USD';

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', '1']));
        $productRepo = Mockery::mock(PayGatewayProductRepository::class);
        $productRepo->shouldReceive('findOneByGatewayAndCacheKey')->once()->with(1, $cacheKey)->andReturn(null);

        $product = Stripe\Product::constructFrom(['id' => 'prod_fresh']);
        $productsMock = Mockery::mock();
        $productsMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $productsMock->shouldReceive('create')->once()->andReturn($product);

        $price = Stripe\Price::constructFrom(['id' => 'price_fresh', 'unit_amount' => null]);
        $pricesMock = Mockery::mock();
        $pricesMock->shouldReceive('all')->once()->andReturn(Stripe\Collection::constructFrom(['data' => []]));
        $pricesMock->shouldReceive('create')->once()->andReturn($price);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->products = $productsMock;
        $stripeMock->prices = $pricesMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $isolatedEm = noOpEntityManagerMock();
        $isolatedEm->shouldReceive('persist')->once()->with(Mockery::type(PayGatewayProduct::class));
        $isolatedEm->shouldReceive('flush')->once();
        setPrivateProperty($this->adapter, 'entityManagerFactory', fn (): object => $isolatedEm);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('getAll')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['db'] = $dbMock;
        $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreatePriceId', [$invoiceModel]);

        expect($result)->toBe('price_fresh');
    });

    test('returns the winning row instead of its own resolved ID when a concurrent request caches first', function (): void {
        // Mirrors getOrCreateCustomer's equivalent case: the unique constraint stops two
        // product/price rows existing for the same (gateway, cache key), so the loser here must
        // re-read and return the winner's price ID rather than the one it just resolved.
        $invoiceModel = new Model_Invoice();
        $invoiceModel->loadBean(new DummyBean());
        $invoiceModel->id = 62;
        $invoiceModel->currency = 'USD';

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', '1']));
        $productRepo = Mockery::mock(PayGatewayProductRepository::class);
        $productRepo->shouldReceive('findOneByGatewayAndCacheKey')->once()->with(1, $cacheKey)->andReturn(null);

        $product = Stripe\Product::constructFrom(['id' => 'prod_this_request']);
        $productsMock = Mockery::mock();
        $productsMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $productsMock->shouldReceive('create')->once()->andReturn($product);

        $price = Stripe\Price::constructFrom(['id' => 'price_this_request', 'unit_amount' => null]);
        $pricesMock = Mockery::mock();
        $pricesMock->shouldReceive('all')->once()->andReturn(Stripe\Collection::constructFrom(['data' => []]));
        $pricesMock->shouldReceive('create')->once()->andReturn($price);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->products = $productsMock;
        $stripeMock->prices = $pricesMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $winningRow = createEntity(PayGatewayProduct::class, ['external_price_id' => 'price_other_request_won']);
        $isolatedEmRepo = Mockery::mock(PayGatewayProductRepository::class);
        $isolatedEmRepo->shouldReceive('findOneByGatewayAndCacheKey')->once()->with(1, $cacheKey)->andReturn($winningRow);
        $isolatedEm = noOpEntityManagerMock();
        $isolatedEm->shouldReceive('flush')->once()->andThrow(uniqueConstraintViolationException());
        $isolatedEm->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($isolatedEmRepo);
        setPrivateProperty($this->adapter, 'entityManagerFactory', fn (): object => $isolatedEm);

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('getAll')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['db'] = $dbMock;
        $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreatePriceId', [$invoiceModel]);

        expect($result)->toBe('price_other_request_won');
    });
});

describe('newIsolatedEntityManager', function (): void {
    test('memoizes the isolated EntityManager while it stays open', function (): void {
        // Resolving a customer and a product/price on the same checkout - the common case for a
        // client's first invoice - must not pay for a fresh EntityManagerFactory::create() twice.
        $callCount = 0;
        $em = noOpEntityManagerMock();
        setPrivateProperty($this->adapter, 'entityManagerFactory', function () use (&$callCount, $em): object {
            ++$callCount;

            return $em;
        });

        $first = invokePrivateMethod($this->adapter, 'newIsolatedEntityManager');
        $second = invokePrivateMethod($this->adapter, 'newIsolatedEntityManager');

        expect($first)->toBe($em)
            ->and($second)->toBe($em)
            ->and($callCount)->toBe(1);
    });

    test('rebuilds once the memoized EntityManager reports itself closed', function (): void {
        // Mirrors Doctrine's own behavior: a flush() that hits a unique constraint violation
        // leaves the EntityManager closed, regardless of what the caller does (see
        // cacheGatewayCustomer()'s docblock) - so the stale instance must not be reused.
        $closedEm = noOpEntityManagerMock();
        $closedEm->shouldReceive('isOpen')->andReturn(false);

        $freshEm = noOpEntityManagerMock();

        $queue = [$closedEm, $freshEm];
        setPrivateProperty($this->adapter, 'entityManagerFactory', function () use (&$queue): object {
            return array_shift($queue);
        });

        $first = invokePrivateMethod($this->adapter, 'newIsolatedEntityManager');
        $second = invokePrivateMethod($this->adapter, 'newIsolatedEntityManager');

        expect($first)->toBe($closedEm)
            ->and($second)->toBe($freshEm);
    });
});

describe('processWebhookEvent signature verification', function (): void {
    test('rejects webhook events missing the Stripe-Signature header', function (): void {
        $tx = buildTransaction();
        $tx->id = 502;

        $rawBody = json_encode(['type' => 'payment_intent.succeeded', 'id' => 'evt_unsigned']);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => [],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($this->adapter, 'processWebhookEvent', [
            Mockery::mock(),
            $tx,
            $data,
            1,
        ]);
    })->throws(FOSSBilling\Exception::class, 'Missing Stripe-Signature header');

    test('rejects webhook events with an invalid signature', function (): void {
        $tx = buildTransaction();
        $tx->id = 503;

        $rawBody = json_encode(['type' => 'payment_intent.succeeded', 'id' => 'evt_bad_sig']);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => ['HTTP_STRIPE_SIGNATURE' => signStripeWebhookPayload($rawBody, 'whsec_wrong_secret')],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($this->adapter, 'processWebhookEvent', [
            Mockery::mock(),
            $tx,
            $data,
            1,
        ]);
    })->throws(FOSSBilling\Exception::class, 'Invalid Stripe webhook signature');

    test('rejects webhook events when no webhook secret is configured', function (): void {
        $adapter = new Payment_Adapter_Stripe([
            'test_mode' => true,
            'test_api_key' => 'sk_test_dummy',
            'test_pub_key' => 'pk_test_dummy',
        ]);

        $tx = buildTransaction();
        $tx->id = 504;

        $rawBody = json_encode(['type' => 'payment_intent.succeeded', 'id' => 'evt_no_secret']);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => ['HTTP_STRIPE_SIGNATURE' => signStripeWebhookPayload($rawBody)],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($adapter, 'processWebhookEvent', [
            Mockery::mock(),
            $tx,
            $data,
            1,
        ]);
    })->throws(FOSSBilling\Exception::class, 'Stripe webhook signing secret is not configured');
});

describe('processWebhookEvent noise filtering', function (): void {
    test('deletes transaction for unhandled event types', function (): void {
        $tx = buildTransaction();
        $tx->id = 500;

        $event = new stdClass();
        $event->id = 'evt_noise_1';
        $event->type = 'charge.succeeded';
        $event->data = (object) ['object' => new stdClass()];

        $rawBody = json_encode(['type' => 'charge.succeeded', 'id' => 'evt_noise_1']);

        $trashCalled = false;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('trash')
            ->withArgs(function ($txArg) use (&$trashCalled): bool {
                $trashCalled = true;

                return true;
            });
        $dbMock->shouldReceive('store')->andReturn($tx->id);

        $di = container();
        $di['db'] = $dbMock;
        $this->adapter->setDi($di);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => ['HTTP_STRIPE_SIGNATURE' => signStripeWebhookPayload($rawBody)],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($this->adapter, 'processWebhookEvent', [
            Mockery::mock(),
            $tx,
            $data,
            1,
        ]);

        expect($trashCalled)->toBeTrue();
    });

    test('deletes transaction for subscription lifecycle events', function (): void {
        $tx = buildTransaction();
        $tx->id = 501;

        $rawBody = json_encode([
            'type' => 'customer.subscription.deleted',
            'id' => 'evt_life_1',
            'data' => ['object' => [
                'id' => 'sub_nonexistent',
                'metadata' => ['gateway_id' => '1'],
            ]],
        ]);

        $trashCalled = false;

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('trash')
            ->andReturnUsing(function () use (&$trashCalled): void {
                $trashCalled = true;
            });
        $dbMock->shouldReceive('store')->andReturn($tx->id);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_subscription_get');

        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldReceive('findIdBySid')->once()->with('sub_nonexistent')->andReturn(null);

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $subscriptionService);
        $this->adapter->setDi($di);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => ['HTTP_STRIPE_SIGNATURE' => signStripeWebhookPayload($rawBody)],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($this->adapter, 'processWebhookEvent', [
            $apiAdmin,
            $tx,
            $data,
            1,
        ]);

        // Subscription lifecycle events don't represent payments — their
        // transactions should be deleted to keep the list clean.
        expect($trashCalled)->toBeTrue();
    });
});

describe('Stripe webhook gateway ownership', function (): void {
    test('tags one-time payments and uses the selected gateway callback', function (): void {
        $adapter = new Payment_Adapter_Stripe([
            'test_mode' => true,
            'test_api_key' => 'sk_test_dummy',
            'test_pub_key' => 'pk_test_dummy',
            'test_webhook_secret' => TEST_WEBHOOK_SECRET,
            'gateway_id' => 3,
            'notify_url' => 'https://billing.example/ipn.php?gateway_id=3&invoice_id=15',
        ]);

        $paymentIntentsMock = Mockery::mock();
        $paymentIntentsMock->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $params, array $options): bool => $params['metadata']['gateway_id'] === '3'
                && $params['metadata']['invoice_id'] === '15'
                && $params['currency'] === 'usd'
                && $options['idempotency_key'] === sprintf(
                    'one_time_invoice_15_gateway_3_%s',
                    hash('sha256', json_encode($params, JSON_THROW_ON_ERROR))
                ))
            ->andReturn(Stripe\PaymentIntent::constructFrom([
                'id' => 'pi_gateway_3',
                'client_secret' => 'pi_gateway_3_secret',
            ]));

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->paymentIntents = $paymentIntentsMock;
        setPrivateProperty($adapter, 'stripe', $stripeMock);

        $invoice = new Model_Invoice();
        $invoice->loadBean(new DummyBean());
        $invoice->id = 15;
        $invoice->client_id = 7;
        $invoice->currency = 'USD';
        $invoice->buyer_email = 'client@example.com';
        $invoice->buyer_first_name = 'Test';
        $invoice->buyer_last_name = 'Client';
        $invoice->hash = 'invoice-hash';
        $invoice->nr = 15;
        $invoice->serie = 'INV';

        $dbMock = Mockery::mock('\\Box_Database');
        $dbMock->shouldReceive('getAll')->once()->andReturn([['title' => 'Hosting']]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(15.00);

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn () => $invoiceService);
        $adapter->setDi($di);

        $form = invokePrivateMethod($adapter, '_generateForm', [$invoice]);

        expect($form)->toContain('https://billing.example/ipn.php?gateway_id=3&invoice_id=15');
    });

    test('ignores an event created by another FOSSBilling Stripe gateway', function (): void {
        $tx = buildTransaction();
        $tx->id = 550;

        $rawBody = json_encode([
            'type' => 'payment_intent.succeeded',
            'id' => 'evt_wrong_gateway',
            'data' => ['object' => [
                'id' => 'pi_gateway_3',
                'status' => 'succeeded',
                'amount' => 1500,
                'currency' => 'usd',
                'metadata' => [
                    'invoice_id' => '10',
                    'client_id' => '3',
                    'gateway_id' => '3',
                ],
            ]],
        ]);

        $dbMock = Mockery::mock('\\Box_Database');
        $dbMock->shouldReceive('trash')->once()->with($tx);
        $dbMock->shouldNotReceive('findOne');
        $dbMock->shouldNotReceive('store');

        $di = container();
        $di['db'] = $dbMock;
        $this->adapter->setDi($di);

        $data = [
            'http_raw_post_data' => $rawBody,
            'server' => ['HTTP_STRIPE_SIGNATURE' => signStripeWebhookPayload($rawBody)],
            'get' => [],
            'post' => [],
        ];

        invokePrivateMethod($this->adapter, 'processWebhookEvent', [
            Mockery::mock(),
            $tx,
            $data,
            10,
        ]);
    });

    test('reads copied subscription metadata from recurring invoices', function (): void {
        $event = (object) [
            'type' => 'invoice.paid',
            'data' => (object) ['object' => (object) [
                'parent' => (object) [
                    'subscription_details' => (object) [
                        'metadata' => (object) ['gateway_id' => '3'],
                    ],
                ],
            ]],
        ];

        expect(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 3]))->toBeTrue()
            ->and(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 10]))->toBeFalse();
    });

    test('resolves gateway ownership from the invoice for legacy Stripe objects', function (): void {
        $event = (object) [
            'type' => 'payment_intent.succeeded',
            'data' => (object) ['object' => (object) [
                'metadata' => (object) ['invoice_id' => '10'],
            ]],
        ];

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        $dbalMock->shouldReceive('fetchOne')
            ->twice()
            ->with('SELECT gateway_id FROM invoice WHERE id = :id', ['id' => 10])
            ->andReturn('3');

        $di = container();
        $di['dbal'] = $dbalMock;
        $this->adapter->setDi($di);

        expect(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 3]))->toBeTrue()
            ->and(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 10]))->toBeFalse();
    });

    test('resolves gateway ownership from a legacy local subscription', function (): void {
        $event = (object) [
            'type' => 'customer.subscription.updated',
            'data' => (object) ['object' => (object) [
                'id' => 'sub_legacy',
            ]],
        ];

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        $dbalMock->shouldReceive('fetchOne')
            ->twice()
            ->with('SELECT pay_gateway_id FROM subscription WHERE sid = :sid', ['sid' => 'sub_legacy'])
            ->andReturn('3');

        $di = container();
        $di['dbal'] = $dbalMock;
        $this->adapter->setDi($di);

        expect(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 3]))->toBeTrue()
            ->and(invokePrivateMethod($this->adapter, 'eventBelongsToGateway', [$event, 10]))->toBeFalse();
    });
});

describe('applyOneTimePayment already-paid guard', function (): void {
    test('skips processing when invoice is already paid', function (): void {
        $tx = buildTransaction();
        $tx->id = 600;
        $tx->amount = '50.00';

        $invoice = new Model_Invoice();
        $invoice->loadBean(new DummyBean());
        $invoice->id = 42;
        $invoice->status = Model_Invoice::STATUS_PAID;

        $addFundsCalled = false;

        $clientService = Mockery::mock();
        $clientService->shouldReceive('addFunds')
            ->andReturnUsing(function () use (&$addFundsCalled): void {
                $addFundsCalled = true;
            });

        $invoiceService = Mockery::mock();
        $transactionService = Mockery::mock();

        $dbMock = Mockery::mock('\Box_Database');
        $dbMock->shouldReceive('findOne')
            ->with('Invoice', 'id = :id', Mockery::any())
            ->andReturn($invoice);

        $di = container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(function ($name, $sub = null) use ($clientService, $invoiceService, $transactionService) {
            if ($name === 'client') {
                return $clientService;
            }
            if ($name === 'Invoice' && $sub === 'Transaction') {
                return $transactionService;
            }
            if ($name === 'Invoice') {
                return $invoiceService;
            }

            return Mockery::mock();
        });
        $this->adapter->setDi($di);

        $charge = new stdClass();
        $charge->id = 'pi_test123';

        invokePrivateMethod($this->adapter, 'applyOneTimePayment', [$tx, $invoice, $charge]);

        expect($addFundsCalled)->toBeFalse();
    });
});
