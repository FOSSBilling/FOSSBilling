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
use Box\Mod\Invoice\ServicePayGateway;
use Box\Mod\Invoice\ServiceSubscription;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

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

test('gets dependency injection container', function (): void {
    $service = new ServiceSubscription();
    $di = container();
    $service->setDi($di);
    $getDi = $service->getDi();
    expect($getDi)->toBe($di);
});

test('creates a subscription', function (): void {
    $service = new ServiceSubscription();
    $newId = 1;

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['em'] = Tests\Helpers\entityManagerWithIds($di);
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventsMock;

    $service->setDi($di);

    $data = [
        'client_id' => 1,
        'gateway_id' => 2,
    ];

    $result = $service->create(createEntity(\Box\Mod\Client\Entity\Client::class), createEntity(\Box\Mod\Invoice\Entity\PayGateway::class), $data);
    expect($result)->toBeInt()->toBe($newId);
});

test('updates a subscription', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class);
    $data = [
        'status' => '',
        'sid' => '',
        'period' => '',
        'amount' => '',
        'currency' => '',
    ];

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();

    $service->setDi($di);

    $result = $service->update($subscriptionModel, $data);
    expect($result)->toBeTrue();
});

test('cancels a subscription at the gateway when canceled status is saved', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class, [
        'status' => 'canceled',
        'sid' => 'sub_old',
        'pay_gateway_id' => 2,
    ]);

    $gatewayModel = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

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

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('find')
        ->once()
        ->with(2)
        ->andReturn($gatewayModel);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);
    $emMock->shouldReceive('persist')->andReturnNull();
    $emMock->shouldReceive('flush')->andReturnNull();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $payGatewayService);
    $service->setDi($di);

    expect($service->update($subscriptionModel, ['status' => 'canceled', 'sid' => 'sub_new', 'skip_gateway' => true]))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled')
        ->and($adapter->canceledSubscriptionId)->toBe('sub_new');
});

test('does not call the gateway when canceling a subscription without a sid', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class, [
        'status' => 'active',
        'sid' => null,
    ]);

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(function (): void {
        throw new RuntimeException('The gateway should not be loaded');
    });
    $service->setDi($di);

    expect($service->update($subscriptionModel, ['status' => 'canceled']))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled');
});

test('schedules a subscription cancellation at the gateway', function (): void {
    $subscription = createEntity(\Box\Mod\Invoice\Entity\Subscription::class, [
        'sid' => 'sub_123',
        'pay_gateway_id' => 2,
    ]);

    $gateway = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);

    $adapter = new class {
        public ?string $scheduledSubscriptionId = null;

        public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
        {
            $this->scheduledSubscriptionId = $subscriptionId;
        }
    };

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('find')->once()->with(2)->andReturn($gateway);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);
    $emMock->shouldReceive('persist')->andReturnNull();
    $emMock->shouldReceive('flush')->andReturnNull();

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $payGatewayService);

    $service = new ServiceSubscription();
    $service->setDi($di);
    $service->scheduleCancellation($subscription);

    expect($adapter->scheduledSubscriptionId)->toBe('sub_123')
        ->and($subscription->status)->toBe(ServiceSubscription::STATUS_PENDING_CANCELLATION);
});

test('updates subscription status from a gateway without calling the adapter', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class, ['status' => 'active']);

    $subscriptionId = 1;
    $subRepo = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subRepo->shouldReceive('find')->with($subscriptionId)->andReturn($subscriptionModel);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class)->shouldIgnoreMissing();
    $emMock->shouldReceive('getRepository')->with(Box\Mod\Invoice\Entity\Subscription::class)->andReturn($subRepo);
    $emMock->shouldReceive('persist');
    $emMock->shouldReceive('flush');

    $di = container();
    $di['em'] = $emMock;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(function (): void {
        throw new RuntimeException('The gateway should not be loaded');
    });
    $service->setDi($di);

    expect($service->updateStatusFromGateway($subscriptionId, 'canceled'))->toBeTrue()
        ->and($subscriptionModel->status)->toBe('canceled');
});

test('cancels subscriptions linked to an order', function (): void {
    $subscriptionEntity = new Box\Mod\Invoice\Entity\Subscription();
    $idProp = new ReflectionProperty(Box\Mod\Invoice\Entity\Subscription::class, 'id');
    $idProp->setValue($subscriptionEntity, 7);

    $orderModel = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 10]);

    $subscriptionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subscriptionRepoMock->shouldReceive('find')
        ->once()
        ->with(7)
        ->andReturn($subscriptionEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Subscription::class)
        ->andReturn($subscriptionRepoMock);

    $service = Mockery::mock(ServiceSubscription::class)->makePartial();
    $service->shouldReceive('cancel')->once()->with($subscriptionEntity);
    $di = container();
    $di['em'] = $emMock;
    $di['dbal'] = createSubscriptionDbal();
    $service->setDi($di);

    $service->cancelForOrder($orderModel);
});

