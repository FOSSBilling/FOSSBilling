<?php

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Doctrine\ORM\EntityManager;

require_once dirname(__DIR__) . '/Fixtures/RenewalContext.php';

test('free domain holds block direct and other-item renewals before unsuspension', function (string $kind, string $status): void {
    [$em, $order, $item, $orders] = freeHoldRegressionContext($kind);
    $order->setStatus($status);
    $em->flush();
    $orders->shouldNotReceive('_callOnService');
    expect(fn () => $orders->renewOrder($order, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
    expect(fn () => $orders->renewInvoiceItem($order, (int) $item->getId() + 1))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
    $api = Mockery::mock(Box\Mod\Order\Api\Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('checkPermissions')->once()->with('order', 'manage');
    $api->shouldReceive('_getOrder')->once()->andReturn($order);
    $api->shouldReceive('getService')->once()->andReturn($orders);
    expect(fn () => $api->renew(['id' => $order->getId()]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
    expect($order->getStatus())->toBe($status);
})->with(['free', 'zero'])->with([Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED]);

test('staff-approved domain retry belongs only to its original eligible item', function (string $kind, bool $hasClaim): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext($kind);
    if (!$hasClaim) {
        $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE name = ?', ['free_domain_renewal_claim_' . $item->getId()]);
        $em->clear();
        $order = $em->find(Order::class, $order->getId());
        $item = $em->find(Box\Mod\Invoice\Entity\InvoiceItem::class, $item->getId());
    }
    $executor->reviewDomainRenewal((int) $item->getId(), $kind === 'free' ? 'retry_free' : 'retry_zero');
    $orders->shouldReceive('_callOnService')->twice()->with($order, Order::ACTION_RENEW);
    expect(fn () => $orders->renewOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
    expect(fn () => $orders->renewInvoiceItem($order, (int) $item->getId() + 1))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
    $cron->doBatchPaidInvoiceActivation();
    $meta = $em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId());
    expect($item->getStatus())->toBe('executed')
        ->and($meta['free_domain_renewal_claim_' . $item->getId()])->toBe('completed')
        ->and($meta['free_domain_renewal_retry_' . $item->getId()])->toBe('0');
    $cron->doBatchPaidInvoiceActivation();
    expect($orders->renewOrder($order))->toBeTrue();
})->with(['free', 'zero'])->with([true, false]);

test('completed domain claims recover listener failure without a second registrar call', function (string $kind): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext($kind);
    $executor->reviewDomainRenewal((int) $item->getId(), $kind === 'free' ? 'retry_free' : 'retry_zero');
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $attempt = 0;
    $cron->getDi()['event_dispatcher']->addListener(Box\Mod\Order\Event\AfterAdminOrderRenewEvent::class, function () use (&$attempt): void {
        if (++$attempt === 1) {
            throw new RuntimeException('listener interrupted');
        }
    });
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('pending_setup')
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())['free_domain_renewal_claim_' . $item->getId()])->toBe('completed');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed')->and($attempt)->toBe(2);
})->with(['free', 'zero']);

test('completed domain renewal recovers after cancellation without changing the order', function (string $kind, string $interruption): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext($kind);
    $executor->reviewDomainRenewal((int) $item->getId(), $kind === 'free' ? 'retry_free' : 'retry_zero');
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $connection = $em->getConnection();
    if ($interruption === 'listener') {
        $cron->getDi()['event_dispatcher']->addListener(Box\Mod\Order\Event\AfterAdminOrderRenewEvent::class, function (): void {
            throw new RuntimeException('interrupted local delivery');
        });
        $cron->doBatchPaidInvoiceActivation();
    } else {
        $connection->executeStatement("CREATE TRIGGER fail_canceled_ack BEFORE UPDATE OF status ON invoice_item WHEN NEW.status = 'executed' BEGIN SELECT RAISE(ABORT, 'interrupted acknowledgement'); END");
        // The existing task error handler attempts to record the failure after
        // Doctrine closes the manager; persisted state below identifies the window.
        expect(fn () => $executor->executeTask($item))->toThrow(Doctrine\ORM\Exception\EntityManagerClosed::class);
        $connection->executeStatement('DROP TRIGGER fail_canceled_ack');
    }
    $itemId = (int) $item->getId();
    $orderId = (int) $order->getId();
    expect($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['free_domain_renewal_claim_' . $itemId]))->toBe('completed')
        ->and($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$itemId]))->toBe('pending_setup');

    // Reopen after interruption and model a cancellation committed before recovery.
    $newEm = new EntityManager($connection, $em->getConfiguration());
    $canceledOrder = $newEm->find(Order::class, $orderId);
    $canceledOrder->setStatus(Order::STATUS_CANCELED)->setCanceledAt(new DateTime())->setExpiresAt(null)->setReason('Staff cancellation');
    $newEm->flush();
    $canceledState = $connection->fetchAssociative('SELECT * FROM client_order WHERE id = ?', [$orderId]);
    $attempts = (int) $connection->fetchOne('SELECT attempts FROM invoice_item WHERE id = ?', [$itemId]);
    [$newOrders, $newCron] = cronRenewalRegressionServices($newEm);
    $newOrders->shouldNotReceive('_callOnService');
    expect(fn () => $newOrders->renewFromOrder($canceledOrder))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'being canceled');
    $deliveries = 0;
    $newCron->getDi()['event_dispatcher']->addListener(Box\Mod\Order\Event\AfterAdminOrderRenewEvent::class, function () use (&$deliveries, $connection, $itemId, $orderId): void {
        ++$deliveries;
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, 'fb:invoice-item-renewal:' . $itemId, 0))->toBeFalse()
            ->and(FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, 'fb:free-domain-renewal:' . $orderId, 0))->toBeFalse();
    });
    $newCron->doBatchPaidInvoiceActivation();
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$itemId]))->toBe('executed')
        ->and((int) $connection->fetchOne('SELECT attempts FROM invoice_item WHERE id = ?', [$itemId]))->toBe($attempts)
        ->and($connection->fetchAssociative('SELECT * FROM client_order WHERE id = ?', [$orderId]))->toBe($canceledState)
        ->and($deliveries)->toBe(1);
    $newCron->doBatchPaidInvoiceActivation();
    expect($deliveries)->toBe(1);
})->with(['free', 'zero'])->with(['listener', 'acknowledgement']);

