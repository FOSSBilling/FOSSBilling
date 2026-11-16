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
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Client::class, Order::class, OrderMeta::class, ClientOrderCancelRequest::class]));
    $client = (new Client())->setCurrency('USD');
    $em->persist($client);
    $em->flush();
    $order = (new Order())->setClientId($client->getId())->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('+1 month'));
    $em->persist($order);
    $em->flush();
    $request = (new ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setType($type)->setStatus(ClientOrderCancelRequest::STATUS_PENDING);
    $em->persist($request);
    $em->flush();
    $orders = Mockery::mock(Service::class)->makePartial();
    $orders->shouldReceive('saveStatusChange')->zeroOrMoreTimes();
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->byDefault()->andReturn(false);
    $requests = new ServiceCancelRequest();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Order' ? $orders : $subscriptions);
    $orders->setDi($di);
    $requests->setDi($di);

    return [$requests, $em, $request, $order, $client, $orders, $subscriptions];
}

test('acceptance excludes a competing withdrawal or staff stop before service action', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $orders->shouldReceive('findByClientIdAndOrderId')->once()->andReturnUsing(function () use ($requests, $operation, $order, $client, $request): Order {
        $competing = fn () => $operation === 'withdraw' ? $requests->withdraw($order, $client) : $requests->stop($request->getId());
        expect($competing)->toThrow(InformationException::class, 'already being processed');

        return $order;
    });
    $orders->shouldReceive('cancelFromOrder')->once()->andReturnTrue();
    $requests->accept($request->getId());
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order_cancel_request'))->toBe('accepted');
})->with(['withdraw', 'stop']);

test('a completed withdrawal or stop prevents a later cancellation action', function (string $operation): void {
    [$requests, $em, $request, $order, $client, $orders] = cancellationRequestConcurrencyContext(ClientOrderCancelRequest::TYPE_IMMEDIATE);
    $orders->shouldNotReceive('cancelFromOrder', 'scheduleCancellationFromOrder');
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
    $orders->shouldNotReceive('cancelFromOrder', 'scheduleCancellationFromOrder');
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
    $orders->shouldNotReceive('cancelFromOrder', 'scheduleCancellationFromOrder');
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
