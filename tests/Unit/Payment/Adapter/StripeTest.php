<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\PayGatewayCustomer;
use Box\Mod\Invoice\Entity\PayGatewayProduct;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Repository\PayGatewayCustomerRepository;
use Box\Mod\Invoice\Repository\PayGatewayProductRepository;
use Box\Mod\Invoice\Repository\TransactionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;
use function Tests\Helpers\setEntityId;

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
    $em->shouldReceive('getReference')->byDefault()->andReturnUsing(
        static fn (string $class, mixed $id): object => createEntity($class, ['id' => $id])
    );
    $em->shouldReceive('persist')->byDefault();
    $em->shouldReceive('flush')->byDefault();
    $em->shouldReceive('isOpen')->byDefault()->andReturn(true);

    return $em;
}

/**
 * A Doctrine\DBAL\Exception\UniqueConstraintViolationException wrapping a minimal fake driver
 * exception - enough to construct without a real database error.
 */
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

function expectStripeObjectLock(Mockery\MockInterface $dbalMock, string $objectId, int $gatewayId): void
{
    $lockName = 'fb:stripe:' . substr(hash('sha256', $gatewayId . ':' . $objectId), 0, 54);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:name, :timeout)', ['name' => $lockName, 'timeout' => 10])
        ->andReturn(1);
    $dbalMock->shouldReceive('executeStatement')
        ->once()
        ->with('SELECT RELEASE_LOCK(:name)', ['name' => $lockName])
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

function buildTransaction(): Transaction
{
    return createEntity(Transaction::class);
}

/**
 * @return array{em: Mockery\MockInterface, txRepo: Mockery\MockInterface, subRepo: Mockery\MockInterface, invoiceRepo: Mockery\MockInterface}
 */
function buildEntityManagerMocks(): array
{
    $txRepo = Mockery::mock(TransactionRepository::class);
    $subRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $invoiceRepo = Mockery::mock(Box\Mod\Invoice\Repository\InvoiceRepository::class);

    $txRepo->shouldReceive('find')->byDefault()->andReturn(null);
    $txRepo->shouldReceive('findOneBy')->byDefault()->andReturn(null);
    $txRepo->shouldReceive('findActiveByTxnIdAndGatewayId')->byDefault()->andReturn(null);
    $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')->byDefault()->andReturn(null);

    $subRepo->shouldReceive('find')->byDefault()->andReturn(null);
    $subRepo->shouldReceive('findOneBy')->byDefault()->andReturn(null);
    $subRepo->shouldReceive('findOneBySid')->byDefault()->andReturn(null);

    $invoiceRepo->shouldReceive('find')->byDefault()->andReturn(null);
    $invoiceRepo->shouldReceive('findByHash')->byDefault()->andReturn(null);

    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->byDefault()->andReturn([]);
    $connection->shouldReceive('fetchOne')->byDefault()->andReturn(null);
    $connection->shouldReceive('executeStatement')->byDefault()->andReturn(1);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->byDefault()->andReturnUsing(static fn (string $class): object => match ($class) {
        Transaction::class => $txRepo,
        Box\Mod\Invoice\Entity\Subscription::class => $subRepo,
        Invoice::class => $invoiceRepo,
        default => Mockery::mock(Doctrine\ORM\EntityRepository::class)->shouldIgnoreMissing(),
    });
    $em->shouldReceive('getConnection')->byDefault()->andReturn($connection);
    $em->shouldReceive('flush')->byDefault();
    $em->shouldReceive('persist')->byDefault();
    $em->shouldReceive('remove')->byDefault();

    return ['em' => $em, 'txRepo' => $txRepo, 'subRepo' => $subRepo, 'invoiceRepo' => $invoiceRepo, 'connection' => $connection];
}

describe('getStripeRecurringParams', function (): void {
    test('carries the period quantity through as interval_count', function (): void {
        $result = invokePrivateMethod($this->adapter, 'getStripeRecurringParams', ['3M']);

        expect($result)->toBe(['interval' => 'month', 'interval_count' => 3]);
    });

    test('maps a yearly period to interval_count 1', function (): void {
        $result = invokePrivateMethod($this->adapter, 'getStripeRecurringParams', ['1Y']);

        expect($result)->toBe(['interval' => 'year', 'interval_count' => 1]);
    });

    test('maps a biennial period to a 2-year interval_count', function (): void {
        $result = invokePrivateMethod($this->adapter, 'getStripeRecurringParams', ['2Y']);

        expect($result)->toBe(['interval' => 'year', 'interval_count' => 2]);
    });

    test('maps a custom day-based period', function (): void {
        $result = invokePrivateMethod($this->adapter, 'getStripeRecurringParams', ['45D']);

        expect($result)->toBe(['interval' => 'day', 'interval_count' => 45]);
    });

    test('rejects a period whose interval_count exceeds Stripe\'s 3-year cap', function (): void {
        // Billing periods allow up to 5Y, but Stripe's own recurring interval_count cap for
        // "year" is 3, so this must fail loudly instead of silently mis-billing.
        expect(fn (): mixed => invokePrivateMethod($this->adapter, 'getStripeRecurringParams', ['5Y']))
            ->toThrow(Payment_Exception::class);
    });
});

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

        $invoiceModel = createEntity(Invoice::class);

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

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->andReturn(10.00);

        $subscriptionService = Mockery::mock();
        $subscriptionService->shouldReceive('getSubscriptionPeriod')->andReturn('1M');

        ['em' => $em, 'subRepo' => $subRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $em->shouldReceive('getConnection')->andReturn($connection);
        $invoiceRepo->shouldReceive('find')->with(5)->andReturn($invoiceModel);
        $subRepo->shouldReceive('findOneBy')
            ->once()
            ->with(['sid' => 'sub_123'])
            ->andReturn(null);

        $di = container();
        $di['em'] = $em;
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
            ->and($tx->getInvoice()?->getId())->toBe(5);
    });

    test('returns false when metadata is missing', function (): void {
        $tx = buildTransaction();

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_no_meta';
        $stripeSubscription->metadata = new stdClass();

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeSubscription];

        $di = container();
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
    test('links transaction to invoice and processes without re-claiming the row', function (): void {
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

        // The service layer holds the processing claim on entry: the adapter
        // must never re-claim, or the claim fails and the payment is skipped.
        $transactionService = Mockery::mock();
        $transactionService->shouldNotReceive('claimForProcessing');

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('in_123', null, 42)
            ->andReturn(null);
        $invoiceRepo->shouldReceive('find')->atLeast()->once()->with(99)->andReturn(createEntity(Invoice::class, ['id' => 99]));
        // flush() happens when the invoice link is persisted.
        $em->shouldReceive('flush')->atLeast()->once();

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_123', 1);
        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->andReturn(false);
        $invoiceService->shouldReceive('issueInvoice')->andReturn(true);
        $invoiceService->shouldReceive('payInvoiceWithCredits')->andReturn(true);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['mod_service'] = $di->protect(function ($module, $service = null) use ($transactionService, $invoiceService) {
            if ($service === 'Transaction') {
                return $transactionService;
            }
            if ($module === 'Invoice') {
                return $invoiceService;
            }

            return Mockery::mock();
        });

        $this->adapter->setDi($di);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        // Processing proceeds: the invoice link is persisted and funds move.
        expect($tx->getInvoice()?->getId())->toBe(99);
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

        $originalInvoice = createEntity(Invoice::class);

        $originalInvoice->id = 77;
        $originalInvoice->status = Invoice::STATUS_UNPAID;
        $originalInvoice->currency = 'USD';

        // Distinguished from $originalInvoice by its currency, so the
        // identity-based matchers below prove the adapter uses this second
        // fixture (from the $isInitialPayment lookup) rather than a stale
        // reference to the first lookup result.
        $invoiceModel = createEntity(Invoice::class);

        $invoiceModel->id = 77;
        $invoiceModel->status = Invoice::STATUS_UNPAID;
        $invoiceModel->issued = 0;
        $invoiceModel->currency = 'EUR';

        $transactionService = Mockery::mock();
        $transactionService->shouldNotReceive('claimForProcessing');

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')
            ->with($invoiceModel)
            ->andReturn(false);
        $invoiceService->shouldReceive('issueInvoice')
            ->with($invoiceModel, ['use_credits' => false])
            ->andReturn(true);
        $invoiceService->shouldReceive('payInvoiceWithCredits')
            ->with($invoiceModel)
            ->andReturn(true);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')
            ->with(77)
            ->andReturn($originalInvoice, $invoiceModel);
        // Duplicate-event check — no prior transaction processed this Stripe invoice.
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('in_456', null, 50)
            ->andReturn(null);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_456', 1);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
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

        // The unpaid original invoice should be issued and paid via the fallback
        expect($tx->getInvoice()?->getId())->toBe(77);
    });
});

