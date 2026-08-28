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
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Repository\OrderRepository;
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

test('creates a subscription', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('persist')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $eventsMock = Mockery::mock(FOSSBilling\Event\Manager::class);
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['events_manager'] = $eventsMock;

    $data = [
        'client_id' => 1,
        'gateway_id' => 2,
    ];

    $client = createEntity(Box\Mod\Client\Entity\Client::class, ['id' => 1]);
    $pg = createEntity(PayGateway::class, ['id' => 2]);

    $result = $service->create($client, $pg, $data);
    expect($result)->toBeInt();
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

test('cancels a subscription at the gateway when canceled status is saved', function (): void {
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
        ->and($adapter->canceledSubscriptionId)->toBe('sub_new');
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
    $di['dbal'] = createSubscriptionDbal();
    $service->setDi($di);
    $service->shouldReceive('cancel')->once()->with($subscriptionModel);

    $service->cancelForOrder($orderModel);
});

test('finalizes a scheduled cancellation by canceling its order and service', function (): void {
    $subscription = createEntity(Subscription::class, ['id' => 7]);
    $subscription->setStatus(ServiceSubscription::STATUS_PENDING_CANCELLATION);
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
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $orderService);

    $service = new ServiceSubscription();
    $service->setDi($di);

    expect($service->finalizeCancellationFromGateway(7))->toBeTrue()
        ->and($subscription->getStatus())->toBe('canceled');
});

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
    $service->getDi()['dbal'] = createSubscriptionDbal();
    $service->getDi()['mod_service'] = $service->getDi()->protect(fn () => $gatewayService);

    expect($service->canCancelAtPeriodEndForOrder($order))->toBeTrue();
});

test('finds a subscription ID by gateway SID without throwing for missing records', function (): void {
    $dbal = Mockery::mock();
    $dbal->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT id FROM subscription WHERE sid = :sid', ['sid' => 'sub_123'])
        ->andReturn('7');
    $dbal->shouldReceive('fetchOne')
        ->once()
        ->with('SELECT id FROM subscription WHERE sid = :sid', ['sid' => 'sub_missing'])
        ->andReturn(false);

    $service = subscriptionService();
    $service->getDi()['dbal'] = $dbal;

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

test('deletes a subscription', function (): void {
    $em = Mockery::mock(EntityManagerInterface::class);
    $em->shouldReceive('remove')->atLeast()->once();
    $em->shouldReceive('flush')->atLeast()->once();

    $eventsMock = Mockery::mock(FOSSBilling\Event\Manager::class);
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $service = subscriptionService(em: $em);
    $service->getDi()['logger'] = new Tests\Helpers\TestLogger();
    $service->getDi()['events_manager'] = $eventsMock;

    $subscriptionModel = createEntity(Subscription::class, ['id' => 1]);

    $result = $service->delete($subscriptionModel);
    expect($result)->toBeTrue();
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
