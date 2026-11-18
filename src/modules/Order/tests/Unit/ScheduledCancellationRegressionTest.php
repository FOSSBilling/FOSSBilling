<?php

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Service;

use function Tests\Helpers\container;

require_once dirname(__DIR__) . '/Fixtures/RenewalContext.php';

test('cancellation claim reads bypass stale managed metadata', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $meta = (new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('0');
    $em->persist($meta);
    $em->flush();
    $repo = $em->getRepository(OrderMeta::class);
    expect($repo->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('0');
    $em->getConnection()->executeStatement('UPDATE client_order_meta SET value = ? WHERE id = ?', ['1', $meta->getId()]);
    $em->refresh($order);
    expect($repo->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');
});

test('a committed cancellation claim blocks renewal and expiry changes before gateway I/O', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_callOnService')->never();
    $service->shouldReceive('finalizeCancellationAfterClaim')->once()->andReturn(true);
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('cancelForOrder')->once()->andReturnUsing(function () use ($em, $order, $service): int {
        $claim = $em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM] ?? null;
        expect($em->getConnection()->isTransactionActive())->toBeFalse()
            ->and($claim)->toBe('1');
        expect(fn () => $service->renewFromOrder($order))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
        expect(fn () => $service->updateOrder($order, ['expires_at' => (new DateTimeImmutable('+1 year'))->format('Y-m-d H:i:s')]))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class);

        return 1;
    });
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);
    expect($service->processScheduledCancellation($order))->toBeTrue()
        ->and($order->getExpiresAt()->getTimestamp())->toBeLessThan(time());
});

test('a scheduled cancellation blocks renewal and expiry edits before a gateway claim exists', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    expect(fn () => $service->renewFromOrder($order))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
    expect(fn () => $service->updateOrder($order, ['expires_at' => (new DateTimeImmutable('+1 year'))->format('Y-m-d H:i:s')]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
});

test('scheduling rechecks a stale order before contacting the provider', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->getConnection()->executeStatement('UPDATE client_order SET status = ? WHERE id = ?', [Order::STATUS_CANCELED, $order->getId()]);
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldNotReceive('scheduleCancellationForOrder');
    $service = new Service();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);

    expect(fn () => $service->scheduleCancellationFromOrder($order))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Cannot cancel canceled order');
});

test('provider scheduling failure leaves a committed review hold before any renewal can run', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE client_order_id = ?', [$order->getId()]);
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('canCancelAtPeriodEndForOrder')->once()->with($order)->andReturn(true);
    $subscription->shouldReceive('scheduleCancellationForOrder')->once()->with($order)->andThrow(new RuntimeException('provider timeout'));
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);

    expect(fn () => $service->scheduleCancellationFromOrder($order))->toThrow(RuntimeException::class, 'provider timeout');
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))
        ->toMatchArray([Service::META_CANCEL_AT_PERIOD_END => '1', Service::META_SCHEDULED_CANCEL_CLAIM => '1']);
    expect(fn () => $service->renewFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
    expect(fn () => $service->scheduleCancellationFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff review');
});

test('gateway failure leaves a durable staff-review hold instead of retrying automatically', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('cancelForOrder')->once()->andThrow(new RuntimeException('gateway timeout'));
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('finalizeCancellationAfterClaim');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);
    expect(fn () => $service->processScheduledCancellation($order))->toThrow(RuntimeException::class, 'gateway timeout');
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');
    expect($service->processScheduledCancellation($order))->toBeFalse();
});

test('local cleanup resumes after an interrupted scheduled cancellation without repeating termination', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->twice();
    $attempt = 0;
    $product->shouldReceive('releaseReservedStockForOrder')->twice()->andReturnUsing(function () use (&$attempt): void {
        if (++$attempt === 1) {
            throw new RuntimeException('temporary cleanup failure');
        }
    });
    $events = new Symfony\Component\EventDispatcher\EventDispatcher();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldReceive('saveStatusChange')->once();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $product);
    $di['event_dispatcher'] = $events;
    $service->setDi($di);
    expect(fn () => $service->processScheduledCancellation($order))->toThrow(RuntimeException::class, 'temporary cleanup failure');
    expect($order->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('2');
    expect($service->processScheduledCancellation($order))->toBeTrue()
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))->not->toHaveKey(Service::META_SCHEDULED_CANCEL_CLAIM);
});