describe('handleInvoicePaymentSucceeded renewal after the first invoice was paid', function (): void {
    test('credits a renewal payment and settles the renewal invoice', function (): void {
        // A subscription's metadata always points at the first FOSSBilling invoice, which is paid by the time the
        // first renewal is charged. The renewal payment must still be credited and applied to the renewal invoice.
        $tx = buildTransaction();
        $tx->id = 60;

        $stripeInvoice = new stdClass();
        $stripeInvoice->id = 'in_renewal_1';
        $stripeInvoice->subscription = 'sub_renew';
        $stripeInvoice->billing_reason = 'subscription_cycle';
        $stripeInvoice->amount_paid = 2500;
        $stripeInvoice->currency = 'usd';

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeInvoice];

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_renew';
        $stripeSubscription->metadata = (object) ['invoice_id' => '77', 'client_id' => '8'];

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')->andReturn($stripeSubscription);
        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $originalInvoice = createEntity(Invoice::class);
        $originalInvoice->id = 77;
        $originalInvoice->status = Invoice::STATUS_PAID; // paid at signup
        $originalInvoice->currency = 'USD';

        $renewalInvoice = createEntity(Invoice::class);
        $renewalInvoice->id = 88;
        $renewalInvoice->status = Invoice::STATUS_UNPAID;
        $renewalInvoice->currency = 'USD';

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('generateRenewalInvoiceForSubscriptionPayment')
            ->with('sub_renew', 8)
            ->andReturn($renewalInvoice);
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->andReturn(false);
        $invoiceService->shouldReceive('payInvoiceWithCredits')->andReturn(true);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')->with(77)->andReturn($originalInvoice);
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->with('in_renewal_1', null, 60)
            ->andReturn(null);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_renewal_1', 1);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $processed = invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [$apiAdmin, $tx, $event, 1]);

        expect($processed)->toBeTrue();
        expect($tx->getInvoice()?->getId())->toBe(88);
    });

    test('skips a duplicate initial payment when the original invoice is already paid', function (): void {
        // The redirect flow pays the first invoice immediately, so when the
        // invoice.paid webhook for the initial payment arrives the original
        // invoice is already paid. It must not be credited a second time and
        // must not mint a renewal invoice.
        $tx = buildTransaction();
        $tx->id = 61;

        $stripeInvoice = new stdClass();
        $stripeInvoice->id = 'in_init_dup';
        $stripeInvoice->subscription = 'sub_dup';
        $stripeInvoice->billing_reason = 'subscription_create';
        $stripeInvoice->amount_paid = 1500;
        $stripeInvoice->currency = 'usd';

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeInvoice];

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_dup';
        $stripeSubscription->metadata = (object) ['invoice_id' => '99', 'client_id' => '5'];

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')->andReturn($stripeSubscription);
        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $originalInvoice = createEntity(Invoice::class);
        $originalInvoice->id = 99;
        $originalInvoice->status = Invoice::STATUS_PAID;
        $originalInvoice->currency = 'USD';

        $invoiceService = Mockery::mock();
        $invoiceService->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment', 'payInvoiceWithCredits');

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')->with(99)->andReturn($originalInvoice);
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->with('in_init_dup', null, 61)
            ->andReturn(null);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_init_dup', 1);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $processed = invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [$apiAdmin, $tx, $event, 1]);

        expect($processed)->toBeFalse();
    });

    test('skips an inconclusive billing_reason when the original invoice is already paid', function (): void {
        // A missing billing_reason with a paid original is ambiguous: it could
        // be a duplicate initial webhook or a renewal. Skipping avoids
        // double-crediting the initial payment and minting a spurious renewal.
        $tx = buildTransaction();
        $tx->id = 62;

        $stripeInvoice = new stdClass();
        $stripeInvoice->id = 'in_missing_reason';
        $stripeInvoice->subscription = 'sub_missing_reason';
        $stripeInvoice->amount_paid = 1500;
        $stripeInvoice->currency = 'usd';

        $event = new stdClass();
        $event->data = (object) ['object' => $stripeInvoice];

        $stripeSubscription = new stdClass();
        $stripeSubscription->id = 'sub_missing_reason';
        $stripeSubscription->metadata = (object) ['invoice_id' => '99', 'client_id' => '5'];

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('retrieve')->andReturn($stripeSubscription);
        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $originalInvoice = createEntity(Invoice::class);
        $originalInvoice->id = 99;
        $originalInvoice->status = Invoice::STATUS_PAID;
        $originalInvoice->currency = 'USD';

        $invoiceService = Mockery::mock();
        $invoiceService->shouldNotReceive('generateRenewalInvoiceForSubscriptionPayment', 'payInvoiceWithCredits');

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('client_balance_add_funds');

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')->with(99)->andReturn($originalInvoice);
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->with('in_missing_reason', null, 62)
            ->andReturn(null);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_missing_reason', 1);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $processed = invokePrivateMethod($this->adapter, 'handleInvoicePaymentSucceeded', [$apiAdmin, $tx, $event, 1]);

        expect($processed)->toBeFalse();
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

        $invoiceModel = createEntity(Invoice::class);

        $invoiceModel->id = 42;
        $invoiceModel->status = Invoice::STATUS_UNPAID;
        $invoiceModel->issued = 0;

        $transactionService = Mockery::mock();
        $transactionService->shouldNotReceive('claimForProcessing');

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')
            ->andReturn(false);
        $invoiceService->shouldReceive('issueInvoice')
            ->andReturn(true);
        $invoiceService->shouldReceive('payInvoiceWithCredits')
            ->andReturn(true);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('client_balance_add_funds')->once();

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')->with(42)->andReturn($invoiceModel);
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('in_1TnBdC', null, 101)
            ->andReturn(null);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'in_1TnBdC', 4);
        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
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

        expect($tx->getInvoice()?->getId())->toBe(42);
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
        $existingTx->status = Transaction::STATUS_PROCESSED;
        $existingTx->invoice = createEntity(Invoice::class, ['id' => 10]);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'pi_existing', 1);

        ['em' => $em, 'txRepo' => $txRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('pi_existing', 1, 200)
            ->andReturn($existingTx);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handlePaymentIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->getInvoice()?->getId())->toBe(10)
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

        $invoiceModel = createEntity(Invoice::class);

        $invoiceModel->id = 15;
        $invoiceModel->issued = 1;
        $invoiceModel->client_id = 7;

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'pi_new', 1);
        $clientModel = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);

        $transactionService = Mockery::mock();
        $transactionService->shouldNotReceive('claimForProcessing');

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

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
        $clientRepo->shouldReceive('find')->with(7)->andReturn($clientModel);
        $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);
        $invoiceRepo->shouldReceive('find')
            ->with(15)
            ->andReturn($invoiceModel, null);
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('pi_new', 1, 201)
            ->andReturn(null);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
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

        expect($tx->getInvoice()?->getId())->toBe(15)
            ->and($tx->status)->toBe(Transaction::STATUS_PROCESSED);
    });
});

