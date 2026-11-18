<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Service as ClientService;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Subscription;
use Box\Mod\Invoice\Repository\PayGatewayRepository;
use Box\Mod\Invoice\Repository\SubscriptionRepository;
use Box\Mod\Invoice\Service as InvoiceService;
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
use Box\Mod\Product\Entity\PromoRedemption;
use Box\Mod\Product\Repository\PromoRedemptionRepository;
use Box\Mod\Product\Service as ProductService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;

use function Tests\Helpers\container;
use function Tests\Helpers\createEntity;

function createSubscriptionDbal(): Connection
{
    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $connection->executeStatement('CREATE TABLE subscription (id INTEGER PRIMARY KEY, rel_type TEXT, rel_id INTEGER, status TEXT, sid TEXT)');
    $connection->executeStatement('CREATE TABLE invoice_item (invoice_id INTEGER, type TEXT, rel_id INTEGER)');
    $connection->executeStatement('CREATE TABLE client_order_meta (client_order_id INTEGER, name TEXT, value TEXT)');
    $connection->executeStatement("INSERT INTO subscription (id, rel_type, rel_id, status, sid) VALUES (7, 'invoice', 25, 'active', 'sub_123')");
    $connection->executeStatement("INSERT INTO invoice_item (invoice_id, type, rel_id) VALUES (25, 'order', 10), (25, 'order', 11)");

    return $connection;
}

function subscriptionService(?SubscriptionRepository $subRepo = null, ?PayGatewayRepository $payGatewayRepo = null, ?EntityManagerInterface $em = null): ServiceSubscription
{
    $service = new ServiceSubscription();
    $di = container();
    $em ??= Mockery::mock(EntityManagerInterface::class);
    $subRepo ??= Mockery::mock(SubscriptionRepository::class);
    $payGatewayRepo ??= Mockery::mock(PayGatewayRepository::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn($payGatewayRepo);
    $di['em'] = $em;
    $service->setDi($di);

    return $service;
}

test('gets dependency injection container', function (): void {
    $repo = Mockery::mock(SubscriptionRepository::class);
    $service = subscriptionService($repo);

    expect($service->getDi())->toBeInstanceOf(Pimple\Container::class)
        ->and($service->getSubscriptionRepository())->toBe($repo);
});

test('creates a subscription and dispatches its typed event', function (): void {
    $calls = (object) ['entries' => []];
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->once()->with(Mockery::type(Subscription::class))->andReturnUsing(function (Subscription $subscription): void {
        (new ReflectionProperty(Subscription::class, 'id'))->setValue($subscription, 42);
    });
    $em->shouldReceive('flush')->once();

    $eventDispatcher = new readonly class($calls) {
        public function __construct(private object $calls)
        {
        }

        public function dispatch(FOSSBilling\Core\Events\Event $event): FOSSBilling\Core\Events\Event
        {
            $this->calls->entries[] = ['typed', $event];

            return $event;
        }
    };

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['event_dispatcher'] = $eventDispatcher;

    $data = [
        'client_id' => 1,
        'gateway_id' => 2,
    ];

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $pg = createEntity(PayGateway::class, ['id' => 2]);

    $result = $service->create($client, $pg, $data);
    expect($result)->toBe(42)
        ->and($calls->entries)->toHaveCount(1)
        ->and($calls->entries[0][0])->toBe('typed')
        ->and($calls->entries[0][1])->toBeInstanceOf(Box\Mod\Invoice\Event\AfterAdminSubscriptionCreateEvent::class)
        ->and($calls->entries[0][1]->subscriptionId)->toBe(42);
});

test('updates a subscription', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->atLeast()->once();

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);
    $data = [
        'status' => '',
        'sid' => '',
        'period' => '',
        'amount' => '',
        'currency' => '',
    ];

    $result = $service->update($subscriptionModel, $data);
    expect($result)->toBeTrue();
});