test('finalizes a scheduled cancellation by canceling its order and service', function (): void {
    $subscriptionEntity = new Box\Mod\Invoice\Entity\Subscription();
    $subscriptionEntity->setStatus(ServiceSubscription::STATUS_PENDING_CANCELLATION);
    $subscriptionEntity->setRelType('invoice');
    $subscriptionEntity->setRelId(25);
    $idProp = new ReflectionProperty(Box\Mod\Invoice\Entity\Subscription::class, 'id');
    $idProp->setValue($subscriptionEntity, 7);

    $orderEntity = new Box\Mod\Order\Entity\Order();
    $orderIdProp = new ReflectionProperty($orderEntity, 'id');
    $orderIdProp->setValue($orderEntity, 10);
    $orderEntity->setStatus(\Box\Mod\Order\Entity\Order::STATUS_ACTIVE);

    $dbal = createSubscriptionDbal();
    $dbal->insert('client_order_meta', [
        'client_order_id' => 10,
        'name' => Box\Mod\Order\Service::META_CANCEL_AT_PERIOD_END,
        'value' => '1',
    ]);

    $subscriptionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subscriptionRepoMock->shouldReceive('find')->once()->with(7)->andReturn($subscriptionEntity);

    $orderRepoMock = Mockery::mock(Box\Mod\Order\Repository\OrderRepository::class);
    $orderRepoMock->shouldReceive('find')->once()->with(10)->andReturn($orderEntity);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Subscription::class)
        ->andReturn($subscriptionRepoMock);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Order\Entity\Order::class)
        ->andReturn($orderRepoMock);
    $emMock->shouldReceive('persist')->andReturnNull();
    $emMock->shouldReceive('flush')->andReturnNull();

    $orderService = Mockery::mock(Box\Mod\Order\Service::class);
    $orderService->shouldReceive('finalizeCancellationFromGateway')
        ->once()
        ->with($orderEntity, 'Subscription ended at the payment gateway')
        ->andReturn(true);

    $di = container();
    $di['em'] = $emMock;
    $di['dbal'] = $dbal;
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod_service'] = $di->protect(fn () => $orderService);

    $service = new ServiceSubscription();
    $service->setDi($di);

    expect($service->finalizeCancellationFromGateway(7))->toBeTrue()
        ->and($subscriptionEntity->getStatus())->toBe('canceled');
});

test('reports end-of-period cancellation support for active gateway subscriptions', function (): void {
    $order = createEntity(\Box\Mod\Order\Entity\Order::class, ['id' => 10]);

    $subscriptionEntity = new Box\Mod\Invoice\Entity\Subscription();
    $subscriptionEntity->setSid('sub_123');
    $subscriptionEntity->setPayGatewayId(2);
    $idProp = new ReflectionProperty(Box\Mod\Invoice\Entity\Subscription::class, 'id');
    $idProp->setValue($subscriptionEntity, 7);

    $gateway = createEntity(\Box\Mod\Invoice\Entity\PayGateway::class);
    $adapter = new class {
        public function cancelSubscriptionAtPeriodEnd(string $subscriptionId): void
        {
        }
    };

    $subscriptionRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\SubscriptionRepository::class);
    $subscriptionRepoMock->shouldReceive('find')->once()->with(7)->andReturn($subscriptionEntity);

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('find')->once()->with(2)->andReturn($gateway);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\Subscription::class)
        ->andReturn($subscriptionRepoMock);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);

    $gatewayService = Mockery::mock(ServicePayGateway::class);
    $gatewayService->shouldReceive('getPaymentAdapter')->once()->with($gateway)->andReturn($adapter);

    $di = container();
    $di['em'] = $emMock;
    $di['dbal'] = createSubscriptionDbal();
    $di['mod_service'] = $di->protect(fn () => $gatewayService);

    $service = new ServiceSubscription();
    $service->setDi($di);

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

    $di = container();
    $di['dbal'] = $dbal;

    $service = new ServiceSubscription();
    $service->setDi($di);

    expect($service->findIdBySid('sub_123'))->toBe(7)
        ->and($service->findIdBySid('sub_missing'))->toBeNull();
});