test('suspended cancellation batch respects scheduled review holds and future end dates', function (string $claim): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_SUSPENDED)->setSuspendedAt(new DateTimeImmutable('-90 days'))->setExpiresAt(new DateTimeImmutable('+30 days'));
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue($claim));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial();
    $service->shouldNotReceive('cancelFromOrder');
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): object => new class {
        public function getConfig(): array
        {
            return ['batch_cancel_suspended' => true, 'batch_cancel_suspended_after_days' => 7];
        }
    });
    $service->setDi($di);
    expect($service->batchCancelSuspended())->toBeTrue()->and($order->getStatus())->toBe(Order::STATUS_SUSPENDED);
})->with(['0', '1']);

test('suspended cancellation batch never repeats termination for an orphaned cleanup claim', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_SUSPENDED)->setSuspendedAt(new DateTimeImmutable('-90 days'));
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$order->getId(), Service::META_CANCEL_AT_PERIOD_END]);
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): object => new class {
        public function getConfig(): array
        {
            return ['batch_cancel_suspended' => true, 'batch_cancel_suspended_after_days' => 7];
        }
    });
    $service->setDi($di);

    expect($service->batchCancelSuspended())->toBeTrue()
        ->and($order->getStatus())->toBe(Order::STATUS_SUSPENDED)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('2');
});

test('renewal is rejected while a termination claim is pending', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->renewFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
});

test('staff retry updates a claim even when its ORM entity was previously cached', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $claim = (new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('0');
    $em->persist($claim);
    $em->flush();
    $em->getConnection()->executeStatement('UPDATE client_order_meta SET value = ? WHERE id = ?', ['1', $claim->getId()]);
    $service = new Service();
    $di = container();
    $di['em'] = $em;
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->once()->andReturn(false);
    $di['mod_service'] = $di->protect(fn (): object => $subscriptions);
    $service->setDi($di);
    expect($service->reviewScheduledCancellation($order, 'retry'))->toBeTrue();
    expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE id = ?', [$claim->getId()]))->toBe('0');
});

test('a failed renewal keeps its failure status after the row-lock transaction rolls back', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE client_order_id = ?', [$order->getId()]);
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_callOnService')->once()->andThrow(new RuntimeException('registrar unavailable'));
    $service->shouldReceive('saveStatusChange')->twice();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->renewFromOrder($order))->toThrow(RuntimeException::class, 'registrar unavailable');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order WHERE id = ?', [$order->getId()]))->toBe(Order::STATUS_FAILED_RENEW);
});

test('staff expiry changes cannot pass an in-flight cancellation claim', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    $service = new Service();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->updateOrder($order, ['expires_at' => (new DateTimeImmutable('+1 year'))->format('Y-m-d H:i:s')]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
});

test('direct cancellation also respects an uncertain scheduled cancellation claim', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->cancelFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff review');
});

test('uncancel refuses unresolved cancellation claims before contacting the service', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_CANCELED);
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    expect(fn () => $service->uncancelFromOrder($order))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Finish or review');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order WHERE id = ?', [$order->getId()]))
        ->toBe(Order::STATUS_CANCELED);
});

test('forced activation cannot revive an order whose cancellation still needs cleanup', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_CANCELED);
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldNotReceive('createFromOrder');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    expect(fn () => $service->activateOrder($order, ['force' => true]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Finish or review');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order WHERE id = ?', [$order->getId()]))
        ->toBe(Order::STATUS_CANCELED);
});

test('status-only updates cannot strand an uncertain cancellation claim', function (string $claim, string $current, string $next): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus($current);
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue($claim));
    $em->flush();
    $service = new Service();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    expect(fn () => $service->updateOrder($order, ['status' => $next]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
    expect($em->getConnection()->fetchOne('SELECT status FROM client_order WHERE id = ?', [$order->getId()]))->toBe($current);
})->with([
    'uncertain gateway action' => ['1', Order::STATUS_ACTIVE, Order::STATUS_CANCELED],
    'pending local cleanup' => ['2', Order::STATUS_CANCELED, Order::STATUS_ACTIVE],
]);

test('admin metadata updates cannot erase or overwrite cancellation recovery checkpoints', function (array $meta): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    $service = new Service();
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);

    expect(fn () => $service->updateOrder($order, ['meta' => $meta]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))
        ->toMatchArray([Service::META_CANCEL_AT_PERIOD_END => '1', Service::META_SCHEDULED_CANCEL_CLAIM => '1']);
})->with([
    'clear all' => [[]],
    'overwrite claim' => [[Service::META_SCHEDULED_CANCEL_CLAIM => '0']],
    'remove schedule' => [[Service::META_CANCEL_AT_PERIOD_END => '0']],
    'overwrite recovery options' => [[Service::META_SCHEDULED_CANCEL_OPTIONS => '{}']],
]);

