<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\ClientOrderCancelRequest;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Entity\OrderStatus;
use Box\Mod\Order\Service;
use Box\Mod\Order\ServiceCancelRequest;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Core\Doctrine\NamedLock;
use FOSSBilling\Core\Exception\InformationException;

use function Tests\Helpers\container;

function cancellationRequestConcurrencyContext(string $type = ClientOrderCancelRequest::TYPE_END_OF_PERIOD): array
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 4)], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Client::class, Order::class, OrderMeta::class, OrderStatus::class, ClientOrderCancelRequest::class]));
    $client = (new Client())->setCurrency('USD');
    $em->persist($client);
    $em->flush();
    $order = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setGroupMaster(false)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->persist($order);
    $em->flush();
    $request = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType($type)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($request);
    $em->flush();
    $orders = Mockery::mock(Service::class)->makePartial();
    $orders->shouldReceive('saveStatusChange')->byDefault()->zeroOrMoreTimes();
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->byDefault()->andReturn(false);
    $subscriptions->shouldReceive('canCancelImmediatelyForOrder')->byDefault()->andReturn(true);
    $products = Mockery::mock(Box\Mod\Product\Service::class);
    $products->shouldReceive('releaseReservedPromoRedemptionsForOrder', 'releaseReservedStockForOrder')->byDefault()->andReturnNull();
    $requests = new ServiceCancelRequest();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module): object => match ($module) {
        'Order' => $orders, 'Product' => $products, default => $subscriptions,
    });
    $orders->setDi($di);
    $requests->setDi($di);

    return [$requests, $em, $request, $order, $client, $orders, $subscriptions, $products];
}

test('acceptance excludes a competing withdrawal or staff stop before service action', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $orders->shouldReceive('findByClientIdAndOrderId')->once()->andReturnUsing(function () use ($requests, $operation, $order, $client, $request): Order {
        $competing = fn () => $operation === 'withdraw' ? $requests->withdraw($order, $client, $request->getId()) : $requests->stop($request->getId());
        expect($competing)->toThrow(InformationException::class, 'already being processed');

        return $order;
    });
    $orders->shouldReceive('cancelFromRequest')->once()->andReturnTrue();
    $requests->accept($request->getId());
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted');
})->with(['withdraw', 'stop']);

test('a completed withdrawal or stop prevents a later cancellation action', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $orders->shouldNotReceive('cancelFromRequest', 'scheduleCancellationFromOrder');
    if ($operation === 'withdraw') {
        $requests->withdraw($order, $client, $request->getId());
    } else {
        $requests->stop($request->getId());
    }
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'no longer waiting');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe($operation === 'withdraw' ? 'withdrawn' : 'stopped');
})->with(['withdraw', 'stop']);

test('request operations refuse a request already held by another executor', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $orders->shouldNotReceive('cancelFromRequest', 'scheduleCancellationFromOrder');
    $lock = 'fb:cancel-request:' . $request->getId();
    expect(NamedLock::acquire($em->getConnection(), $lock, 0))->toBeTrue();

    try {
        $execute = fn () => match ($operation) {
            'accept' => $requests->accept($request->getId()),
            'withdraw' => $requests->withdraw($order, $client, $request->getId()),
            default => $requests->stop($request->getId()),
        };
        expect($execute)->toThrow(InformationException::class, 'already being processed');
        expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending');
    } finally {
        NamedLock::release($em->getConnection(), $lock);
    }
})->with(['accept', 'withdraw', 'stop']);

test('requests cannot report success before an enclosing transaction commits', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $orders->shouldNotReceive('cancelFromRequest', 'scheduleCancellationFromOrder');
    $em->getConnection()->beginTransaction();

    try {
        $execute = fn () => match ($operation) {
            'accept' => $requests->accept($request->getId()),
            'withdraw' => $requests->withdraw($order, $client, $request->getId()),
            default => $requests->stop($request->getId()),
        };
        expect($execute)->toThrow(InformationException::class, 'committed transaction');
    } finally {
        $em->getConnection()->rollBack();
    }
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending');
})->with(['accept', 'withdraw', 'stop']);