test('converts to api array', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class, [
        'client_id' => 1,
        'pay_gateway_id' => 2,
    ]);

    $clientEntity = new Box\Mod\Client\Entity\Client();
    $clientIdProp = new ReflectionProperty($clientEntity, 'id');
    $clientIdProp->setValue($clientEntity, 1);

    $gatewayEntity = new Box\Mod\Invoice\Entity\PayGateway();
    $gatewayEntity->setName('Test Gateway');

    $clientRepoMock = Mockery::mock(Box\Mod\Client\Repository\ClientRepository::class);
    $clientRepoMock->shouldReceive('find')
        ->with(1)
        ->andReturn($clientEntity);

    $payGatewayRepoMock = Mockery::mock(Box\Mod\Invoice\Repository\PayGatewayRepository::class);
    $payGatewayRepoMock->shouldReceive('find')
        ->with(2)
        ->andReturn($gatewayEntity);

    $clientServiceMock = Mockery::mock(ClientService::class);
    $clientServiceMock->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $payGatewayService = Mockery::mock(ServicePayGateway::class);
    $payGatewayService->shouldReceive('toApiArray')
        ->atLeast()->once()
        ->andReturn([]);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Client\Entity\Client::class)
        ->andReturn($clientRepoMock);
    $emMock->shouldReceive('getRepository')
        ->with(Box\Mod\Invoice\Entity\PayGateway::class)
        ->andReturn($payGatewayRepoMock);

    $di = container();
    $di['mod_service'] = $di->protect(function ($serviceName, $sub = '') use ($clientServiceMock, $payGatewayService) {
        if ($serviceName == 'Client') {
            return $clientServiceMock;
        }
        if ($sub == 'PayGateway') {
            return $payGatewayService;
        }
    });
    $di['em'] = $emMock;
    $service->setDi($di);

    $result = $service->toApiArray($subscriptionModel);
    expect($result)->toBeArray();
    expect($result)->toHaveKey('id');
    expect($result)->toHaveKey('client');
    expect($result)->toHaveKey('gateway');
    expect($result['client'])->toBeArray();
    expect($result['gateway'])->toBeArray();
});

test('deletes a subscription', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class);

    $eventsMock = Mockery::mock('\Box_EventManager');
    $eventsMock->shouldReceive('fire')
        ->atLeast()->once();

    $di = container();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['events_manager'] = $eventsMock;
    $service->setDi($di);

    $result = $service->delete($subscriptionModel);
    expect($result)->toBeTrue();
});

test('gets search query with various parameters', function (array $data, string $expectedSqlPart, array $expectedParams): void {
    $service = new ServiceSubscription();
    $di = container();

    $service->setDi($di);
    $result = $service->getSearchQuery($data);

    expect($result)->toBeArray();
    expect($result[0])->toBeString();
    expect($result[1])->toBeArray();

    expect($result[1])->toBe($expectedParams);
    expect(str_contains((string) $result[0], $expectedSqlPart))->toBeTrue();
})->with([
    [
        [], 'FROM subscription', [],
    ],
    [
        ['status' => 'active'], 'AND status = :status', ['status' => 'active'],
    ],
    [
        ['invoice_id' => '1'], 'AND invoice_id = :invoice_id', ['invoice_id' => '1'],
    ],
    [
        ['gateway_id' => '2'], 'AND gateway_id = :gateway_id', ['gateway_id' => '2'],
    ],
    [
        ['client_id' => '3'], 'AND client_id  = :client_id', ['client_id' => '3'],
    ],
    [
        ['currency' => 'EUR'], 'AND currency =  :currency', ['currency' => 'EUR'],
    ],
    [
        ['date_from' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) >= :date_from', ['date_from' => '1234567'],
    ],
    [
        ['date_to' => '1234567'], 'AND UNIX_TIMESTAMP(created_at) <= :date_to', ['date_to' => '1234567'],
    ],
    [
        ['id' => '10'], 'AND id = :id', ['id' => '10'],
    ],
    [
        ['sid' => '10'], 'AND sid = :sid', ['sid' => '10'],
    ],
]);

test('returns false when invoice is not subscribable', function (): void {
    $service = new ServiceSubscription();
    $dbalMock = Mockery::mock(Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([]);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeFalse();
});

test('checks if invoice is subscribable', function (): void {
    $service = new ServiceSubscription();
    $dbalMock = Mockery::mock(Connection::class);

    $getAllResults = [
        ['period' => '1W', 'price' => 10, 'quantity' => 1],
    ];
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn($getAllResults);

    $di = container();
    $di['dbal'] = $dbalMock;
    $service->setDi($di);

    $invoice_id = 2;
    $result = $service->isSubscribable($invoice_id);
    expect($result)->toBeBool()->toBeTrue();
});

test('gets subscription period', function (): void {
    $serviceMock = Mockery::mock(ServiceSubscription::class)->makePartial()->shouldAllowMockingProtectedMethods();

    $period = '1W';
    $dbalMock = Mockery::mock(Connection::class);
    $dbalMock->shouldReceive('fetchAllAssociative')
        ->atLeast()->once()
        ->andReturn([['period' => $period, 'price' => 10, 'quantity' => 1]]);

    $di = container();
    $di['dbal'] = $dbalMock;
    $serviceMock->setDi($di);

    $invoiceModel = createEntity(\Box\Mod\Invoice\Entity\Invoice::class);

    $result = $serviceMock->getSubscriptionPeriod($invoiceModel);
    expect($result)->toBeString()->toBe($period);
});

test('unsubscribes', function (): void {
    $service = new ServiceSubscription();
    $subscriptionModel = createEntity(\Box\Mod\Invoice\Entity\Subscription::class);

    $di = container();
    $service->setDi($di);

    $service->unsubscribe($subscriptionModel);
    expect($subscriptionModel->status)->toBe('canceled');
});