test('cancels the stored subscription at the gateway when canceled status is saved', function (): void {
    $gatewayModel = createEntity(PayGateway::class, ['id' => 2]);

    $subscriptionModel = createEntity(Subscription::class, ['id' => 5, 'payGateway' => $gatewayModel]);
    $subscriptionModel->setSid('sub_old');

    $adapter = new class {
        public ?string $canceledSubscriptionId = null;

        public function cancelSubscription(string $subscriptionId): void
        {
            $this->canceledSubscriptionId = $subscriptionId;
        }
    };

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('getPaymentAdapter')
        ->once()
        ->with($gatewayModel)
        ->andReturn($adapter);

    $pgRepo = Mockery::mock(PayGatewayRepository::class)->shouldIgnoreMissing();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = subscriptionService(payGatewayRepo: $pgRepo, em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $payGatewayService);

    expect($service->update($subscriptionModel, ['status' => 'canceled', 'sid' => 'sub_new', 'skip_gateway' => true]))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled')
        ->and($subscriptionModel->getSid())->toBe('sub_new')
        ->and($adapter->canceledSubscriptionId)->toBe('sub_old');
});

test('does not call the gateway when canceling a subscription without a sid', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['mod_service'] = $service->getDi()->protect(function (): void {
        throw new RuntimeException('The gateway should not be loaded');
    });

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);
    $subscriptionModel->setSid(null);

    expect($service->update($subscriptionModel, ['status' => 'canceled']))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled');
});

test('schedules a subscription cancellation at the gateway', function (): void {
    $gateway = createEntity(PayGateway::class, ['id' => 2]);

    $subscription = createEntity(Subscription::class, ['id' => 3, 'payGateway' => $gateway]);
    $subscription->setSid('sub_123');

    $adapter = new class {
        public ?string $scheduledSubscriptionId = null;

        public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
        {
            $this->scheduledSubscriptionId = $subscriptionId;
        }
    };

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);

    $pgRepo = Mockery::mock(PayGatewayRepository::class)->shouldIgnoreMissing();

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = subscriptionService(payGatewayRepo: $pgRepo, em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $payGatewayService);

    $service->scheduleCancellation($subscription);

    expect($adapter->scheduledSubscriptionId)->toBe('sub_123')
        ->and($subscription->getStatus())->toBe(ServiceSubscription::STATUS_PENDING_CANCELLATION);
});

test('updates subscription status from a gateway without calling the adapter', function (): void {
    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);
    $subscriptionModel->status = 'active';

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->once()->with(1)->andReturn($subscriptionModel);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $service = subscriptionService(subRepo: $subRepo, em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['mod_service'] = $service->getDi()->protect(function (): void {
        throw new RuntimeException('The gateway should not be loaded');
    });

    expect($service->updateStatusFromGateway(1, 'canceled'))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled');
});

test('cancels subscriptions linked to an order', function (): void {
    $subscriptionModel = createEntity(Subscription::class, ['id' => 7]);

    $orderModel = createEntity(Order::class, ['id' => 10]);

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->once()->with(7)->andReturn($subscriptionModel);

    $service = Mockery::mock(ServiceSubscription::class)->makePartial();
    $di = container();
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $di['em'] = $em;
    $em->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());
    $service->setDi($di);
    $service->shouldReceive('cancel')->once()->with($subscriptionModel);

    $service->cancelForOrder($orderModel);
});

test('finalizes a scheduled cancellation after subscription deletion regardless of update ordering', function (string $status): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setStatus($status);
    $subscription->setRelType('invoice');
    $subscription->setRelId(25);

    $order = createEntity(Order::class, ['status' => Order::STATUS_ACTIVE]);

    $dbal = createSubscriptionDbal();
    $dbal->insert('client_order_meta', [
        'client_order_id' => 10,
        'name' => Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END,
        'value' => '1',
    ]);

    $orderRepoMock = Mockery::mock(OrderRepository::class);
    $orderRepoMock->shouldReceive('find')->once()->with(10)->andReturn($order);

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->once()->with(7)->andReturn($subscription);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->once();

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('finalizeCancellationFromGateway')
        ->once()
        ->with($order, 'Subscription ended at the payment gateway')
        ->andReturn(true);

    $di = container();
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($subRepo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepoMock);
    $di['em'] = $em;
    $em->shouldReceive('getConnection')->andReturn($dbal);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $orderService);

    $service = new ServiceSubscription();
    $service->setDi($di);

    expect($service->finalizeCancellationFromGateway(7))->toBeTrue()
        ->and($subscription->getStatus())->toBe('canceled');
})->with([ServiceSubscription::STATUS_PENDING_CANCELLATION, 'canceled', 'active']);