describe('processPaymentIntent', function (): void {
    test('validates a redirect payment amount as a float', function (): void {
        $invoice = createEntity(Invoice::class, [
            'id' => 15,
            'client_id' => 7,
            'issued' => true,
        ]);

        $tx = buildTransaction();
        $tx->id = 402;
        $tx->gateway = createEntity(PayGateway::class, ['id' => 4]);
        $tx->invoice = $invoice;

        $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 7]);
        $charge = (object) [
            'id' => 'pi_redirect',
            'status' => 'succeeded',
            'amount' => 2999,
            'currency' => 'usd',
        ];

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoice)->andReturn(29.99);
        $invoiceService->shouldReceive('validatePaymentAmount')
            ->once()
            ->with(Mockery::on(fn (mixed $amount): bool => is_float($amount) && $amount === 29.99), 29.99)
            ->andReturn(null);
        $invoiceService->shouldReceive('isInvoiceTypeDeposit')->once()->with($invoice)->andReturn(false);
        $invoiceService->shouldReceive('payInvoiceWithCredits')->once()->with($invoice)->andReturn(true);

        $transactionService = Mockery::mock();
        $transactionService->shouldNotReceive('claimForProcessing');

        $clientService = Mockery::mock();
        $clientService->shouldReceive('addFunds')->once();

        ['em' => $em, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $invoiceRepo->shouldReceive('find')->with(15)->andReturn($invoice);
        $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
        $clientRepo->shouldReceive('find')->once()->with(7)->andReturn($client);
        $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);

        $di = container();
        $di['em'] = $em;
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => match (true) {
            $service === 'Transaction' => $transactionService,
            $module === 'client' => $clientService,
            default => $invoiceService,
        });
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'processPaymentIntentUnderLock', [$tx, $invoice, $charge]);

        expect($tx->status)->toBe(Transaction::STATUS_PROCESSED);
    });

    test('deletes the redirect transaction when the webhook already recorded the PaymentIntent', function (): void {
        $tx = buildTransaction();
        $tx->id = 401;
        $tx->gateway = createEntity(PayGateway::class, ['id' => 4]);

        $existingTx = buildTransaction();

        $paymentIntent = Stripe\PaymentIntent::constructFrom([
            'id' => 'pi_webhook_first',
            'status' => 'succeeded',
            'amount' => 2500,
            'currency' => 'usd',
            'metadata' => ['gateway_id' => '4'],
        ]);

        $paymentIntentsMock = Mockery::mock();
        $paymentIntentsMock->shouldReceive('retrieve')
            ->once()
            ->with('pi_webhook_first', [])
            ->andReturn($paymentIntent);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->paymentIntents = $paymentIntentsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'pi_webhook_first', 4);

        ['em' => $em, 'txRepo' => $txRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findActiveByTxnIdAndGatewayId')
            ->once()
            ->with('pi_webhook_first', 4, 401)
            ->andReturn($existingTx);
        $em->shouldReceive('remove')->once()->with($tx);
        $em->shouldReceive('flush')->once();

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'processPaymentIntent', [
            $tx,
            null,
            ['get' => ['payment_intent' => 'pi_webhook_first']],
        ]);

        expect($tx->txn_id)->toBe('pi_webhook_first');
    });

    test('rejects redirect PaymentIntents that are not bound to the invoice', function (array $paymentIntent): void {
        $tx = buildTransaction();
        $tx->setGateway(createEntity(PayGateway::class, ['id' => 4]));

        $invoice = createEntity(Invoice::class, [
            'id' => 15,
            'currency' => 'USD',
        ]);

        $paymentIntentsMock = Mockery::mock();
        $paymentIntentsMock->shouldReceive('retrieve')
            ->once()
            ->with('pi_unbound', [])
            ->andReturn(Stripe\PaymentIntent::constructFrom($paymentIntent));

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->paymentIntents = $paymentIntentsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        expect(fn (): mixed => invokePrivateMethod($this->adapter, 'processPaymentIntent', [
            $tx,
            $invoice,
            ['get' => ['payment_intent' => 'pi_unbound']],
        ]))->toThrow(FOSSBilling\Exception::class);
    })->with([
        'wrong invoice' => [[
            'id' => 'pi_unbound',
            'currency' => 'usd',
            'metadata' => ['invoice_id' => '99', 'gateway_id' => '4'],
        ]],
        'wrong gateway' => [[
            'id' => 'pi_unbound',
            'currency' => 'usd',
            'metadata' => ['invoice_id' => '15', 'gateway_id' => '8'],
        ]],
        'wrong currency' => [[
            'id' => 'pi_unbound',
            'currency' => 'jpy',
            'metadata' => ['invoice_id' => '15', 'gateway_id' => '4'],
        ]],
    ]);
});

describe('processSetupIntent', function (): void {
    test('rejects a setup intent whose metadata invoice_id does not match the resolved invoice', function (): void {
        // Regression coverage: unlike the webhook flow (which derives the invoice from the setup
        // intent's own metadata), the redirect flow resolves $invoice and $setupIntent
        // independently, from separate query parameters on the redirect URL (see
        // resolveInvoice()). Without this check, a request naming a victim's invoice_id alongside
        // the requester's own completed setup intent would subscribe/charge the requester but
        // credit and mark paid whatever invoice_id was supplied.
        $victimInvoice = createEntity(Invoice::class, ['id' => 90, 'client_id' => 5]);

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

        expect($tx->status)->toBe(Transaction::STATUS_ERROR);
    });

    test('rejects a setup intent whose metadata gateway_id does not match the current gateway', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 91, 'client_id' => 5]);

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

        expect($tx->status)->toBe(Transaction::STATUS_ERROR);
    });

    test('proceeds when the setup intent metadata matches the resolved invoice and gateway', function (): void {
        $invoice = createEntity(Invoice::class, ['id' => 92, 'client_id' => 5, 'currency' => 'USD']);

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

        expect($tx->status)->toBe(Transaction::STATUS_PROCESSED)
            ->and($tx->s_id)->toBe('sub_match');
    });
});

describe('createStripeSubscription', function (): void {
    test('changes the idempotency key when the resolved price differs for the same invoice', function (): void {
        // Regression coverage for https://github.com/FOSSBilling/FOSSBilling/issues/4228: the
        // same invoice can produce setup intents bound to different prices over time (e.g. a
        // stale setup intent's late webhook arriving after a reload already completed checkout
        // with a different one) - reusing a plain invoice-id idempotency key across that would
        // hit an idempotency_error instead of the expected/handled idempotency_key_in_use race.
        $invoice = createEntity(Invoice::class, ['id' => 93, 'client_id' => 5]);
        $setupIntent = Stripe\SetupIntent::constructFrom(['id' => 'seti_x', 'payment_method' => 'pm_x']);

        $capturedKeys = [];
        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('create')
            ->twice()
            ->with(Mockery::any(), Mockery::on(function (array $options) use (&$capturedKeys): bool {
                $capturedKeys[] = $options['idempotency_key'] ?? null;

                return true;
            }))
            ->andReturn(Stripe\Subscription::constructFrom(['id' => 'sub_x']));

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->subscriptions = $subscriptionsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);
        $this->adapter->setDi(container());

        invokePrivateMethod($this->adapter, 'createStripeSubscription', ['cus_a', 'price_a', $setupIntent, $invoice]);
        invokePrivateMethod($this->adapter, 'createStripeSubscription', ['cus_a', 'price_b', $setupIntent, $invoice]);

        expect($capturedKeys)->toHaveCount(2)
            ->and($capturedKeys[0])->not->toBe($capturedKeys[1]);
    });
});

test('releases the Stripe object lock when processing fails', function (): void {
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    expectStripeObjectLock($dbalMock, 'pi_failure', 2);

    $di = container();
    $di['dbal'] = $dbalMock;
    $this->adapter->setDi($di);

    expect(fn (): mixed => invokePrivateMethod($this->adapter, 'withStripeObjectLock', [
        'pi_failure',
        2,
        fn () => throw new RuntimeException('Processing failed'),
    ]))->toThrow(RuntimeException::class, 'Processing failed');
});

test('logs Stripe object lock timeouts with lock context', function (): void {
    $lockName = 'fb:stripe:' . substr(hash('sha256', '2:pi_timeout'), 0, 54);
    $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
    $dbalMock->shouldReceive('getDatabasePlatform')
        ->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $dbalMock->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT GET_LOCK(:name, :timeout)', ['name' => $lockName, 'timeout' => 10])
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
        ->and($logger->calls[0]['params'][0])->toBe('Timed out after {duration_ms} ms waiting for Stripe object lock {lock_name}')
        ->and($logger->calls[0]['params'][1]['lock_name'])->toBe($lockName);
});

