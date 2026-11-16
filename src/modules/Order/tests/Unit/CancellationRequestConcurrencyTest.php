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
        $competing = fn () => $operation === 'withdraw' ? $requests->withdraw($order, $client) : $requests->stop($request->getId());
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
        $requests->withdraw($order, $client);
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
            'withdraw' => $requests->withdraw($order, $client),
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
            'withdraw' => $requests->withdraw($order, $client),
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
        $requests->withdraw($order, $client);
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
    expect(fn () => $requests->accept($first->getId()))->toThrow(InformationException::class, 'no longer waiting');
})->with(['withdraw', 'stop', 'accept']);

test('a committed schedule cannot be withdrawn or stopped after request acknowledgement crashes', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext();
    $orders->shouldReceive('saveStatusChange')->once()->andThrow(new Error('stopped after scheduling'));
    expect(fn () => $requests->accept($request->getId()))->toThrow(Error::class, 'stopped after scheduling');
    $change = fn () => $operation === 'withdraw' ? $requests->withdraw($order, $client) : $requests->stop($request->getId());
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
    expect(fn () => $fresh->withdraw($em->find(Order::class, $order->getId()), $em->find(Client::class, $client->getId())))
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