test('cancellation marker deletion is atomic when the claim delete fails', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $em->getConnection()->executeStatement("CREATE TRIGGER fail_claim_delete BEFORE DELETE ON client_order_meta WHEN OLD.name = 'scheduled_cancel_claim' BEGIN SELECT RAISE(ABORT, 'claim delete failed'); END");
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->once();
    $product->shouldReceive('releaseReservedStockForOrder')->once();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldReceive('saveStatusChange')->once();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $product);
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $service->setDi($di);
    expect(fn () => $service->processScheduledCancellation($order))->toThrow(Doctrine\DBAL\Exception\DriverException::class);
    $meta = $em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId());
    expect($meta[Service::META_CANCEL_AT_PERIOD_END])->toBe('1')
        ->and($meta[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('2');
    $em->getConnection()->executeStatement('DROP TRIGGER fail_claim_delete');
    expect($service->processScheduledCancellation($order))->toBeTrue();
    $meta = $em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId());
    expect($meta)->not->toHaveKey(Service::META_CANCEL_AT_PERIOD_END)
        ->and($meta)->not->toHaveKey(Service::META_SCHEDULED_CANCEL_CLAIM);
});

test('queued cancellation notification and history roll back together on an interrupted cleanup', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $connection = $em->getConnection();
    $connection->executeStatement('CREATE TABLE cancellation_test_effect (kind TEXT NOT NULL)');
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->twice();
    $product->shouldReceive('releaseReservedStockForOrder')->twice();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldReceive('saveStatusChange')->twice()->andReturnUsing(
        static fn () => $connection->executeStatement("INSERT INTO cancellation_test_effect (kind) VALUES ('history')"),
    );
    $attempts = 0;
    $events = new Symfony\Component\EventDispatcher\EventDispatcher();
    $events->addListener(Box\Mod\Order\Event\AfterAdminOrderCancelEvent::class, static function () use ($connection, &$attempts): void {
        $connection->executeStatement("INSERT INTO cancellation_test_effect (kind) VALUES ('notification')");
        if (++$attempts === 1) {
            throw new RuntimeException('interrupted after queueing');
        }
    });
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $product);
    $di['event_dispatcher'] = $events;
    $service->setDi($di);

    expect(fn () => $service->processScheduledCancellation($order))->toThrow(RuntimeException::class, 'interrupted after queueing');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM cancellation_test_effect'))->toBe(0)
        ->and($order->getStatus())->toBe(Order::STATUS_ACTIVE)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('2');
    expect($service->processScheduledCancellation($order))->toBeTrue()
        ->and($connection->fetchFirstColumn('SELECT kind FROM cancellation_test_effect ORDER BY rowid'))->toBe(['history', 'notification'])
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))->not->toHaveKey(Service::META_SCHEDULED_CANCEL_CLAIM);
});

test('cron recovers a legacy orphaned claim without repeating external cancellation', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_CANCELED);
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $em->flush();
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$order->getId(), Service::META_CANCEL_AT_PERIOD_END]);
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldNotReceive('finishScheduledCancellation');
    $service->shouldReceive('getExpiredOrders')->once()->andReturn([]);
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['logger'] = new Tests\Helpers\TestLogger();
    $di['mod'] = $di->protect(fn (): object => new class {
        public function getConfig(): array
        {
            return [];
        }
    });
    $service->setDi($di);
    expect($service->batchSuspendExpired())->toBeTrue();
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))->not->toHaveKey(Service::META_SCHEDULED_CANCEL_CLAIM);
});

test('scheduled cancellation refuses an outer transaction before external work', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldNotReceive('finalizeCancellationAfterClaim');
    $di = container();
    $di['em'] = $em;
    $service->setDi($di);
    $em->getConnection()->beginTransaction();

    try {
        expect(fn () => $service->processScheduledCancellation($order))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'committed claim');
        expect(fn () => $service->finalizeCancellationFromGateway($order))
            ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'committed claim');
        expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId()))
            ->not->toHaveKey(Service::META_SCHEDULED_CANCEL_CLAIM);
    } finally {
        $em->getConnection()->rollBack();
    }
});