test('requests without subscriptions respect the renewal executor mutex', function (): void {
    [$requests, $em, $request, $order] = cancellationRequestConcurrencyContext();
    $request->setUpdatedAt(new DateTimeImmutable('2000-01-01'));
    $em->flush();
    $expires = $order->getExpiresAt()->format('c');
    $lock = 'fb:scheduled-cancel:' . $order->getId();
    expect(NamedLock::acquire($em->getConnection(), $lock, 0))->toBeTrue();

    try {
        expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'already being processed');
        expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
            ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0)
            ->and($order->getExpiresAt()->format('c'))->toBe($expires);
    } finally {
        NamedLock::release($em->getConnection(), $lock);
    }
    $requests->accept($request->getId());
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted')
        ->and($em->getConnection()->fetchOne("SELECT value FROM client_order_meta WHERE name = 'cancel_at_period_end'"))->toBe('1');
});

test('requests without subscriptions recheck expiry under the scheduling lock', function (): void {
    [$requests, $em, $request, $order] = cancellationRequestConcurrencyContext();
    $em->getConnection()->executeStatement('UPDATE client_order SET expires_at = NULL WHERE id = ?', [$order->getId()]);
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'end of its period');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
});

test('request scheduling preserves an unresolved cancellation claim', function (): void {
    [$requests, $em, $request, $order] = cancellationRequestConcurrencyContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'staff review');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta'))->toBe('1');
});

test('request scheduling still rejects unsupported subscription cancellation', function (): void {
    [$requests, $em, $request, , , , $subscriptions] = cancellationRequestConcurrencyContext();
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturn(true);
    $subscriptions->shouldReceive('canCancelAtPeriodEndForOrder')->once()->andReturn(false);
    $subscriptions->shouldNotReceive('scheduleCancellationForOrder');
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'payment provider');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
        ->and($em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
});

test('failed subscription scheduling retains its durable hold and releases the request mutex', function (): void {
    [$requests, $em, $request, $order, , , $subscriptions] = cancellationRequestConcurrencyContext();
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturn(true);
    $subscriptions->shouldReceive('canCancelAtPeriodEndForOrder')->once()->andReturn(true);
    $subscriptions->shouldReceive('scheduleCancellationForOrder')->once()->andThrow(new RuntimeException('provider outcome uncertain'));
    expect(fn () => $requests->accept($request->getId()))->toThrow(RuntimeException::class, 'outcome uncertain');
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'staff review');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_CLAIM]))->toBe('1')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_PHASE]))->toBe('scheduling');
    $lock = 'fb:cancel-request:' . $request->getId();
    expect(NamedLock::acquire($em->getConnection(), $lock, 0))->toBeTrue();
    NamedLock::release($em->getConnection(), $lock);
});

test('accepted subscription requests commit scheduling checkpoints before provider work', function (): void {
    [$requests, $em, $request, , , , $subscriptions] = cancellationRequestConcurrencyContext();
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturn(true);
    $subscriptions->shouldReceive('canCancelAtPeriodEndForOrder')->once()->andReturn(true);
    $subscriptions->shouldReceive('scheduleCancellationForOrder')->once()->andReturnUsing(function () use ($em): int {
        expect($em->getConnection()->isTransactionActive())->toBeFalse()
            ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_CLAIM]))->toBe('1')
            ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_PHASE]))->toBe('scheduling')
            ->and($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending');

        return 1;
    });
    $requests->accept($request->getId());
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_CLAIM]))->toBe('0')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_PHASE]))->toBe('scheduled');
});