describe('_generateSubscriptionForm', function (): void {
    test('serializes concurrent form generation per invoice and reuses the setup intent via idempotency key', function (): void {
        // Related hardening for https://github.com/FOSSBilling/FOSSBilling/issues/4228:
        // this is the sole place the Stripe customer/product/price are first
        // resolved, but the checkout page can be loaded more than once for
        // the same invoice (reload, duplicate tab, back/forward). Locking on
        // the invoice ID avoids repeated loads redundantly repeating the
        // same cache lookups and Stripe calls, and the idempotency key on
        // setupIntents->create() makes a repeated load reuse the very same
        // setup intent instead of creating another one.
        $invoiceModel = createEntity(Invoice::class, [
            'id' => 40,
            'currency' => 'USD',
            'buyer_email' => 'lock@example.com',
            'buyer_first_name' => 'Lock',
            'buyer_last_name' => 'Test',
        ]);

        $customer = Stripe\Customer::constructFrom(['id' => 'cus_lock']);
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->once()->andReturn(
            Stripe\SearchResult::constructFrom(['data' => [$customer]])
        );

        $product = Stripe\Product::constructFrom(['id' => 'prod_lock']);
        $productsMock = Mockery::mock();
        $productsMock->shouldReceive('search')->once()->andReturn(
            Stripe\SearchResult::constructFrom(['data' => [$product]])
        );

        $price = Stripe\Price::constructFrom(['id' => 'price_lock', 'unit_amount' => null]);
        $pricesMock = Mockery::mock();
        $pricesMock->shouldReceive('all')->once()->andReturn(
            Stripe\Collection::constructFrom(['data' => []])
        );
        $pricesMock->shouldReceive('create')->once()->andReturn($price);

        $setupIntent = Stripe\SetupIntent::constructFrom(['id' => 'seti_lock', 'client_secret' => 'seti_lock_secret']);
        $setupIntentsMock = Mockery::mock();
        $capturedParams = null;
        $setupIntentsMock->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(function (array $params) use (&$capturedParams): bool {
                    $capturedParams = $params;

                    return true;
                }),
                Mockery::on(function (array $options) use (&$capturedParams): bool {
                    // The key is hashed from the actual request params (like _generateForm()
                    // does for one-time payments), not just the invoice ID, so it changes if a
                    // repeated load resolves a different customer/price for this invoice.
                    $expectedKey = sprintf(
                        'setup_invoice_40_gateway_1_%s',
                        hash('sha256', json_encode($capturedParams, JSON_THROW_ON_ERROR))
                    );

                    return ($options['idempotency_key'] ?? null) === $expectedKey;
                })
            )
            ->andReturn($setupIntent);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        $stripeMock->products = $productsMock;
        $stripeMock->prices = $pricesMock;
        $stripeMock->setupIntents = $setupIntentsMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        // getOrCreateCustomer() skips its own cache lookup here since this
        // invoice has no client_id (see its docblock) - that path is
        // covered by its own dedicated tests below.
        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 'gen_subscription_form_invoice_40', 1);
        $dbalMock->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['dbal'] = $dbalMock;
        $this->adapter->setDi($di);

        $html = invokePrivateMethod($this->adapter, '_generateSubscriptionForm', [$invoiceModel]);

        expect($html)->toContain('seti_lock_secret');
    });

    test('changes the idempotency key when the resolved customer/price differ for the same invoice', function (): void {
        // Regression coverage for https://github.com/FOSSBilling/FOSSBilling/issues/4228: reusing
        // a plain invoice-id idempotency key across two different resolved customer/price pairs
        // (e.g. an admin edits the invoice's items/amount between two checkout page loads) is
        // exactly the parameter-mismatch bug this file exists to avoid, so the key must be hashed
        // from the actual request params too - matching _generateForm()'s existing pattern.
        $resolveKeyFor = function (string $externalCustomerId, string $externalPriceId): ?string {
            $adapter = new Payment_Adapter_Stripe([
                'test_mode' => true,
                'test_api_key' => 'sk_test_dummy',
                'test_pub_key' => 'pk_test_dummy',
                'test_webhook_secret' => TEST_WEBHOOK_SECRET,
                'gateway_id' => 1,
            ]);

            $invoiceModel = createEntity(Invoice::class, [
                'id' => 41,
                'currency' => 'USD',
                'buyer_email' => 'reprice@example.com',
                'buyer_first_name' => 'Re',
                'buyer_last_name' => 'Price',
                'client_id' => 20,
            ]);

            $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
            $customerRepo->shouldReceive('findOneByGatewayAndClient')->andReturn(
                createEntity(PayGatewayCustomer::class, ['external_customer_id' => $externalCustomerId])
            );
            $productRepo = Mockery::mock(PayGatewayProductRepository::class);
            $productRepo->shouldReceive('findOneByGatewayAndCacheKey')->andReturn(
                createEntity(PayGatewayProduct::class, ['external_price_id' => $externalPriceId])
            );

            $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
            $connection->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

            $di = container();
            $di['em']->shouldReceive('getConnection')->andReturn($connection);
            $di['em']->shouldReceive('getRepository')->with(PayGatewayCustomer::class)->andReturn($customerRepo);
            $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
            $adapter->setDi($di);

            $capturedKey = null;
            $setupIntentsMock = Mockery::mock();
            $setupIntentsMock->shouldReceive('create')
                ->once()
                ->with(Mockery::any(), Mockery::on(function (array $options) use (&$capturedKey): bool {
                    $capturedKey = $options['idempotency_key'] ?? null;

                    return true;
                }))
                ->andReturn(Stripe\SetupIntent::constructFrom(['id' => 'seti_x', 'client_secret' => 'secret_x']));

            $stripeMock = Mockery::mock(StripeClient::class);
            $stripeMock->setupIntents = $setupIntentsMock;
            setPrivateProperty($adapter, 'stripe', $stripeMock);

            invokePrivateMethod($adapter, 'generateSubscriptionFormUnderLock', [$invoiceModel]);

            return $capturedKey;
        };

        $firstKey = $resolveKeyFor('cus_a', 'price_a');
        $secondKey = $resolveKeyFor('cus_a', 'price_b');

        expect($firstKey)->not->toBeNull()
            ->and($secondKey)->not->toBeNull()
            ->and($firstKey)->not->toBe($secondKey);
    });
});

describe('getOrCreateCustomer', function (): void {
    test('returns the cached customer ID without touching Stripe', function (): void {
        $invoiceModel = createEntity(Invoice::class, ['id' => 50, 'client_id' => 9]);

        $cached = createEntity(PayGatewayCustomer::class, ['external_customer_id' => 'cus_cached']);
        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 9)->andReturn($cached);

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
        $invoiceModel = createEntity(Invoice::class, [
            'id' => 51,
            'client_id' => 10,
            'buyer_email' => 'newcustomer@example.com',
            'buyer_first_name' => 'New',
            'buyer_last_name' => 'Customer',
        ]);

        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 10)->andReturn(null);

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
        // The unique constraint (see PayGatewayCustomerRepositoryTest) stops both rows from
        // existing; cacheGatewayCustomer() must then re-read and return the winner's ID rather
        // than the one this request resolved, so every caller converges on one customer.
        $invoiceModel = createEntity(Invoice::class, [
            'id' => 52,
            'client_id' => 11,
            'buyer_email' => 'raced@example.com',
            'buyer_first_name' => 'Raced',
            'buyer_last_name' => 'Customer',
        ]);

        $customerRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $customerRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 11)->andReturn(null);

        $customer = Stripe\Customer::constructFrom(['id' => 'cus_this_request']);
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $customersMock->shouldReceive('create')->once()->andReturn($customer);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $winningRow = createEntity(PayGatewayCustomer::class, ['external_customer_id' => 'cus_other_request_won']);
        $isolatedEmRepo = Mockery::mock(PayGatewayCustomerRepository::class);
        $isolatedEmRepo->shouldReceive('findOneByGatewayAndClient')->once()->with(1, 11)->andReturn($winningRow);
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

    test('resolves without caching when the invoice has no client', function (): void {
        $invoiceModel = createEntity(Invoice::class, [
            'id' => 53,
            'buyer_email' => 'guest@example.com',
            'buyer_first_name' => 'Guest',
            'buyer_last_name' => 'Buyer',
        ]);

        $customer = Stripe\Customer::constructFrom(['id' => 'cus_guest']);
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->once()->andReturn(Stripe\SearchResult::constructFrom(['data' => []]));
        $customersMock->shouldReceive('create')->once()->andReturn($customer);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $di = container();
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreateCustomer', [$invoiceModel]);

        expect($result)->toBe('cus_guest');
    });
});