test('reports end-of-period cancellation support for active gateway subscriptions', function (): void {
    $order = createEntity(Order::class, ['id' => 10]);

    $gateway = createEntity(PayGateway::class, ['id' => 2]);

    $subscription = createEntity(Subscription::class, ['id' => 7, 'payGateway' => $gateway]);
    $subscription->setSid('sub_123');

    $adapter = new class {
        public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
        {
        }
    };

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->once()->with(7)->andReturn($subscription);

    $pgRepo = Mockery::mock(PayGatewayRepository::class)->shouldIgnoreMissing();

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);

    $service = subscriptionService(subRepo: $subRepo, payGatewayRepo: $pgRepo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $gatewayService);

    expect($service->canCancelAtPeriodEndForOrder($order))->toBeTrue();
});

test('detects orders paid through an active gateway subscription', function (): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setSid('sub_123');

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->with(7)->andReturn($subscription);

    $service = subscriptionService(subRepo: $subRepo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());

    // Order 10 sits on invoice 25, which carries the active subscription.
    // Order 99 has no subscription rows at all.
    expect($service->hasActiveSubscriptionForOrder(createEntity(Order::class, ['id' => 10])))->toBeTrue()
        ->and($service->hasActiveSubscriptionForOrder(createEntity(Order::class, ['id' => 99])))->toBeFalse();
});

test('staff cancellation review still sees a subscription pending at the gateway', function (): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setSid('sub_123')->setStatus(ServiceSubscription::STATUS_PENDING_CANCELLATION);
    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->with(7)->andReturn($subscription);
    $dbal = createSubscriptionDbal();
    $dbal->executeStatement("UPDATE subscription SET status = 'pending_cancellation' WHERE id = 7");
    $service = subscriptionService(subRepo: $subRepo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($dbal);
    $order = createEntity(Order::class, ['id' => 10]);

    expect($service->hasActiveSubscriptionForOrder($order))->toBeFalse()
        ->and($service->hasUncanceledSubscriptionForOrder($order))->toBeTrue();
});

test('ignores subscriptions without a gateway sid', function (): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setSid('');

    $subRepo = Mockery::mock(SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->with(7)->andReturn($subscription);

    $dbal = createSubscriptionDbal();
    $dbal->executeStatement("UPDATE subscription SET sid = '' WHERE id = 7");

    $service = subscriptionService(subRepo: $subRepo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($dbal);

    expect($service->hasActiveSubscriptionForOrder(createEntity(Order::class, ['id' => 10])))->toBeFalse();
});

test('finds a subscription ID by gateway SID without throwing for missing records', function (): void {
    $dbal = Mockery::mock(Connection::class);
    $dbal->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT id FROM subscription WHERE sid = :sid', ['sid' => 'sub_123'])
        ->andReturn('7');
    $dbal->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT id FROM subscription WHERE sid = :sid', ['sid' => 'sub_missing'])
        ->andReturn(false);

    $service = subscriptionService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($dbal);

    expect($service->findIdBySid('sub_123'))->toBe(7)
        ->and($service->findIdBySid('sub_missing'))->toBeNull();
});

test('converts to api array', function (): void {
    $gatewayModel = createEntity(PayGateway::class, ['id' => 1]);

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1, 'clientId' => 5, 'payGateway' => $gatewayModel]);

    $clientModel = createEntity(Box\Mod\Client\Entity\Client::class);

    $clientRepo = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepo->shouldReceive('find')
        ->atLeast()->once()
        ->andReturn($clientModel);

    $pgRepo = Mockery::mock(PayGatewayRepository::class)->shouldIgnoreMissing();

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Box\Mod\Client\Entity\Client::class)->andReturn($clientRepo);

    $service = subscriptionService(payGatewayRepo: $pgRepo, em: $em);
    $service->getDi()['mod_service'] = $service->getDi()->protect(function ($serviceName, $sub = '') use ($clientServiceMock, $payGatewayService) {
        if ($serviceName == 'Client') {
            return $clientServiceMock;
        }
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
    });

    $result = $service->toApiArray($subscriptionModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('client');
    expect($result)->toHaveKey('gateway');
    expect($result['client'])->toBeArray();
    expect($result['gateway'])->toBeArray();
});