test('request creation serializes the pending check and rejects a competing creator', function (): void {
    [$requests, $em, $oldRequest, $order, , $orders] = cancellationRequestConcurrencyContext();
    $em->remove($oldRequest);
    $em->flush();
    $other = new ServiceCancelRequest();
    $other->setDi($requests->getDi());
    $real = $em->getRepository(ClientOrderCancelRequest::class);
    $repository = Mockery::mock(Box\Mod\Order\Repository\ClientOrderCancelRequestRepository::class);
    $repository->shouldReceive('findPendingByOrderId')->once()->andReturnUsing(function (int $id) use ($other, $order, $real): ?ClientOrderCancelRequest {
        expect(fn () => $other->create($order, ClientOrderCancelRequest::TYPE_IMMEDIATE, 'competing request'))
            ->toThrow(InformationException::class, 'already being processed');

        return $real->findPendingByOrderId($id);
    });
    (new ReflectionProperty(ServiceCancelRequest::class, 'cancelRequestRepository'))->setValue($requests, $repository);
    $created = $requests->create($order, ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'first request');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_cancel_request'))->toBe(1)
        ->and($created->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING);
    expect(fn () => $other->create($order, ClientOrderCancelRequest::TYPE_IMMEDIATE, 'later request'))
        ->toThrow(InformationException::class, 'already received');
});

test('closing a legacy duplicate request closes all unprocessed requests for the order', function (string $operation): void {
    [$requests, $em, $first, $order, $client] = cancellationRequestConcurrencyContext();
    $second = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)
        ->setType(ClientOrderCancelRequest::TYPE_IMMEDIATE)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($second);
    $em->flush();
    if ($operation === 'withdraw') {
        $requests->withdraw($order, $client, $second->getId());
    } elseif ($operation === 'stop') {
        $requests->stop($second->getId());
    } else {
        $order->setStatus(Order::STATUS_CANCELED);
        $em->flush();
        $requests->accept($second->getId());
    }
    $expected = match ($operation) {
        'withdraw' => ClientOrderCancelRequest::STATUS_WITHDRAWN,
        'stop' => ClientOrderCancelRequest::STATUS_STOPPED,
        default => ClientOrderCancelRequest::STATUS_ACCEPTED,
    };
    expect(array_unique($em->getConnection()->fetchFirstColumn('SELECT status FROM client_order_cancel_request')))->toBe([$expected]);
    if ($operation === 'accept') {
        $requests->accept($first->getId());
    } else {
        expect(fn () => $requests->accept($first->getId()))->toThrow(InformationException::class, 'no longer waiting');
    }
})->with(['withdraw', 'stop', 'accept']);

test('a committed schedule cannot be withdrawn or stopped after request acknowledgement crashes', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $orders->shouldReceive('saveStatusChange')->once()->andThrow(new Error('stopped after scheduling'));
    expect(fn () => $requests->accept($request->getId()))->toThrow(Error::class, 'stopped after scheduling');
    $change = fn () => $operation === 'withdraw' ? $requests->withdraw($order, $client, $request->getId()) : $requests->stop($request->getId());
    expect($change)->toThrow(InformationException::class, 'already started');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending')
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_CANCEL_AT_PERIOD_END]))->toBe('1');
    // A fresh worker only acknowledges the already-committed local schedule.
    $em->clear();
    $fresh = new ServiceCancelRequest();
    $fresh->setDi($requests->getDi());
    $fresh->accept($request->getId());
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted');
})->with(['withdraw', 'stop']);