describe('getOrCreatePriceId', function (): void {
    test('returns the cached price ID without touching Stripe', function (): void {
        $invoiceModel = createEntity(Invoice::class, ['id' => 60, 'currency' => 'USD']);

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', 1]));
        $cached = createEntity(PayGatewayProduct::class, ['external_price_id' => 'price_cached']);
        $productRepo = Mockery::mock(PayGatewayProductRepository::class);
        $productRepo->shouldReceive('findOneByGatewayAndCacheKey')->once()->with(1, $cacheKey)->andReturn($cached);

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->products = Mockery::mock(); // no expectations - must not be called
        $stripeMock->prices = Mockery::mock(); // no expectations - must not be called
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['em']->shouldReceive('getConnection')->andReturn($connection);
        $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreatePriceId', [$invoiceModel]);

        expect($result)->toBe('price_cached');
    });

    test('resolves and caches a new product/price on a cache miss', function (): void {
        $invoiceModel = createEntity(Invoice::class, ['id' => 61, 'currency' => 'USD']);

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', 1]));
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

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['em']->shouldReceive('getConnection')->andReturn($connection);
        $di['em']->shouldReceive('getRepository')->with(PayGatewayProduct::class)->andReturn($productRepo);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'getOrCreatePriceId', [$invoiceModel]);

        expect($result)->toBe('price_fresh');
    });

    test('returns the winning row instead of its own resolved ID when a concurrent request caches first', function (): void {
        // Mirrors getOrCreateCustomer's equivalent case: the unique constraint stops two
        // product/price rows existing for the same (gateway, cache key), so the loser here must
        // re-read and return the winner's price ID rather than the one it just resolved.
        $invoiceModel = createEntity(Invoice::class, ['id' => 62, 'currency' => 'USD']);

        $cacheKey = hash('sha256', implode('|', ['Test Product', 'usd', 0, 'month', 1]));
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

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('fetchAllAssociative')->andReturn([['title' => 'Test Product']]);

        $di = container();
        $di['em']->shouldReceive('getConnection')->andReturn($connection);
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
        $existingTx->status = Transaction::STATUS_PROCESSED;
        $existingTx->invoice = createEntity(Invoice::class, ['id' => 20]);

        ['em' => $em, 'txRepo' => $txRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findProcessingOrProcessedByTxnId')
            ->once()
            ->with('seti_existing')
            ->andReturn($existingTx);

        $di = container();
        $di['em'] = $em;
        $this->adapter->setDi($di);

        invokePrivateMethod($this->adapter, 'handleSetupIntentSucceededWebhook', [
            Mockery::mock(),
            $tx,
            $event,
            1,
        ]);

        expect($tx->getInvoice()?->getId())->toBe(20);
    });

    test('creates subscription when not already handled', function (): void {
        $tx = buildTransaction();
        $tx->id = 301;

        // Mirrors what _generateSubscriptionForm() actually creates: a setup
        // intent with the customer already attached and the resolved price
        // ID stashed in metadata.
        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_new',
            'object' => 'setup_intent',
            'status' => 'succeeded',
            'payment_method' => 'pm_456',
            'customer' => 'cus_existing',
            'metadata' => ['invoice_id' => '25', 'price_id' => 'price_existing'],
        ]);

        $event = new stdClass();
        $event->data = (object) ['object' => $setupIntent];

        $invoiceModel = createEntity(Invoice::class);

        $invoiceModel->id = 25;
        $invoiceModel->currency = 'USD';
        $invoiceModel->buyer_email = 'test@example.com';
        $invoiceModel->buyer_first_name = 'Test';
        $invoiceModel->buyer_last_name = 'User';

        $subscription = Stripe\Subscription::constructFrom(['id' => 'sub_new_123']);

        $subscriptionsMock = Mockery::mock();
        $subscriptionsMock->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $params): bool => $params['customer'] === 'cus_existing'
                    && $params['items'] === [['price' => 'price_existing']]),
                Mockery::any()
            )
            ->andReturn($subscription);

        // The customer and price are already known from the setup intent, so
        // resolving them must not fall back to Stripe's Search API - doing so
        // is what let the redirect and webhook flows resolve different
        // customers/products for the same invoice and made Stripe reject the
        // second createStripeSubscription() call as an idempotency-key
        // mismatch (the bug this test guards against).
        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->never();
        $customersMock->shouldReceive('create')->never();

        $productsMock = Mockery::mock();
        $productsMock->shouldReceive('search')->never();
        $productsMock->shouldReceive('create')->never();

        $pricesMock = Mockery::mock();
        $pricesMock->shouldReceive('all')->never();
        $pricesMock->shouldReceive('create')->never();

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        $stripeMock->products = $productsMock;
        $stripeMock->prices = $pricesMock;
        $stripeMock->subscriptions = $subscriptionsMock;

        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        $di = container();
        $invoiceRepo = $di['em']->getRepository(Invoice::class);
        $invoiceRepo->shouldReceive('find')
            ->with(25)
            ->andReturn($invoiceModel);
        $this->adapter->setDi($di);

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldReceive('invoice_subscription_create')->once()->andReturn(1);

        invokePrivateMethod($this->adapter, 'handleSetupIntentSucceededWebhook', [
            $apiAdmin,
            $tx,
            $event,
            1,
        ]);

        expect($tx->getInvoice()?->getId())->toBe(25)
            ->and($tx->s_id)->toBe('sub_new_123');
    });

    test('redirect and webhook flows resolve identical customer/price for the same setup intent', function (): void {
        // Regression test for https://github.com/FOSSBilling/FOSSBilling/issues/4228:
        // both flows independently re-derived the Stripe customer (via the
        // eventually-consistent Search API) and product/price, so a race
        // between them could create duplicate Stripe customers/products and
        // then submit different parameters under the same subscription
        // idempotency key, which Stripe rejects with an idempotency_error.
        // Both flows must now resolve the customer/price straight from the
        // setup intent, so calling the resolvers twice (simulating the
        // webhook and redirect flows racing on the same setup intent) must
        // always agree, without ever touching Stripe's Search API.
        $setupIntent = Stripe\SetupIntent::constructFrom([
            'id' => 'seti_race',
            'object' => 'setup_intent',
            'status' => 'succeeded',
            'payment_method' => 'pm_789',
            'customer' => 'cus_race',
            'metadata' => ['invoice_id' => '30', 'price_id' => 'price_race'],
        ]);

        $invoiceModel = createEntity(Invoice::class);
        $invoiceModel->id = 30;

        $customersMock = Mockery::mock();
        $customersMock->shouldReceive('search')->never();
        $customersMock->shouldReceive('create')->never();

        $productsMock = Mockery::mock();
        $productsMock->shouldReceive('search')->never();
        $productsMock->shouldReceive('create')->never();

        $pricesMock = Mockery::mock();
        $pricesMock->shouldReceive('all')->never();
        $pricesMock->shouldReceive('create')->never();

        $stripeMock = Mockery::mock(StripeClient::class);
        $stripeMock->customers = $customersMock;
        $stripeMock->products = $productsMock;
        $stripeMock->prices = $pricesMock;
        setPrivateProperty($this->adapter, 'stripe', $stripeMock);

        // Call each resolver twice, as the webhook and redirect flows would.
        $customerIdFromWebhook = invokePrivateMethod($this->adapter, 'resolveSubscriptionCustomerId', [$setupIntent, $invoiceModel]);
        $customerIdFromRedirect = invokePrivateMethod($this->adapter, 'resolveSubscriptionCustomerId', [$setupIntent, $invoiceModel]);
        $priceIdFromWebhook = invokePrivateMethod($this->adapter, 'resolveSubscriptionPriceId', [$setupIntent, $invoiceModel]);
        $priceIdFromRedirect = invokePrivateMethod($this->adapter, 'resolveSubscriptionPriceId', [$setupIntent, $invoiceModel]);

        expect($customerIdFromWebhook)->toBe('cus_race')
            ->and($customerIdFromWebhook)->toBe($customerIdFromRedirect)
            ->and($priceIdFromWebhook)->toBe('price_race')
            ->and($priceIdFromWebhook)->toBe($priceIdFromRedirect);
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

        $rawBody = json_encode(['type' => 'charge.succeeded', 'id' => 'evt_noise_1']);

        ['em' => $em] = buildEntityManagerMocks();
        $em->shouldReceive('remove')->once()->with($tx);
        $em->shouldReceive('flush')->once();

        $di = container();
        $di['em'] = $em;
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

        $apiAdmin = Mockery::mock();
        $apiAdmin->shouldNotReceive('invoice_subscription_get');

        $subscriptionService = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
        $subscriptionService->shouldReceive('findIdBySid')->once()->with('sub_nonexistent')->andReturn(null);

        ['em' => $em] = buildEntityManagerMocks();
        $em->shouldReceive('remove')->once()->with($tx);
        $em->shouldReceive('flush')->once();

        $di = container();
        $di['em'] = $em;
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

        $invoice = createEntity(Invoice::class);
        $invoice->id = 15;
        $invoice->client_id = 7;
        $invoice->currency = 'USD';
        $invoice->buyer_email = 'client@example.com';
        $invoice->buyer_first_name = 'Test';
        $invoice->buyer_last_name = 'Client';
        $invoice->hash = 'invoice-hash';
        $invoice->nr = 15;
        $invoice->serie = 'INV';

        $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
        $connection->shouldReceive('fetchAllAssociative')->once()->andReturn([['title' => 'Hosting']]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(15.00);

        $di = container();
        $di['em']->shouldReceive('getConnection')->andReturn($connection);
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

        ['em' => $em] = buildEntityManagerMocks();
        $em->shouldReceive('remove')->once()->with($tx);
        $em->shouldReceive('flush')->once();

        $di = container();
        $di['em'] = $em;
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

        $invoice = createEntity(Invoice::class);
        $invoice->id = 42;
        $invoice->status = Invoice::STATUS_PAID;

        $addFundsCalled = false;

        $clientService = Mockery::mock();
        $clientService->shouldReceive('addFunds')
            ->andReturnUsing(function () use (&$addFundsCalled): void {
                $addFundsCalled = true;
            });

        $invoiceService = Mockery::mock();
        $transactionService = Mockery::mock();

        $di = container();
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

test('checkout buyer details are encoded as JS string literals', function (): void {
    // HTML entities are never decoded inside <script>, so HTML-escaping would
    // corrupt the data sent to Stripe (issue #4305).
    $encoded = invokePrivateMethod($this->adapter, 'encodeJsString', ["O'Brien & <Sons>"]);

    expect($encoded)->toBe('"O\u0027Brien \u0026 \u003CSons\u003E"')
        ->and(json_decode($encoded))->toBe("O'Brien & <Sons>")
        ->and($encoded)->not->toContain('</script>');
});

describe('processTransaction payload handling', function (): void {
    test('rejects an empty payload with a clear error instead of stalling', function (): void {
        $tx = createEntity(Transaction::class, ['id' => 7]);

        ['em' => $em, 'txRepo' => $txRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('find')->with(7)->andReturn($tx);

        $di = container();
        $di['em'] = $em;
        $this->adapter->setDi($di);

        $apiAdmin = (new ReflectionClass(FOSSBilling\Api\Proxy::class))->newInstanceWithoutConstructor();

        try {
            $this->adapter->processTransaction($apiAdmin, 7, [], 1);
            expect(false)->toBeTrue('empty Stripe payloads must throw');
        } catch (Payment_Exception $e) {
            expect($e->getMessage())->toBe('Stripe payment data is missing.')
                ->and($e->getCode())->toBe(7020);
        }
    });
});

describe('getAmountInMinorUnits follows Stripe currency rules', function (): void {
    // Stripe lists "BIF CLP DJF GNF JPY KMF KRW MGA PYG RWF UGX VND VUV XAF XOF XPF" as zero-decimal, but its
    // special-cases row requires UGX charges ×100, like ISK (https://docs.stripe.com/currencies). Symfony Intl
    // reports COP, HUF, IDR, ISK, PKR, UGX and others with no decimals, but Stripe expects them ×100.
    test('sends the invoice total in the unit Stripe expects', function (string $currency, int $expected): void {
        $invoice = createEntity(Invoice::class);
        $invoice->id = 5;
        $invoice->currency = $currency;

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->with($invoice)->andReturn(1000.0);
        $di = container();
        $di['mod_service'] = $di->protect(fn ($module) => $invoiceService);
        $this->adapter->setDi($di);

        expect($this->adapter->getAmountInMinorUnits($invoice))->toBe($expected);
    })->with([
        'USD' => ['USD', 100000],
        'JPY (zero-decimal)' => ['JPY', 1000],
        'KWD (three-decimal)' => ['KWD', 1000000],
        'BHD (three-decimal)' => ['BHD', 1000000],
        'COP' => ['COP', 100000],
        'HUF' => ['HUF', 100000],
        'IDR' => ['IDR', 100000],
        'ISK (two-decimal representation)' => ['ISK', 100000],
        'PKR' => ['PKR', 100000],
        'UGX (two-decimal representation)' => ['UGX', 100000],
        'AFN' => ['AFN', 100000],
        'YER' => ['YER', 100000],
        'TWD (two-decimal for charges)' => ['TWD', 100000],
        'unknown code falls back to two decimals' => ['XXX', 100000],
        'lowercase USD' => ['usd', 100000],
        'lowercase JPY' => ['jpy', 1000],
    ]);

    test('reads Stripe amounts back in the same unit', function (string $currency, int $minorUnits, float $expected): void {
        expect(invokePrivateMethod($this->adapter, 'getAmountFromMinorUnits', [$minorUnits, $currency]))->toBe($expected);
    })->with([
        'USD' => ['USD', 100000, 1000.0],
        'JPY (zero-decimal)' => ['JPY', 1000, 1000.0],
        'KWD (three-decimal)' => ['KWD', 1000000, 1000.0],
        'HUF' => ['HUF', 100000, 1000.0],
        'UGX (two-decimal representation)' => ['UGX', 100000, 1000.0],
        'unknown code falls back to two decimals' => ['XXX', 100000, 1000.0],
        'lowercase currency' => ['usd', 100000, 1000.0],
    ]);
});

describe('Stripe refunds', function (): void {
    test('advertises refund support', function (): void {
        expect(Payment_Adapter_Stripe::supportsRefunds())->toBeTrue();
    });

    test('refunds a PaymentIntent with converted minor units', function (): void {
        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => $p['payment_intent'] === 'pi_123' && $p['amount'] === 1050 && ($p['metadata']['invoice_id'] ?? null) === '5'),
                Mockery::on(fn (array $o): bool => str_starts_with((string) ($o['idempotency_key'] ?? ''), 'refund_'))
            )
            ->andReturn((object) ['id' => 're_123']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('pi_123', 10.50, 'USD', ['invoice_id' => '5']))->toBe('re_123');
    });

    test('refunds a charge directly and respects zero-decimal currencies', function (): void {
        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => ($p['charge'] ?? null) === 'ch_123' && $p['amount'] === 1000),
                Mockery::any())
            ->andReturn((object) ['id' => 're_456']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('ch_123', 1000, 'JPY'))->toBe('re_456');
    });

    test('resolves a Stripe invoice to its payment intent', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_123', ['expand' => ['payments']])->andReturn((object) ['payment_intent' => 'pi_inv', 'charge' => null]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $p): bool => ($p['payment_intent'] ?? null) === 'pi_inv' && $p['amount'] === 2000), Mockery::any())
            ->andReturn((object) ['id' => 're_789']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('in_123', 20.00, 'USD'))->toBe('re_789');
    });

    test('rejects transaction ids that cannot be refunded', function (): void {
        expect(fn () => $this->adapter->refundPayment('seti_123', 5.00, 'USD'))->toThrow(Payment_Exception::class);
        expect(fn () => $this->adapter->refundPayment('pi_123', 0, 'USD'))->toThrow(Payment_Exception::class);
    });

    test('wraps Stripe API failures', function (): void {
        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')->once()->andThrow(new Stripe\Exception\InvalidRequestException('Already refunded', 400));

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect(fn () => $this->adapter->refundPayment('pi_123', 5.00, 'USD'))->toThrow(Payment_Exception::class, 'Stripe refund failed');
    });

    test('refund webhooks are acknowledged without keeping a transaction', function (): void {
        expect(Payment_Adapter_Stripe::HANDLED_EVENT_TYPES)->toContain('charge.refunded')
            ->and(Payment_Adapter_Stripe::HANDLED_EVENT_TYPES)->toContain('refund.created');

        $tx = buildTransaction();
        $event = (object) ['id' => 'evt_1', 'type' => 'refund.created', 'data' => (object) ['object' => (object) ['id' => 're_1']]];
        expect(invokePrivateMethod($this->adapter, 'handleRefundWebhook', [$tx, $event]))->toBeFalse()
            ->and($tx->getTxnId())->toBe('re_1');
    });
});