test('deletes a subscription and dispatches its typed event', function (): void {
    $calls = (object) ['entries' => []];
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->once();
    $em->shouldReceive('flush')->once();

    $eventDispatcher = new readonly class($calls) {
        public function __construct(private object $calls)
        {
        }

        public function dispatch(FOSSBilling\Core\Events\Event $event): FOSSBilling\Core\Events\Event
        {
            $this->calls->entries[] = ['typed', $event];

            return $event;
        }
    };

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['event_dispatcher'] = $eventDispatcher;

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);

    $result = $service->delete($subscriptionModel);
    expect($result)->toBeTrue()
        ->and($calls->entries)->toHaveCount(1)
        ->and($calls->entries[0][0])->toBe('typed')
        ->and($calls->entries[0][1])->toBeInstanceOf(Box\Mod\Invoice\Event\AfterAdminSubscriptionDeleteEvent::class)
        ->and($calls->entries[0][1]->subscriptionId)->toBe(1);
});

test('returns false when invoice is not subscribable', function (): void {
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $service = subscriptionService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeFalse();
});

test('checks if invoice is subscribable', function (): void {
    $getAllResults = [
        ['period' => '1W', 'price' => 10, 'quantity' => 1],
    ];
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($getAllResults);

    $service = subscriptionService();
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets subscription period', function (): void {
    $serviceMock = Mockery::mock(ServiceSubscription::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $period = '1W';
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([['period' => $period, 'price' => 10, 'quantity' => 1]]);

    $di = container();
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn(Mockery::mock(SubscriptionRepository::class));
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getConnection')->andReturn($connection);
    $di['em'] = $em;
    $serviceMock->setDi($di);

    $invoiceModel = createEntity(Invoice::class);

    $result = $serviceMock->getSubscriptionPeriod($invoiceModel);
    expect($result)->toBeString()->toBe($period);
});

test('unsubscribes', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('flush')->atLeast()->once();

    $service = subscriptionService(em: $em);

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);

    $service->unsubscribe($subscriptionModel);
});

function discountedInvoiceService(array $items, float $recurringSum, array $orders = [], array $adjustments = [], array $renewalLines = []): ServiceSubscription
{
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('fetchAllAssociative')->atLeast()->once()->andReturn($items);

    $redemptionRepo = Mockery::mock(PromoRedemptionRepository::class);
    $redemptionRepo->shouldReceive('sumRecurringDiscountForInvoice')->with(30)->andReturn($recurringSum);

    $orderRepo = Mockery::mock(OrderRepository::class);
    $orderRepo->shouldReceive('findBy')->andReturn($orders);

    $invoiceService = Mockery::mock(InvoiceService::class);
    $invoiceService->shouldReceive('resolveRenewalLine')->andReturnUsing(
        static fn (Order $order): array => $renewalLines[(int) $order->getId()]
            ?? ['price' => $order->getPrice(), 'line' => ['quantity' => $order->getQuantity()]]
    );

    $productService = Mockery::mock(ProductService::class);
    $productService->shouldReceive('getRenewalPromoAdjustments')->andReturnUsing(
        static fn (Order $order): array => $adjustments[(int) $order->getId()] ?? []
    );

    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn(Mockery::mock(SubscriptionRepository::class));
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getRepository')->with(PromoRedemption::class)->andReturn($redemptionRepo);
    $em->shouldReceive('getRepository')->with(Order::class)->andReturn($orderRepo);
    $em->shouldReceive('getConnection')->andReturn($connection);

    $service = new ServiceSubscription();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(static fn (string $module) => $module === 'Product' ? $productService : $invoiceService);
    $service->setDi($di);

    return $service;
}

function discountedOrder(int $id, float $price = 10.0, int $quantity = 1): Order
{
    return createEntity(Order::class, ['id' => $id, 'price' => $price, 'quantity' => $quantity]);
}

function orderLine(int $orderId, float $price = 10.0): array
{
    return ['period' => '1M', 'price' => $price, 'quantity' => 1, 'type' => 'order', 'rel_id' => (string) $orderId];
}

function discountLine(float $amount): array
{
    return ['period' => null, 'price' => -$amount, 'quantity' => 1];
}