test('immediate requests retain review holds across provider failures and backend crashes', function (string $failure): void {
    [$requests, $em, $request, $order, $client, $orders, $subscriptions, $products] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $connection = $em->getConnection();
    $backendCalls = 0;
    $providerCalls = 0;
    $fail = true;
    $subscriptions->shouldReceive('cancelForOrder')->andReturnUsing(function () use (&$providerCalls, &$fail, $failure, $connection): int {
        ++$providerCalls;
        expect($connection->isTransactionActive())->toBeFalse()
            ->and($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [Service::META_SCHEDULED_CANCEL_CLAIM]))->toBe('1');
        if ($fail && $failure === 'provider') {
            throw new RuntimeException('provider response lost');
        }

        return 1;
    });
    $orders->shouldAllowMockingProtectedMethods()->shouldReceive('_callOnService')->andReturnUsing(function () use (&$backendCalls, &$fail, $failure): void {
        ++$backendCalls;
        if ($fail && $failure === 'backend') {
            throw new Error('backend response lost');
        }
    });
    $products->shouldReceive('releaseReservedPromoRedemptionsForOrder')->andReturnUsing(function () use (&$fail, $failure): void {
        if ($fail && $failure === 'cleanup') {
            throw new RuntimeException('local cleanup failed');
        }
    });
    expect(fn () => $requests->accept($request->getId()))->toThrow($failure === 'backend' ? Error::class : RuntimeException::class);
    $firstBackendCalls = $backendCalls;
    $firstProviderCalls = $providerCalls;
    $em->clear();
    $fresh = new ServiceCancelRequest();
    $fresh->setDi($requests->getDi());
    expect(fn () => $fresh->accept($request->getId()))->toThrow(InformationException::class, 'staff review');
    expect(fn () => $fresh->withdraw($em->find(Order::class, $order->getId()), $em->find(Client::class, $client->getId()), $request->getId()))
        ->toThrow(InformationException::class, 'already started');
    expect(fn () => $fresh->stop($request->getId()))->toThrow(InformationException::class, 'already started');
    expect($backendCalls)->toBe($firstBackendCalls)->and($providerCalls)->toBe($firstProviderCalls)
        ->and($connection->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending');
    $fail = false;
    $currentOrder = $em->find(Order::class, $order->getId());
    if ($failure === 'cleanup') {
        // Claim 2 recovers only local cleanup; neither provider action repeats.
        expect($orders->processScheduledCancellation($currentOrder))->toBeTrue();
        expect($backendCalls)->toBe($firstBackendCalls)->and($providerCalls)->toBe($firstProviderCalls);
    } else {
        expect($orders->reviewScheduledCancellation($currentOrder, 'retry'))->toBeTrue();
    }
    $fresh->accept($request->getId());
    expect($connection->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted')
        ->and($connection->fetchOne('SELECT status FROM client_order'))->toBe(Order::STATUS_CANCELED)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
})->with(['provider', 'backend', 'cleanup']);

test('client removal deletes request history before parent rows on FK and legacy schemas', function (bool $foreignKeys): void {
    [, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $connection = $em->getConnection();
    $connection->executeStatement('PRAGMA foreign_keys = ' . ($foreignKeys ? 'ON' : 'OFF'));
    $request->setStatus(ClientOrderCancelRequest::STATUS_WITHDRAWN);
    $orphan = (new ClientOrderCancelRequest())->setClient($client)->setStatus(ClientOrderCancelRequest::STATUS_ACCEPTED);
    $em->persist($orphan);
    $em->flush();
    $orders->rmByClient($client, [$order->getId()]);
    $em->remove($client);
    $em->flush();
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM client_order_cancel_request'))->toBe(0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client_order'))->toBe(0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM client'))->toBe(0);
})->with([true, false]);

test('cancellation list dates match the fields consumed by the admin template', function (): void {
    [$requests, $em, $request, $order] = cancellationRequestConcurrencyContext();
    $request->setCreatedAt(new DateTimeImmutable('2020-01-02 03:04:05'));
    $order->setExpiresAt(new DateTimeImmutable('2030-06-07 08:09:10'));
    $em->flush();
    $row = $requests->pendingList()[0];
    $twig = new Twig\Environment(new Twig\Loader\ArrayLoader([
        'dates' => '{{ request.created_at|date("Y-m-d") }} {{ request.expires_at|date("Y-m-d") }}',
    ]), ['strict_variables' => true]);
    expect($twig->render('dates', ['request' => $row]))->toBe('2020-01-02 2030-06-07');
});

test('new requests cannot hide an already scheduled or held cancellation', function (string $key, string $value): void {
    [$requests, $em, $request, $order] = cancellationRequestConcurrencyContext();
    $em->remove($request);
    $em->persist((new OrderMeta())->setOrder($order)->setName($key)->setValue($value));
    $em->flush();
    expect(fn () => $requests->create($order, ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'new request'))
        ->toThrow(InformationException::class, 'already started');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_cancel_request'))->toBe(0);
})->with([
    [Service::META_CANCEL_AT_PERIOD_END, '1'],
    [Service::META_SCHEDULED_CANCEL_CLAIM, '1'],
    [Service::META_SCHEDULED_CANCEL_CLAIM, '2'],
]);

test('successful cancellation requests persist the requested terminal state', function (string $type): void {
    [$requests, $em, $request, $order, , $orders, $subscriptions] = cancellationRequestConcurrencyContext($type);
    $expires = $order->getExpiresAt()->format('c');
    if ($type === ClientOrderCancelRequest::TYPE_IMMEDIATE) {
        $subscriptions->shouldReceive('cancelForOrder')->once()->with($order)->andReturn(1);
        $orders->shouldAllowMockingProtectedMethods()->shouldReceive('_callOnService')->once();
    } else {
        $subscriptions->shouldNotReceive('cancelForOrder', 'scheduleCancellationForOrder');
        $orders->shouldAllowMockingProtectedMethods()->shouldNotReceive('_callOnService');
    }

    $requests->accept($request->getId());
    $em->refresh($request);
    $em->refresh($order);
    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_ACCEPTED)
        ->and($request->getUpdatedAt())->toBeGreaterThan(new DateTimeImmutable('2000-01-01'))
        ->and($order->getStatus())->toBe($type === ClientOrderCancelRequest::TYPE_IMMEDIATE ? Order::STATUS_CANCELED : Order::STATUS_ACTIVE)
        ->and($order->getReason())->toBe('Cancellation request #' . $request->getId())
        ->and($order->getExpiresAt()?->format('c'))->toBe($type === ClientOrderCancelRequest::TYPE_IMMEDIATE ? null : $expires);
    $meta = $orders->getOrderMetaRepository()->getPairsForOrder($order->getId());
    expect($meta)->toBe($type === ClientOrderCancelRequest::TYPE_IMMEDIATE ? [] : [Service::META_CANCEL_AT_PERIOD_END => '1', Service::META_SCHEDULED_CANCEL_OPTIONS => json_encode(['reason' => 'Cancellation request #' . $request->getId(), 'skip_event' => false, 'request_id' => $request->getId()], JSON_THROW_ON_ERROR)]);
})->with([ClientOrderCancelRequest::TYPE_IMMEDIATE, ClientOrderCancelRequest::TYPE_END_OF_PERIOD]);