describe('listRefundsForTransaction', function (): void {
    test('maps actionable refunds and skips failed ones', function (): void {
        $refunds = Mockery::mock();
        $refunds->shouldReceive('all')
            ->once()
            ->with(['payment_intent' => 'pi_123', 'limit' => 100])
            ->andReturn((object) ['data' => [
                (object) ['id' => 're_1', 'amount' => 1050, 'currency' => 'usd', 'status' => 'succeeded', 'metadata' => (object) ['invoice_id' => '5', 'lines' => '12:1']],
                (object) ['id' => 're_2', 'amount' => 500, 'currency' => 'usd', 'status' => 'failed', 'metadata' => (object) []],
                (object) ['id' => 're_3', 'amount' => 200, 'currency' => 'usd', 'status' => 'pending', 'metadata' => (object) []],
            ]]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->listRefundsForTransaction('pi_123', 'USD'))->toBe([
            ['id' => 're_1', 'amount' => 10.5, 'invoice_id' => '5', 'lines' => '12:1'],
            ['id' => 're_3', 'amount' => 2.0, 'invoice_id' => null, 'lines' => null],
        ]);
    });

    test('resolves a Stripe invoice before listing', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_123', ['expand' => ['payments']])->andReturn((object) ['payment_intent' => 'pi_inv', 'charge' => null]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('all')->once()->with(['payment_intent' => 'pi_inv', 'limit' => 100])->andReturn((object) ['data' => []]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->listRefundsForTransaction('in_123', 'USD'))->toBe([]);
    });
});