test('does not subscribe an invoice whose discount only applies to the first payment', function (array $items, float $recurringSum, array $orders, array $adjustments, array $renewalLines = []): void {
    expect(discountedInvoiceService($items, $recurringSum, $orders, $adjustments, $renewalLines)->isSubscribable(30))->toBeFalse();
})->with([
    'one-time promo' => [
        [orderLine(10), discountLine(2.5)], 0.0,
        [discountedOrder(10)], [],
    ],
    'discount without a promo' => [
        [orderLine(10), discountLine(2.5)], 0.0,
        [discountedOrder(10)], [],
    ],
    'recurring promo covers part of the discount' => [
        [orderLine(10), discountLine(2.5), discountLine(1.0)], 2.50,
        [discountedOrder(10)], [10 => [['discount_amount' => 2.50]]],
    ],
    'recurring promo shares 0.01 short of the discount' => [
        [orderLine(10), discountLine(2.5)], 2.49,
        [discountedOrder(10)], [10 => [['discount_amount' => 2.49]]],
    ],
    'recurring promo recorded 0.01 above the invoice discount' => [
        [orderLine(10), discountLine(2.5)], 2.51,
        [discountedOrder(10)], [10 => [['discount_amount' => 2.51]]],
    ],
    'capped recurring promo leaves a zero renewal total' => [
        [orderLine(11, 30.0), discountLine(20.0)], 20.0,
        [discountedOrder(11, 30.0)], [11 => [['discount_amount' => 20.0]]],
        [11 => ['price' => 10.0, 'line' => ['quantity' => 1]]],
    ],
    'stale redemption cannot cover another order discount' => [
        [orderLine(10), discountLine(2.5)], 2.50,
        [discountedOrder(10)], [10 => [['discount_amount' => 0.50]]],
    ],
]);

test('subscribes an invoice whose discount comes from a recurring promo', function (array $items, float $recurringSum, array $orders, array $adjustments): void {
    $service = discountedInvoiceService($items, $recurringSum, $orders, $adjustments);

    expect($service->getSubscriptionPeriod(createEntity(Invoice::class, ['id' => 30])))->toBe('1M');
})->with([
    'one recurring promo' => [
        [orderLine(10), discountLine(2.5)], 2.50,
        [discountedOrder(10)], [10 => [['discount_amount' => 2.50]]],
    ],
]);

test('order cancellation retries skip settled subscriptions and force failures retain reconciliation identifiers', function (bool $force): void {
    $dbal = createSubscriptionDbal();
    $dbal->executeStatement("INSERT INTO subscription VALUES (8, 'invoice', 25, 'active', 'sub_456')");
    $first = createEntity(Subscription::class, ['id' => 7, 'sid' => 'sub_123', 'status' => 'active']);
    $second = createEntity(Subscription::class, ['id' => 8, 'sid' => 'sub_456', 'status' => 'active']);
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($first);
    $repo->shouldReceive('find')->with(8)->andReturn($second);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($repo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getConnection')->andReturn($dbal);
    $service = Mockery::mock(ServiceSubscription::class)->makePartial();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    if ($force) {
        $service->shouldReceive('cancel')->once()->with($first)->andThrow(new RuntimeException('Gateway unavailable'));
    } else {
        $service->shouldReceive('cancel')->once()->with($first)->andReturnUsing(fn () => $first->setStatus('canceled'));
    }
    $fail = !$force;
    $service->shouldReceive('cancel')->with($second)->andReturnUsing(function () use (&$fail, $second): void {
        if ($fail) {
            throw new RuntimeException('Gateway unavailable');
        }
        $second->setStatus('canceled');
    });
    $order = createEntity(Order::class, ['id' => 10]);
    if ($force) {
        expect($service->cancelForOrder($order, true))->toBe(1);
        $warnings = array_values(array_filter($di['logger']->calls, fn (array $call): bool => $call['method'] === 'warning'));
        expect($warnings)->toHaveCount(1)->and($warnings[0]['params'][1])->toMatchArray(['order_id' => 10, 'subscription_id' => 7, 'sid' => 'sub_123']);
    } else {
        expect(fn () => $service->cancelForOrder($order))->toThrow(RuntimeException::class);
        $fail = false;
        expect($service->cancelForOrder($order))->toBe(1)
            ->and($service->cancelForOrder($order))->toBe(0);
    }
    expect($first->getStatus())->toBe($force ? 'active' : 'canceled')
        ->and($second->getStatus())->toBe('canceled');
})->with([false, true]);

test('cancelForOrder does not contact gateways for canceled historical subscriptions', function (): void {
    $canceled = createEntity(Subscription::class, ['id' => 7]);
    $canceled->setStatus('canceled');
    $order = createEntity(Order::class, ['id' => 10]);
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->once()->with(7)->andReturn($canceled);
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('getRepository')->with(Subscription::class)->andReturn($repo);
    $em->shouldReceive('getRepository')->with(PayGateway::class)->andReturn(Mockery::mock(PayGatewayRepository::class));
    $em->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());
    $service = Mockery::mock(ServiceSubscription::class)->makePartial();
    $service->shouldNotReceive('cancel');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect($service->cancelForOrder($order))->toBe(0);
});