test('withdraw persists its status and update time without cancelling the service', function (): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $em->getConnection()->update('client_order_cancel_request', ['updated_at' => '2000-01-01 00:00:00'], ['id' => $request->getId()]);
    $em->refresh($request);
    $orders->shouldNotReceive('cancelFromRequest', 'scheduleCancellationFromOrder');

    $requests->withdraw($order, $client, $request->getId());
    $em->refresh($request);
    $em->refresh($order);
    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_WITHDRAWN)
        ->and($request->getUpdatedAt())->toBeGreaterThan(new DateTimeImmutable('2000-01-01'))
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($orders->getOrderMetaRepository()->getPairsForOrder($order->getId()))->toBe([]);
});

test('automatic processing accepts valid requests and leaves failures pending until another day', function (bool $firstFails): void {
    [$requests, $em, $first, $order, $client, $orders, $subscriptions] = cancellationRequestConcurrencyContext();
    if ($firstFails) {
        $order->setExpiresAt(null);
    }
    $secondOrder = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setGroupMaster(false)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->persist($secondOrder);
    $second = (new ClientOrderCancelRequest())->setOrder($secondOrder)->setClient($client)
        ->setType(ClientOrderCancelRequest::TYPE_END_OF_PERIOD)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($second);
    $em->flush();

    $module = Mockery::mock();
    $module->shouldReceive('getConfig')->twice()->andReturn(['cancel_requests_auto' => 1]);
    $lastDay = '2000-01-01';
    $system = Mockery::mock(Box\Mod\System\Service::class);
    $system->shouldReceive('getParamValue')->twice()->with('cancel_requests_last_day')->andReturnUsing(function () use (&$lastDay): string {
        return $lastDay;
    });
    $system->shouldReceive('setParamValue')->once()->with('cancel_requests_last_day', date('Y-m-d'), true)
        ->andReturnUsing(function (string $key, string $value) use (&$lastDay): bool {
            $lastDay = $value;

            return true;
        });
    $di = $requests->getDi();
    $di['mod'] = $di->protect(fn (): object => $module);
    $di['mod_service'] = $di->protect(fn (string $module): object => match ($module) {
        'Order' => $orders, 'system' => $system, default => $subscriptions,
    });

    expect($requests->runAutoProcess())->toBeTrue();
    $em->refresh($first);
    $em->refresh($second);
    expect($first->getStatus())->toBe($firstFails ? ClientOrderCancelRequest::STATUS_PENDING : ClientOrderCancelRequest::STATUS_ACCEPTED)
        ->and($second->getStatus())->toBe(ClientOrderCancelRequest::STATUS_ACCEPTED)
        ->and($lastDay)->toBe(date('Y-m-d'));
    // Making a failed request valid must not bypass the daily checkpoint.
    $order->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->flush();
    expect($requests->runAutoProcess())->toBeTrue();
    $em->refresh($first);
    expect($first->getStatus())->toBe($firstFails ? ClientOrderCancelRequest::STATUS_PENDING : ClientOrderCancelRequest::STATUS_ACCEPTED);
})->with([false, true]);