describe('handleRefundCreatedWebhook', function (): void {
    test('ignores refunds that originated in FOSSBilling', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_1',
            'amount' => 2000,
            'currency' => 'usd',
            'charge' => 'ch_1',
            'payment_intent' => 'pi_1',
            'metadata' => (object) ['gateway_id' => '1', 'invoice_id' => '5'],
        ];
        $event = (object) ['id' => 'evt_1', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        $this->adapter->setDi(container());

        $result = invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]);

        expect($result)->toBeFalse();
    });

    test('records a full dashboard refund as a credit note', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_9',
            'amount' => 2000,
            'currency' => 'usd',
            'charge' => 'ch_9',
            'payment_intent' => 'pi_9',
            'metadata' => (object) [],
        ];
        $event = (object) ['id' => 'evt_9', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        $payGateway = createEntity(PayGateway::class);
        setEntityId($payGateway, 1);

        $invoiceModel = createEntity(Invoice::class);
        $invoiceModel->id = 5;
        $invoiceModel->currency = 'USD';
        $invoiceModel->setStatus(Invoice::STATUS_PAID);
        $invoiceModel->setGateway($payGateway);

        $paymentTx = buildTransaction();
        $paymentTx->setTxnId('pi_9');
        $paymentTx->setGateway($payGateway);
        $paymentTx->setInvoice($invoiceModel);

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findOneByTxnIdAndGatewayId')->once()->with('pi_9', 1)->andReturn($paymentTx);
        $invoiceRepo->shouldReceive('find')->once()->with(5)->andReturn($invoiceModel);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoiceModel)->andReturn(20.0);
        $invoiceService->shouldReceive('refundInvoice')
            ->once()
            ->with($invoiceModel, 'Recorded from Stripe dashboard refund re_9', null, ['gateway_refund' => false, 'gateway_returned' => true])
            ->andReturn(7);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 're_9', 1);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]);

        expect($result)->toBeFalse();
    });

    test('leaves partial dashboard refunds for the admin', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_9',
            'amount' => 500,
            'currency' => 'usd',
            'charge' => 'ch_9',
            'payment_intent' => 'pi_9',
            'metadata' => (object) [],
        ];
        $event = (object) ['id' => 'evt_9', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        $payGateway = createEntity(PayGateway::class);
        setEntityId($payGateway, 1);

        $invoiceModel = createEntity(Invoice::class);
        $invoiceModel->id = 5;
        $invoiceModel->currency = 'USD';
        $invoiceModel->setStatus(Invoice::STATUS_PAID);
        $invoiceModel->setGateway($payGateway);

        $paymentTx = buildTransaction();
        $paymentTx->setTxnId('pi_9');
        $paymentTx->setGateway($payGateway);
        $paymentTx->setInvoice($invoiceModel);

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findOneByTxnIdAndGatewayId')->once()->with('pi_9', 1)->andReturn($paymentTx);
        $invoiceRepo->shouldReceive('find')->once()->with(5)->andReturn($invoiceModel);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(20.0);
        $invoiceService->shouldNotReceive('refundInvoice');

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 're_9', 1);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]);

        expect($result)->toBeFalse();
    });
});

describe('handleRefundCreatedWebhook races', function (): void {
    test('ignores the webhook when a concurrent refund settled the invoice first', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_9',
            'amount' => 2000,
            'currency' => 'usd',
            'charge' => 'ch_9',
            'payment_intent' => 'pi_9',
            'metadata' => (object) [],
        ];
        $event = (object) ['id' => 'evt_9', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        $payGateway = createEntity(PayGateway::class);
        setEntityId($payGateway, 1);

        $invoiceModel = createEntity(Invoice::class);
        $invoiceModel->id = 5;
        $invoiceModel->currency = 'USD';
        $invoiceModel->setStatus(Invoice::STATUS_PAID);
        $invoiceModel->setGateway($payGateway);

        $settledInvoice = createEntity(Invoice::class);
        $settledInvoice->id = 5;
        $settledInvoice->currency = 'USD';
        $settledInvoice->setStatus(Invoice::STATUS_REFUNDED);
        $settledInvoice->setGateway($payGateway);

        $paymentTx = buildTransaction();
        $paymentTx->setTxnId('pi_9');
        $paymentTx->setGateway($payGateway);
        $paymentTx->setInvoice($invoiceModel);

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findOneByTxnIdAndGatewayId')->once()->with('pi_9', 1)->andReturn($paymentTx);
        $invoiceRepo->shouldReceive('find')->twice()->with(5)->andReturn($invoiceModel, $settledInvoice);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->andReturn(20.0);
        $invoiceService->shouldReceive('refundInvoice')
            ->once()
            ->andThrow(new FOSSBilling\InformationException('Only paid invoices can be refunded'));

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 're_9', 1);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        $result = invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]);

        expect($result)->toBeFalse()->and($tx->getStatus())->toBe(Transaction::STATUS_RECEIVED);
    });
});