test('subscriptions without discounts must match the current renewal quote', function (float $renewalPrice, bool $expected): void {
    $order = discountedOrder(10, 12.0);
    $service = discountedInvoiceService([orderLine(10, 12.0)], 0.0, [$order], [], [10 => ['price' => $renewalPrice, 'line' => ['quantity' => 1]]]);
    expect($service->isSubscribable(30))->toBe($expected);
})->with([[0.0, false], [8.0, false], [12.0, true]]);

test('immediate cancellation requires gateway support for each live subscription', function (bool $supported, string $status, bool $expected): void {
    $order = createEntity(Order::class, ['id' => 10]);
    $gateway = createEntity(PayGateway::class, ['id' => 2]);
    $subscription = createEntity(Subscription::class, ['id' => 7, 'payGateway' => $gateway, 'status' => $status, 'sid' => 'sub_123']);
    $adapter = $supported ? new class {
        public function cancelSubscription(string $id): never
        {
            throw new LogicException('Capability checks must not call the provider');
        }
    } : new stdClass();
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($subscription);
    $service = subscriptionService(subRepo: $repo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());
    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getPaymentAdapter')->zeroOrMoreTimes()->with($gateway)->andReturn($adapter);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn (): object => $gatewayService);
    expect($service->canCancelImmediatelyForOrder($order))->toBe($expected);
})->with([[false, 'active', false], [true, 'active', true], [false, 'pending_cancellation', false], [false, 'canceled', true]]);

test('unconfirmed gateway subscription states prevent free renewals', function (string $status, bool $held): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setSid('sub_retry')->setStatus($status);
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($subscription);
    $service = subscriptionService(subRepo: $repo);
    $dbal = createSubscriptionDbal();
    $dbal->update('subscription', ['status' => $status], ['id' => 7]);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($dbal);
    expect($service->hasUncanceledSubscriptionForOrder(createEntity(Order::class, ['id' => 10])))->toBe($held);
})->with([['past_due', true], ['unpaid', true], ['paused', true], ['pending_cancellation', true], ['canceled', false]]);

test('shared subscriptions require staff reconciliation before canceling either live order', function (?string $status, bool $otherLive): void {
    $connection = createSubscriptionDbal();
    $connection->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, status TEXT)');
    $connection->insert('client_order', ['id' => 10, 'status' => 'active']);
    $connection->insert('client_order', ['id' => 11, 'status' => $status]);
    $gateway = createEntity(PayGateway::class, ['id' => 2]);
    $subscription = createEntity(Subscription::class, ['id' => 7, 'payGateway' => $gateway, 'relType' => 'invoice', 'relId' => 25, 'sid' => 'sub_123', 'status' => 'active']);
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($subscription);
    $adapter = new class {
        public function cancelSubscription(string $sid): never
        {
            throw new LogicException('Preflight must not cancel');
        }

        public function cancelSubscriptionAtPeriodEnd(string $sid): never
        {
            throw new LogicException('Preflight must not schedule');
        }
    };
    $gateways = Mockery::mock(ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->zeroOrMoreTimes()->with($gateway)->andReturn($adapter);
    $service = subscriptionService(subRepo: $repo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn($connection);
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $gateways);
    $order = createEntity(Order::class, ['id' => 10]);
    expect($service->canCancelImmediatelyForOrder($order))->toBe(!$otherLive)
        ->and($service->canCancelAtPeriodEndForOrder($order))->toBe(!$otherLive);
})->with([['suspended', true], ['canceled', false], [null, true]]);