function enableCancellationAutomaticProcessing(ServiceCancelRequest $requests): void
{
    $di = $requests->getDi();
    $em = $di['em'];
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\System\Entity\Setting::class)]);
    $module = Mockery::mock();
    $module->shouldReceive('getConfig')->andReturn(['cancel_requests_auto' => 1]);
    $di['mod'] = $di->protect(fn (): object => $module);
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(function (string $module) use ($di, $services): object {
        if ($module === 'system') {
            $system = new Box\Mod\System\Service();
            $system->setDi($di);

            return $system;
        }

        return $services($module);
    });
}

test('stale withdrawal identifies its displayed request and preserves a replacement', function (): void {
    [$requests, $em, $old, $order, $client] = cancellationRequestConcurrencyContext();
    $requests->withdraw($order, $client, $old->getId());
    $replacement = $requests->create($order, ClientOrderCancelRequest::TYPE_END_OF_PERIOD, 'replacement');
    expect(fn () => $requests->withdraw($order, $client, $old->getId()))->toThrow(InformationException::class, 'no longer waiting');
    $em->refresh($replacement);
    expect($replacement->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING);
});

test('withdrawal and stop refresh their selected row after acquiring the order mutex', function (string $action): void {
    [$requests, $em, $old, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $replacement = null;
    $orders->shouldReceive('withLifecycleLock')->once()->andReturnUsing(function (Order $order, Closure $operation) use ($em, $old, $client, &$replacement): mixed {
        $em->getConnection()->update('client_order_cancel_request', ['status' => ClientOrderCancelRequest::STATUS_STOPPED], ['id' => $old->getId()]);
        $replacement = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType(ClientOrderCancelRequest::TYPE_IMMEDIATE)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
        $em->persist($replacement);
        $em->flush();

        return $operation();
    });
    $attempt = fn () => $action === 'withdraw' ? $requests->withdraw($order, $client, $old->getId()) : $requests->stop($old->getId());
    expect($attempt)->toThrow(InformationException::class, 'no longer waiting');
    $em->refresh($replacement);
    expect($replacement->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING);
})->with(['withdraw', 'stop']);

test('automatic cancellation uses the same oldest request shown to the client and attempts one per order', function (bool $failure): void {
    [$requests, $em, $first, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $duplicate = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType(ClientOrderCancelRequest::TYPE_IMMEDIATE)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($duplicate);
    $em->flush();
    enableCancellationAutomaticProcessing($requests);
    $orders->shouldNotReceive('cancelFromRequest');
    if ($failure) {
        $orders->shouldReceive('saveStatusChange')->once()->andThrow(new RuntimeException('history failed'));
    }
    expect($requests->pending($order)->getId())->toBe($first->getId())
        ->and(count($requests->pendingList()))->toBe(2);
    $requests->runAutoProcess();
    $statuses = $em->getConnection()->fetchFirstColumn('SELECT status FROM client_order_cancel_request ORDER BY id');
    expect($statuses)->toBe(array_fill(0, 2, $failure ? ClientOrderCancelRequest::STATUS_PENDING : ClientOrderCancelRequest::STATUS_ACCEPTED));
    $errors = array_filter($requests->getDi()['logger']->calls, fn (array $call): bool => $call['method'] === 'error');
    expect(count($errors))->toBe($failure ? 1 : 0);
})->with([false, true]);

test('the automatic batch holds its mutex across the marker read and scan', function (): void {
    [$requests, $em, $request] = cancellationRequestConcurrencyContext();
    enableCancellationAutomaticProcessing($requests);
    $name = 'fb:cancel-request-batch';
    expect(NamedLock::acquire($em->getConnection(), $name, 0))->toBeTrue();

    try {
        expect(fn () => $requests->runAutoProcess())->toThrow(InformationException::class, 'already being processed');
        expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING)
            ->and($em->getConnection()->fetchOne("SELECT value FROM setting WHERE param = 'cancel_requests_last_day'"))->toBeFalse();
    } finally {
        NamedLock::release($em->getConnection(), $name);
    }
    expect($requests->runAutoProcess())->toBeTrue();
});

test('end-of-period requests reject deletion holds and missing gateway expiry before provider work', function (string $hold): void {
    [$requests, $em, $request, $order, , , $subscriptions] = cancellationRequestConcurrencyContext();
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturn(true);
    $subscriptions->shouldNotReceive('scheduleCancellationForOrder');
    if ($hold === 'deletion') {
        $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_DELETION_STATE)->setValue(Box\Mod\Order\DeletionRecovery::SERVICE_DELETED));
    } else {
        $order->setExpiresAt(null);
    }
    $em->flush();
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class);
    $em->refresh($request);
    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING)
        ->and($em->getConnection()->fetchOne("SELECT value FROM client_order_meta WHERE name = 'cancel_at_period_end'"))->toBeFalse();
})->with(['deletion', 'expiry']);