describe('resolveRefundTargetForStripeInvoice', function (): void {
    test('resolves through the payments list when legacy fields are absent', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_456', ['expand' => ['payments']])->andReturn((object) [
            'payment_intent' => null,
            'charge' => null,
            'payments' => (object) ['data' => [(object) ['status' => 'paid', 'payment' => (object) ['type' => 'payment_intent', 'payment_intent' => 'pi_from_list']]]],
        ]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => ($p['payment_intent'] ?? null) === 'pi_from_list'),
                Mockery::any())
            ->andReturn((object) ['id' => 're_1']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('in_456', 20.00, 'USD'))->toBe('re_1');
    });

    test('resolves an expanded payment object through its id', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_789', ['expand' => ['payments']])->andReturn((object) [
            'payments' => (object) ['data' => [(object) ['payment' => (object) ['id' => 'ch_expanded', 'object' => 'charge']]]],
        ]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => ($p['charge'] ?? null) === 'ch_expanded'),
                Mockery::any())
            ->andReturn((object) ['id' => 're_2']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('in_789', 20.00, 'USD'))->toBe('re_2');
    });

    test('resolves the wrapped payment reference and skips unpaid entries', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_321', ['expand' => ['payments']])->andReturn((object) [
            'payment_intent' => null,
            'charge' => null,
            'payments' => (object) ['data' => [
                (object) ['status' => 'open', 'payment' => (object) ['type' => 'payment_intent', 'payment_intent' => 'pi_open']],
                (object) ['status' => 'canceled', 'payment' => (object) ['type' => 'charge', 'charge' => 'ch_dead']],
                (object) ['status' => 'paid', 'payment' => (object) ['type' => 'payment_intent', 'payment_intent' => 'pi_wrap']],
            ]],
        ]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => ($p['payment_intent'] ?? null) === 'pi_wrap'),
                Mockery::any())
            ->andReturn((object) ['id' => 're_3']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('in_321', 20.00, 'USD'))->toBe('re_3');
    });

    test('resolves an expanded object nested in the payment wrapper', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_654', ['expand' => ['payments']])->andReturn((object) [
            'payment_intent' => null,
            'charge' => null,
            'payments' => (object) ['data' => [
                (object) ['status' => 'paid', 'payment' => (object) ['type' => 'payment_intent', 'payment_intent' => (object) ['id' => 'pi_nested', 'object' => 'payment_intent']]],
            ]],
        ]);

        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn (array $p): bool => ($p['payment_intent'] ?? null) === 'pi_nested'),
                Mockery::any())
            ->andReturn((object) ['id' => 're_4']);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect($this->adapter->refundPayment('in_654', 20.00, 'USD'))->toBe('re_4');
    });

    test('throws when only unpaid invoice payments exist', function (): void {
        $invoices = Mockery::mock();
        $invoices->shouldReceive('retrieve')->once()->with('in_987', ['expand' => ['payments']])->andReturn((object) [
            'payment_intent' => null,
            'charge' => null,
            'payments' => (object) ['data' => [
                (object) ['status' => 'open', 'payment' => (object) ['type' => 'payment_intent', 'payment_intent' => 'pi_open']],
            ]],
        ]);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->invoices = $invoices;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        expect(fn (): string => $this->adapter->refundPayment('in_987', 20.00, 'USD'))->toThrow(Payment_Exception::class, 'has no refundable payment');
    });
});

describe('refund idempotency keys', function (): void {
    test('issues distinct keys per refunded line selection', function (): void {
        $capturedKeys = [];
        $refunds = Mockery::mock();
        $refunds->shouldReceive('create')->times(3)->andReturnUsing(function (array $params, array $opts) use (&$capturedKeys): object {
            $capturedKeys[] = $opts['idempotency_key'] ?? null;

            return (object) ['id' => 're_x'];
        });

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->refunds = $refunds;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        $base = ['invoice_id' => '5', 'gateway_id' => '1'];
        $this->adapter->refundPayment('pi_1', 10.00, 'USD', $base + ['lines' => '12:1']);
        $this->adapter->refundPayment('pi_1', 10.00, 'USD', $base + ['lines' => '13:1']);
        $this->adapter->refundPayment('pi_1', 10.00, 'USD', $base + ['lines' => '12:1']);

        expect($capturedKeys)->toHaveCount(3)
            ->and($capturedKeys[0])->not->toBe($capturedKeys[1])
            ->and($capturedKeys[0])->toBe($capturedKeys[2]);
    });
});

describe('handleRefundCreatedWebhook invoice payment fallback', function (): void {
    test('records a subscription dashboard refund resolved through the invoice payment list', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_9',
            'amount' => 2000,
            'currency' => 'usd',
            'charge' => 'ch_sub',
            'payment_intent' => 'pi_sub',
            'metadata' => (object) [],
        ];
        $event = (object) ['id' => 'evt_9', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        $payGateway = createEntity(PayGateway::class);
        setEntityId($payGateway, 1);

        $invoiceModel = createEntity(Invoice::class);
        $invoiceModel->id = 5;
        $invoiceModel->currency = 'USD';
        $invoiceModel->setStatus(Invoice::STATUS_PAID);
        $invoiceModel->setGateway($payGateway);

        $paymentTx = buildTransaction();
        $paymentTx->setTxnId('in_9');
        $paymentTx->setGateway($payGateway);
        $paymentTx->setInvoice($invoiceModel);

        ['em' => $em, 'txRepo' => $txRepo, 'invoiceRepo' => $invoiceRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findOneByTxnIdAndGatewayId')->times(3)->andReturnUsing(
            fn (string $txnId, int $gatewayId): ?object => $txnId === 'in_9' ? $paymentTx : null
        );
        $invoiceRepo->shouldReceive('find')->once()->with(5)->andReturn($invoiceModel);

        $charges = Mockery::mock();
        $charges->shouldReceive('retrieve')->once()->with('ch_sub', [])->andReturn((object) ['payment_intent' => 'pi_sub']);

        $invoicePayments = Mockery::mock();
        $invoicePayments->shouldReceive('all')
            ->once()
            ->with(['payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_sub'], 'limit' => 1])
            ->andReturn((object) ['data' => [(object) ['invoice' => 'in_9']]]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldReceive('getTotalWithTax')->once()->with($invoiceModel)->andReturn(20.0);
        $invoiceService->shouldReceive('refundInvoice')
            ->once()
            ->with($invoiceModel, 'Recorded from Stripe dashboard refund re_9', null, ['gateway_refund' => false, 'gateway_returned' => true])
            ->andReturn(7);

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 're_9', 1);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->charges = $charges;
        $stripe->invoicePayments = $invoicePayments;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        expect(invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]))->toBeFalse();
    });

    test('ignores the refund when the invoice payment list is empty', function (): void {
        $tx = buildTransaction();
        $refund = (object) [
            'id' => 're_9',
            'amount' => 2000,
            'currency' => 'usd',
            'charge' => 'ch_sub',
            'payment_intent' => 'pi_unknown',
            'metadata' => (object) [],
        ];
        $event = (object) ['id' => 'evt_9', 'type' => 'refund.created', 'data' => (object) ['object' => $refund]];

        ['em' => $em, 'txRepo' => $txRepo] = buildEntityManagerMocks();
        $txRepo->shouldReceive('findOneByTxnIdAndGatewayId')->twice()->andReturn(null);

        $charges = Mockery::mock();
        $charges->shouldReceive('retrieve')->once()->with('ch_sub', [])->andReturn((object) ['payment_intent' => 'pi_unknown']);

        $invoicePayments = Mockery::mock();
        $invoicePayments->shouldReceive('all')->once()->andReturn((object) ['data' => []]);

        $invoiceService = Mockery::mock();
        $invoiceService->shouldNotReceive('refundInvoice');

        $dbalMock = Mockery::mock(Doctrine\DBAL\Connection::class);
        expectStripeObjectLock($dbalMock, 're_9', 1);

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->charges = $charges;
        $stripe->invoicePayments = $invoicePayments;
        setPrivateProperty($this->adapter, 'stripe', $stripe);

        $di = container();
        $di['dbal'] = $dbalMock;
        $di['em'] = $em;
        $di['logger'] = new Tests\Helpers\TestLogger();
        $di['mod_service'] = $di->protect(fn ($module, $service = null) => $invoiceService);
        $this->adapter->setDi($di);

        expect(invokePrivateMethod($this->adapter, 'handleRefundCreatedWebhook', [$tx, $event, 1]))->toBeFalse();
    });
});