test('locally canceled Stripe subscriptions require current gateway confirmation before free pricing', function (?string $providerState, bool $held, bool $cancelRequest): void {
    $gateway = createEntity(PayGateway::class, ['id' => 2, 'gateway' => 'Stripe']);
    $subscription = createEntity(Subscription::class, ['id' => 7, 'payGateway' => $gateway, 'sid' => 'sub_123', 'status' => 'canceled']);
    $repo = Mockery::mock(SubscriptionRepository::class);
    $repo->shouldReceive('find')->with(7)->andReturn($subscription);
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $expectation = $adapter->shouldReceive('getSubscriptionStatus')->once()->with('sub_123');
    if ($providerState === null) {
        $expectation->andThrow(new RuntimeException('Gateway unavailable'));
    } else {
        $expectation->andReturn($providerState);
    }
    $gateways = Mockery::mock(ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);
    $service = subscriptionService(subRepo: $repo);
    $service->getDi()['em']->shouldReceive('getConnection')->andReturn(createSubscriptionDbal());
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $gateways);
    $order = createEntity(Order::class, ['id' => 10]);
    if ($cancelRequest) {
        expect($service->canCancelImmediatelyForOrder($order))->toBe(!$held);
    } else {
        expect($service->hasUncanceledSubscriptionForOrder($order))->toBe($held);
    }
})->with([['active', true], ['pending_cancellation', true], ['canceled', false], [null, true]])->with([false, true]);

test('multi-order invoices cannot start a subscription even with matching prices or a free second line', function (float $secondPrice): void {
    $items = [orderLine(10, 12), orderLine(11, $secondPrice)];
    expect(discountedInvoiceService($items, 0, [discountedOrder(10, 12), discountedOrder(11, $secondPrice)])->isSubscribable(30))->toBeFalse();
})->with([8.0, 0.0]);

test('single-order subscriptions without discounts compare their renewal price', function (float $renewalPrice, bool $allowed): void {
    $service = discountedInvoiceService([orderLine(10, 12)], 0, [discountedOrder(10, 12)], [], [10 => ['price' => $renewalPrice, 'line' => ['quantity' => 1]]]);
    expect($service->isSubscribable(30))->toBe($allowed);
})->with([[12.0, true], [0.0, false]]);

test('a gateway deletion persists cancellation before attempting local cleanup', function (bool $postgres): void {
    $connection = null;
    if ($postgres) {
        $dsn = getenv('FOSSBILLING_TEST_PGSQL_DSN');
        if ($dsn === false) {
            $this->markTestSkipped('Set FOSSBILLING_TEST_PGSQL_DSN to validate against PostgreSQL.');
        }
        $connection = DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse($dsn));
        $schema = 'subscription_cancel_' . bin2hex(random_bytes(6));
        $connection->executeStatement('CREATE SCHEMA ' . $schema);
        $connection->executeStatement('SET search_path TO ' . $schema);
    }

    require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

    try {
        [, $em, $client, $gateway] = receiptTestContext($connection);
        (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Subscription::class)]);
        $order = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE);
        $invoice = (new Invoice())->setClientId($client->getId());
        $em->persist($order);
        $em->persist($invoice);
        $em->flush();
        $em->persist((new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('order')->setRelId((string) $order->getId()));
        $em->persist((new Box\Mod\Order\Entity\OrderMeta())->setOrder($order)->setName('cancel_at_period_end')->setValue('1'));
        $subscription = (new Subscription())->setPayGateway($gateway)->setSid('sub_old')->setRelId($invoice->getId())->setRelType('invoice')->setStatus('active');
        $em->persist($subscription);
        $em->flush();
        $di = container();
        $di['em'] = $em;
        $orders = Mockery::mock(Box\Mod\Order\Service::class);
        $orders->shouldReceive('finalizeCancellationFromGateway')->once()->with($order, Mockery::type('string'))->andThrow(new RuntimeException('Backend unavailable'));
        $di['mod_service'] = $di->protect(fn () => $orders);
        $subscriptions = new ServiceSubscription();
        $subscriptions->setDi($di);

        expect(fn () => $subscriptions->finalizeCancellationFromGateway($subscription->getId()))->toThrow(RuntimeException::class, 'Backend unavailable');
        expect($em->getConnection()->fetchOne('SELECT status FROM subscription WHERE id = ?', [$subscription->getId()]))->toBe('canceled');
        $em->getConnection()->update('subscription', ['sid' => 'sub_replacement', 'status' => 'active'], ['id' => $subscription->getId()]);
        $subscriptions->updateStatusForGatewayLink($subscription->getId(), 'sub_old', $gateway->getId(), 'canceled');
        expect($em->getConnection()->fetchOne('SELECT status FROM subscription WHERE id = ?', [$subscription->getId()]))->toBe('active');
    } finally {
        if ($connection !== null) {
            $connection->executeStatement('DROP SCHEMA ' . $schema . ' CASCADE');
            $connection->close();
        }
    }
})->with(['SQLite' => false, 'PostgreSQL' => true]);