test('an unsupported immediate subscription stays pending without claiming or cancelling the backend', function (): void {
    [$requests, $em, $request, , , $orders, $subscriptions] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $subscriptions->shouldReceive('canCancelImmediatelyForOrder')->andReturn(false);
    $subscriptions->shouldNotReceive('cancelForOrder');
    $orders->shouldAllowMockingProtectedMethods()->shouldNotReceive('_callOnService');
    expect(fn () => $requests->accept($request->getId()))->toThrow(InformationException::class, 'payment provider');
    $em->refresh($request);
    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_PENDING)
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_order_meta'))->toBe(0);
});

test('completed immediate cancellation is acknowledged without cancelling a reactivated order', function (): void {
    [$requests, $em, $request, $order, , $orders, $subscriptions] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $subscriptions->shouldReceive('cancelForOrder')->once()->andReturn(1);
    $orders->shouldAllowMockingProtectedMethods()->shouldReceive('_callOnService')->once();
    // Simulate a worker stopping after the order service commits, before the request service returns.
    $orders->cancelFromRequest($order, 'Cancellation request #' . $request->getId(), $request->getId());
    $em->refresh($request);
    expect($request->getStatus())->toBe(ClientOrderCancelRequest::STATUS_ACCEPTED);
    $order->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->flush();
    $fresh = new ServiceCancelRequest();
    $fresh->setDi($requests->getDi());
    $fresh->accept($request->getId());
    $em->refresh($order);
    expect($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});

test('automatic cancellation replaces a closed manager while preserving the failed claim and processing other orders', function (): void {
    [$requests, $em, $first, $order, $client, $orders, $subscriptions] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    enableCancellationAutomaticProcessing($requests);
    $secondOrder = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setGroupMaster(false)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->persist($secondOrder);
    $second = (new ClientOrderCancelRequest())->setOrder($secondOrder)->setClient($client)->setType(ClientOrderCancelRequest::TYPE_END_OF_PERIOD)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($second);
    $em->flush();
    $em->getConnection()->executeStatement("CREATE TRIGGER cancel_flush_failure BEFORE UPDATE ON client_order WHEN NEW.status = 'canceled' BEGIN SELECT RAISE(ABORT, 'cleanup failed'); END");
    $subscriptions->shouldReceive('cancelForOrder')->once()->andReturn(1);
    $orders->shouldAllowMockingProtectedMethods()->shouldReceive('_callOnService')->once();
    $di = $requests->getDi();
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(function (string $module) use ($di, $em, $services): object {
        if ($module === 'Order' && $di['em'] !== $em) {
            $fresh = Mockery::mock(Service::class)->makePartial();
            $fresh->setDi($di);
            $fresh->shouldReceive('saveStatusChange')->zeroOrMoreTimes();

            return $fresh;
        }

        return $services($module);
    });
    expect($requests->runAutoProcess())->toBeTrue()->and($em->isOpen())->toBeFalse()->and($di['em']->isOpen())->toBeTrue();
    $connection = $di['em']->getConnection();
    expect($connection->fetchOne('SELECT status FROM client_order_cancel_request WHERE id = ?', [$first->getId()]))->toBe(ClientOrderCancelRequest::STATUS_PENDING)
        ->and($connection->fetchOne('SELECT status FROM client_order_cancel_request WHERE id = ?', [$second->getId()]))->toBe(ClientOrderCancelRequest::STATUS_ACCEPTED)
        ->and($connection->fetchOne('SELECT value FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$order->getId(), Service::META_SCHEDULED_CANCEL_CLAIM]))->toBe('2')
        ->and($connection->fetchOne("SELECT value FROM setting WHERE param = 'cancel_requests_last_day'"))->toBe(date('Y-m-d'));
});

test('expiry acknowledges a durably scheduled request after its original history write failed', function (): void {
    [$requests, $em, $request, $order, , $orders, $subscriptions] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_END_OF_PERIOD);
    $orders->shouldReceive('saveStatusChange')->andReturnUsing(function (Order $order, string $note): void {
        if (str_contains($note, 'Cancellation scheduled')) {
            throw new RuntimeException('History unavailable');
        }
    });
    expect(fn () => $requests->accept($request->getId()))->toThrow(RuntimeException::class, 'History unavailable');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('pending');
    $order->setExpiresAt(new DateTimeImmutable('-1 day'));
    $em->flush();
    $orders->shouldAllowMockingProtectedMethods()->shouldReceive('_callOnService')->once();
    $subscriptions->shouldReceive('cancelForOrder')->once()->with($order)->andReturn(1);
    expect($orders->processScheduledCancellation($order))->toBeTrue();
    $em->refresh($request);
    expect($request->getStatus())->toBe('accepted');
    $order->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->flush();
    $requests->accept($request->getId());
    expect($orders->getOrderMetaRepository()->getPairsForOrder($order->getId()))->toBeEmpty()
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});

test('automatic selection includes every unlinked request and only the oldest linked request', function (): void {
    [, $em, $first, $order, $client] = cancellationRequestConcurrencyContext();
    $duplicate = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending');
    $orphanA = (new ClientOrderCancelRequest())->setClient($client)->setStatus('pending');
    $orphanB = (new ClientOrderCancelRequest())->setClient($client)->setStatus('pending');
    foreach ([$duplicate, $orphanA, $orphanB] as $request) {
        $em->persist($request);
    }
    $em->flush();
    expect($em->getRepository(ClientOrderCancelRequest::class)->getAutomaticRequestIds())->toBe([$first->getId(), $orphanA->getId(), $orphanB->getId()]);
});

test('expiry suspension honors a pending cancellation while automatic acceptance is disabled', function (): void {
    [, $em, , $order, , $orders] = cancellationRequestConcurrencyContext();
    $order->setExpiresAt(new DateTimeImmutable('-1 day'));
    $em->flush();
    $di = $orders->getDi();
    $di['mod'] = $di->protect(fn () => new class {
        public function getConfig(): array
        {
            return [];
        }
    });
    $orders->shouldReceive('getExpiredOrders')->once()->andReturn([$order]);
    $orders->shouldNotReceive('suspendFromOrder');
    expect($orders->batchSuspendExpired())->toBeTrue()->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});