test('staff cannot confirm service cancellation while a gateway subscription remains active', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('1'));
    $em->flush();
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('hasUncanceledSubscriptionForOrder')->once()->with($order)->andReturn(true);
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldNotReceive('updateOrderMeta');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);
    expect(fn () => $service->reviewScheduledCancellation($order, 'completed'))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'gateway subscription');
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');
});

test('staff retry reschedules an interrupted provider request and retains the hold on another failure', function (bool $failsAgain, bool $legacy): void {
    [$em, $order] = cancellationRegressionContext();
    foreach ([Service::META_SCHEDULED_CANCEL_CLAIM => '1', Service::META_SCHEDULED_CANCEL_PHASE => 'scheduling'] as $name => $value) {
        $em->persist((new OrderMeta())->setOrder($order)->setName($name)->setValue($value));
    }
    $em->flush();
    if ($legacy) {
        $em->getConnection()->delete('client_order_meta', ['client_order_id' => $order->getId(), 'name' => Service::META_SCHEDULED_CANCEL_PHASE]);
    }
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    if ($legacy) {
        $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->once()->andReturn(true);
    }
    $attempt = $subscriptions->shouldReceive('scheduleCancellationForOrder')->once()->with($order);
    if ($failsAgain) {
        $attempt->andThrow(new RuntimeException('still unavailable'));
    } else {
        $attempt->andReturn(1);
    }
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscriptions);
    $service = new Service();
    $service->setDi($di);
    if ($failsAgain) {
        expect(fn () => $service->reviewScheduledCancellation($order, 'retry'))->toThrow(RuntimeException::class, 'still unavailable');
    } else {
        expect($service->reviewScheduledCancellation($order, 'retry'))->toBeTrue();
        // A second scheduling request must not contact the provider again.
        expect($service->scheduleCancellationFromOrder($order))->toBeTrue();
    }
    $meta = $em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId());
    expect($meta)->toMatchArray([Service::META_CANCEL_AT_PERIOD_END => '1', Service::META_SCHEDULED_CANCEL_CLAIM => $failsAgain ? '1' : '0']);
    expect($meta[Service::META_SCHEDULED_CANCEL_PHASE] ?? null)->toBe($failsAgain ? ($legacy ? null : 'scheduling') : 'scheduled');
})->with([false, true])->with([false, true]);

test('scheduled cancellation recovery accepts failed-renew orders', function (string $entry): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_FAILED_RENEW);
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('finalizeCancellationAfterClaim')->once()->with($order, Mockery::any())->andReturn(true);
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    if ($entry === 'processScheduledCancellation') {
        $subscriptions->shouldReceive('cancelForOrder')->once()->andReturn(1);
    }
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscriptions);
    $service->setDi($di);
    expect($service->$entry($order))->toBeTrue();
})->with(['processScheduledCancellation', 'finalizeCancellationFromGateway']);

test('standalone unsuspend respects fresh cancellation recovery holds', function (string $claim): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setStatus(Order::STATUS_SUSPENDED);
    $meta = (new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('0');
    $em->persist($meta);
    $em->flush();
    $em->getConnection()->update('client_order_meta', ['value' => $claim], ['id' => $meta->getId()]);
    [$service] = cronRenewalRegressionServices($em);
    $service->shouldNotReceive('_callOnService');
    expect(fn () => $service->unsuspendFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff review')
        ->and($order->getStatus())->toBe(Order::STATUS_SUSPENDED);
})->with(['1', '2']);

test('immediate cancellation of a scheduled order commits the hold before subscription I/O', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setExpiresAt(new DateTimeImmutable('+1 year'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('cancelForOrder')->once()->andReturnUsing(function () use ($em, $order): never {
        expect($em->getConnection()->isTransactionActive())->toBeFalse()
            ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');

        throw new RuntimeException('subscription outcome uncertain');
    });
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscription);
    $service->setDi($di);
    expect(fn () => $service->cancelFromOrder($order))->toThrow(RuntimeException::class, 'subscription outcome');
    $order->setExpiresAt(new DateTimeImmutable('-1 day'));
    $em->flush();
    expect($service->processScheduledCancellation($order))->toBeFalse();
    expect(fn () => $service->renewFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect(fn () => $service->cancelFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'staff review');
});

test('scheduled immediate cancellation preserves reason and event skipping', function (bool $skip): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setExpiresAt(new DateTimeImmutable('+1 year'));
    $em->flush();
    $orderOfCalls = [];
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_CANCEL)->andReturnUsing(function () use (&$orderOfCalls): void { $orderOfCalls[] = 'service'; });
    $service->shouldReceive('saveStatusChange')->once();
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('cancelForOrder')->once()->andReturnUsing(function () use (&$orderOfCalls): int {
        $orderOfCalls[] = 'subscription';

        return 1;
    });
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->once();
    $product->shouldReceive('releaseReservedStockForOrder')->once();
    $events = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    foreach ([Box\Mod\Order\Event\BeforeAdminOrderCancelEvent::class, Box\Mod\Order\Event\AfterAdminOrderCancelEvent::class] as $event) {
        $dispatcher->addListener($event, function () use (&$events): void { ++$events; });
    }
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Product' ? $product : $subscription);
    $service->setDi($di);
    expect($service->cancelFromOrder($order, 'Customer requested immediate cancellation', $skip))->toBeTrue()
        ->and($order->getStatus())->toBe(Order::STATUS_CANCELED)->and($order->getReason())->toBe('Customer requested immediate cancellation')
        ->and($events)->toBe($skip ? 0 : 2)->and($orderOfCalls)->toBe(['subscription', 'service']);
})->with([false, true]);