test('an incomplete domain claim cannot bypass cancellation during recovery', function (string $kind): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext($kind);
    $executor->reviewDomainRenewal((int) $item->getId(), $kind === 'free' ? 'retry_free' : 'retry_zero');
    $order->setStatus(Order::STATUS_CANCELED)->setCanceledAt(new DateTime());
    $em->flush();
    $orders->shouldNotReceive('_callOnService');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('failed')->and($order->getStatus())->toBe(Order::STATUS_CANCELED)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())['free_domain_renewal_claim_' . $item->getId()])->toBe('1');
})->with(['free', 'zero']);

test('an interrupted domain outcome commit preserves the hold and consumed retry approval', function (): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext('zero');
    $executor->reviewDomainRenewal((int) $item->getId(), 'retry_zero');
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $connection = $em->getConnection();
    $connection->executeStatement("CREATE TRIGGER fail_domain_outcome BEFORE UPDATE ON client_order_meta WHEN NEW.value = 'completed' BEGIN SELECT RAISE(ABORT, 'interrupted domain outcome'); END");
    expect(fn () => $executor->executeTask($item))->toThrow(Exception::class);
    $key = 'free_domain_renewal_claim_' . $item->getId();
    expect($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [$key]))->toBe('1')
        ->and($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['free_domain_renewal_retry_' . $item->getId()]))->toBe('0');
    $connection->executeStatement('DROP TRIGGER fail_domain_outcome');
    $newEm = new EntityManager($connection, $em->getConfiguration());
    [$newOrders, $newCron] = cronRenewalRegressionServices($newEm);
    $newOrders->shouldNotReceive('_callOnService');
    $newCron->doBatchPaidInvoiceActivation();
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('failed');
    $newOrder = $newEm->find(Order::class, $order->getId());
    expect(fn () => $newOrders->renewOrder($newOrder))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved domain renewal');
});

test('resolved and historical successful free claims do not hold future renewals', function (bool $historical): void {
    [$em, $order, $item, $orders, $cron, $executor] = freeHoldRegressionContext('zero');
    if ($historical) {
        $item->setStatus('executed');
        $em->flush();
    } else {
        $executor->reviewDomainRenewal((int) $item->getId(), 'completed');
    }
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    expect($orders->renewOrder($order))->toBeTrue();
})->with([true, false]);

test('ordinary edits cannot erase free domain claims or retry approvals', function (string $prefix): void {
    [$em, $order, $item, $orders] = freeHoldRegressionContext('zero');
    $key = $prefix . $item->getId();
    expect(fn () => $orders->updateOrder($order, ['meta' => [$key => '0']]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Renewal outcome checkpoints');
    expect(fn () => $orders->updateOrder($order, ['meta' => []]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Renewal outcome checkpoints');
})->with(['free_domain_renewal_claim_', 'free_domain_renewal_retry_']);

test('free retry approval is consumed even when the managed approval still says zero', function (): void {
    [$em, $order, $item, $orders, , $executor] = freeHoldRegressionContext('zero');
    $key = 'free_domain_renewal_retry_' . $item->getId();
    $approval = (new OrderMeta())->setOrder($order)->setName($key)->setValue('0');
    $em->persist($approval);
    $em->flush();
    $executor->reviewDomainRenewal($item->getId(), 'retry_zero');
    // Keep this worker's managed approval at zero while staff grants a retry.
    $em->getConnection()->update('client_order_meta', ['value' => '0'], ['id' => $approval->getId()]);
    $em->refresh($approval);
    $em->getConnection()->update('client_order_meta', ['value' => '1'], ['id' => $approval->getId()]);
    $orders->shouldReceive('_callOnService')->once()->andReturnUsing(function () use ($em, $key): void {
        expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [$key]))->toBe('0');

        throw new Error('crash during registrar call');
    });
    expect(fn () => $executor->executeTask($item))->toThrow(Error::class);
    expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [$key]))->toBe('0');
});

test('free eligibility and provider execution share the direct renewal lock', function (): void {
    [$em, $order, $item, $orders, , $executor] = freeHoldRegressionContext('free');
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE name = ?', ['free_domain_renewal_claim_' . $item->getId()]);
    $item->setStatus('pending_setup');
    $em->flush();
    $products = Mockery::mock(Box\Mod\Product\Service::class);
    $products->shouldReceive('findProductById')->andReturn((new Box\Mod\Product\Entity\Product())->setType('domain'));
    $products->shouldReceive('freeDomainRenewalUntil')->once()->andReturnUsing(function () use ($orders, $order): int {
        expect(fn () => $orders->renewOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class);

        return time() + 366 * 86400;
    });
    $di = $executor->getDi();
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $module === 'Product' ? $products : $services($module, $sub));
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $executor->executeTask($item);
    expect($item->getStatus())->toBe('executed');
});