test('scheduled immediate termination commits cleanup recovery without repeating external calls', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setExpiresAt(new DateTimeImmutable('+1 year'));
    $em->flush();
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_CANCEL);
    $service->shouldReceive('saveStatusChange')->once();
    $subscription = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscription->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscription->shouldReceive('cancelForOrder')->once()->andReturn(1);
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->twice();
    $calls = 0;
    $product->shouldReceive('releaseReservedStockForOrder')->twice()->andReturnUsing(function () use (&$calls): void {
        if (++$calls === 1) {
            throw new Error('crash during cleanup');
        }
    });
    $di = container();
    $di['em'] = $em;
    $events = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    foreach ([Box\Mod\Order\Event\BeforeAdminOrderCancelEvent::class, Box\Mod\Order\Event\AfterAdminOrderCancelEvent::class] as $event) {
        $dispatcher->addListener($event, function () use (&$events): void { ++$events; });
    }
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Product' ? $product : $subscription);
    $service->setDi($di);
    expect(fn () => $service->cancelFromOrder($order, 'Immediate cancellation reason', true))->toThrow(Error::class);
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('2');
    expect($service->processScheduledCancellation($order))->toBeTrue()->and($order->getStatus())->toBe(Order::STATUS_CANCELED)
        ->and($order->getReason())->toBe('Immediate cancellation reason')->and($events)->toBe(0);
});

test('scheduled cleanup restores managed promo reservations after rollback and releases each once', function (): void {
    [$em, $order] = cancellationRegressionContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Box\Mod\Product\Entity\Promo::class, Box\Mod\Product\Entity\PromoRedemption::class]));
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_SCHEDULED_CANCEL_CLAIM)->setValue('2'));
    $promo = (new Box\Mod\Product\Entity\Promo())->setCode('RECOVERY')->setUsed(2);
    $em->persist($promo);
    $checkout = (new Box\Mod\Product\Entity\PromoRedemption())->setPromo($promo)->setClientOrderId($order->getId())->setPhase('checkout')->setStatus('reserved');
    $renewal = (new Box\Mod\Product\Entity\PromoRedemption())->setPromo($promo)->setClientOrderId($order->getId())->setPhase('renewal')->setStatus('reserved');
    $other = (new Box\Mod\Product\Entity\PromoRedemption())->setPromo($promo)->setClientOrderId($order->getId() + 1)->setPhase('checkout')->setStatus('reserved');
    foreach ([$checkout, $renewal, $other] as $redemption) {
        $em->persist($redemption);
    }
    $em->flush();
    $product = Mockery::mock(Box\Mod\Product\Service::class)->makePartial();
    $calls = 0;
    $product->shouldReceive('releaseReservedStockForOrder')->twice()->andReturnUsing(function () use (&$calls): void {
        if (++$calls === 1) {
            throw new RuntimeException('stock release interrupted');
        }
    });
    $service = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $service->shouldNotReceive('_callOnService');
    $service->shouldReceive('saveStatusChange')->once();
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['mod_service'] = $di->protect(fn (): object => $product);
    $service->setDi($di);
    $product->setDi($di);
    expect(fn () => $service->processScheduledCancellation($order))->toThrow(RuntimeException::class);
    expect($checkout->getStatus())->toBe('reserved')->and($renewal->getStatus())->toBe('reserved')
        ->and((int) $em->getConnection()->fetchOne('SELECT used FROM promo WHERE id = ?', [$promo->getId()]))->toBe(2);
    expect($service->processScheduledCancellation($order))->toBeTrue();
    expect($checkout->getStatus())->toBe('released')->and($renewal->getStatus())->toBe('released')->and($other->getStatus())->toBe('reserved')
        ->and((int) $em->getConnection()->fetchOne('SELECT used FROM promo WHERE id = ?', [$promo->getId()]))->toBe(1)
        ->and($checkout->getReleaseReason())->toBe('order_canceled');
});

test('staff-approved retry resumes immediate cancellation before period end', function (bool $legacy, bool $failsAgain): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setExpiresAt(new DateTimeImmutable('+1 year'));
    $em->flush();
    $orders = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $attempt = 0;
    $subscriptions->shouldReceive('cancelForOrder')->twice()->andReturnUsing(function () use (&$attempt, $failsAgain, $em, $order): int {
        expect($em->getConnection()->isTransactionActive())->toBeFalse()
            ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');
        if (++$attempt === 1 || $failsAgain) {
            throw new RuntimeException('subscription failed');
        }

        return 1;
    });
    if ($failsAgain) {
        $orders->shouldNotReceive('_callOnService');
    } else {
        $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_CANCEL);
        $orders->shouldReceive('saveStatusChange')->once();
    }
    $product = Mockery::mock(Box\Mod\Product\Service::class);
    $product->shouldReceive('releaseReservedPromoRedemptionsForOrder')->zeroOrMoreTimes();
    $product->shouldReceive('releaseReservedStockForOrder')->zeroOrMoreTimes();
    $events = 0;
    $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
    foreach ([Box\Mod\Order\Event\BeforeAdminOrderCancelEvent::class, Box\Mod\Order\Event\AfterAdminOrderCancelEvent::class] as $event) {
        $dispatcher->addListener($event, function () use (&$events): void { ++$events; });
    }
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = $dispatcher;
    $di['mod_service'] = $di->protect(fn (string $module): object => $module === 'Product' ? $product : $subscriptions);
    $orders->setDi($di);
    expect(fn () => $orders->cancelFromOrder($order, 'Immediate request', true))->toThrow(RuntimeException::class);
    $meta = $orders->getOrderMetaRepository()->getPairsForOrder($order->getId());
    $options = json_decode($meta[Service::META_SCHEDULED_CANCEL_OPTIONS], true, 512, JSON_THROW_ON_ERROR);
    expect($options['mode'])->toBe('immediate');
    if ($legacy) {
        unset($options['mode']);
        $orders->updateOrderMeta($order, [Service::META_SCHEDULED_CANCEL_OPTIONS => json_encode($options, JSON_THROW_ON_ERROR)]);
    }
    if ($failsAgain) {
        expect(fn () => $orders->reviewScheduledCancellation($order, 'retry'))->toThrow(RuntimeException::class);
        expect($orders->getOrderMetaRepository()->getPairsForOrder($order->getId())[Service::META_SCHEDULED_CANCEL_CLAIM])->toBe('1');
        expect($orders->processScheduledCancellation($order))->toBeFalse();
    } else {
        expect($orders->reviewScheduledCancellation($order, 'retry'))->toBeTrue()->and($order->getStatus())->toBe(Order::STATUS_CANCELED)
            ->and($order->getReason())->toBe('Immediate request');
    }
    expect($events)->toBe(0);
})->with([false, true])->with([false, true]);

test('approved period-end retry remains deferred until expiry', function (): void {
    [$em, $order] = cancellationRegressionContext();
    $order->setExpiresAt(new DateTimeImmutable('+1 year'));
    foreach ([Service::META_SCHEDULED_CANCEL_CLAIM => '1', Service::META_SCHEDULED_CANCEL_PHASE => 'scheduled'] as $key => $value) {
        $em->persist((new OrderMeta())->setOrder($order)->setName($key)->setValue($value));
    }
    $em->flush();
    $orders = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $orders->shouldNotReceive('_callOnService');
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('assertNoSharedSubscriptionForOrder')->byDefault();
    $subscriptions->shouldNotReceive('cancelForOrder');
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (): object => $subscriptions);
    $orders->setDi($di);
    expect($orders->reviewScheduledCancellation($order, 'retry'))->toBeTrue()
        ->and($orders->processScheduledCancellation($order))->toBeFalse()->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});
