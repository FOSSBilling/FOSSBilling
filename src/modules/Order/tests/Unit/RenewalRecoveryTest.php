<?php

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Service;
use Doctrine\ORM\EntityManager;

use function Tests\Helpers\container;

require_once dirname(__DIR__) . '/Fixtures/RenewalContext.php';

test('cron recovers an ordinary paid renewal with its own order transaction exactly once', function (): void {
    [$em, $order, $item, $orderService, $cron] = cronRenewalRegressionContext();
    $orderService->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW)->andReturnUsing(function () use ($em): void {
        expect($em->getConnection()->getTransactionNestingLevel())->toBe(1);
    });
    expect($cron->doBatchPaidInvoiceActivation())->toBeTrue()
        ->and($item->getStatus())->toBe('executed')
        ->and($item->getAttempts())->toBe(0);
    expect($cron->doBatchPaidInvoiceActivation())->toBeTrue();
});

test('cron skips a renewal owned by another worker and recovers it once the item lock is released', function (): void {
    [$em, $order, $item, $orderService, $cron] = cronRenewalRegressionContext();
    $orderService->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $connection = $em->getConnection();
    $name = 'fb:invoice-item-renewal:' . $item->getId();
    expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($connection, $name, 0))->toBeTrue();

    try {
        $cron->doBatchPaidInvoiceActivation();
        expect($item->getStatus())->toBe('pending_setup')->and($item->getAttempts())->toBe(0);
    } finally {
        FOSSBilling\Core\Doctrine\NamedLock::release($connection, $name);
    }
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed');
});

test('staff can approve one retry after checking an uncertain provider failure', function (): void {
    [$em, $order, $item, $orderService, $cron] = cronRenewalRegressionContext();
    $attempt = 0;
    $orderService->shouldReceive('_callOnService')->twice()->with($order, Order::ACTION_RENEW)->andReturnUsing(function () use (&$attempt): void {
        if (++$attempt === 1) {
            throw new RuntimeException('temporary provider failure');
        }
    });
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('failed')->and($item->getAttempts())->toBe(1)
        ->and($order->getStatus())->toBe(Order::STATUS_FAILED_RENEW);
    $executor = ($cron->getDi()['mod_service'])('Invoice', 'InvoiceItem');
    expect(fn () => $executor->requeueItem((int) $item->getId()))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Review the provider');
    $executor->reviewPaidRenewal((int) $item->getId(), 'retry');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed')->and($order->getStatus())->toBe(Order::STATUS_ACTIVE);
});

test('uncertain outcomes block every renewal entry point for the order', function (string $entryPoint, string $status): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $order->setStatus($status);
    $key = 'paid_renewal_outcome_' . $item->getId();
    $em->persist((new OrderMeta())->setOrder($order)->setName($key)->setValue('pending'));
    $em->flush();
    $orders->shouldNotReceive('_callOnService');
    $attempt = match ($entryPoint) {
        'service' => fn () => $orders->renewFromOrder($order),
        'order' => fn () => $orders->renewOrder($order, []),
        'different item' => fn () => $orders->renewInvoiceItem($order, (int) $item->getId() + 1),
        'admin' => function () use ($orders, $order): void {
            $api = Mockery::mock(Box\Mod\Order\Api\Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
            $api->shouldReceive('checkPermissions')->once()->with('order', 'manage');
            $api->shouldReceive('_getOrder')->once()->andReturn($order);
            $api->shouldReceive('getService')->once()->andReturn($orders);
            $api->renew(['id' => $order->getId()]);
        },
    };
    expect($attempt)->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved renewal outcome');
    expect($order->getStatus())->toBe($status)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[$key])->toBe('pending')
        ->and($em->getConnection()->isTransactionActive())->toBeFalse();
})->with(['service', 'order', 'admin', 'different item'])->with([Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED]);

test('a reviewed retry is reserved for its original item until that attempt completes', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $key = 'paid_renewal_outcome_' . $item->getId();
    $item->setStatus('failed');
    $em->persist((new OrderMeta())->setOrder($order)->setName($key)->setValue('pending'));
    $em->flush();
    $executor = ($cron->getDi()['mod_service'])('Invoice', 'InvoiceItem');
    $executor->reviewPaidRenewal((int) $item->getId(), 'retry');
    $orders->shouldReceive('_callOnService')->twice()->with($order, Order::ACTION_RENEW);
    expect(fn () => $orders->renewOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved renewal outcome');
    expect(fn () => $orders->renewInvoiceItem($order, (int) $item->getId() + 1))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved renewal outcome');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed')
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[$key])->toBe('completed');
    // A genuinely new manual renewal remains available after resolution.
    expect($orders->renewOrder($order))->toBeTrue();
});

test('completed item recovery can finish without provider work while another outcome is pending', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    foreach ([$item->getId() => 'completed', $item->getId() + 1 => 'pending'] as $id => $value) {
        $em->persist((new OrderMeta())->setOrder($order)->setName('paid_renewal_outcome_' . $id)->setValue($value));
    }
    $em->flush();
    $orders->shouldNotReceive('_callOnService');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed');
    expect(fn () => $orders->renewOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved renewal outcome');
});

test('order renewal checks current outcomes rather than cached metadata', function (): void {
    [$em, $order, $item, $orders] = cronRenewalRegressionContext();
    $meta = (new OrderMeta())->setOrder($order)->setName('paid_renewal_outcome_' . $item->getId())->setValue('completed');
    $em->persist($meta);
    $em->flush();
    $em->getConnection()->executeStatement('UPDATE client_order_meta SET value = ? WHERE id = ?', ['pending', $meta->getId()]);
    $orders->shouldNotReceive('_callOnService');
    expect(fn () => $orders->renewFromOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved renewal outcome');
});

test('a listener failure after renewal retries local delivery without renewing again', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $order->setPeriod('1M');
    $em->flush();
    $cron->getDi()['period'] = $cron->getDi()->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $attempts = 0;
    $cron->getDi()['event_dispatcher']->addListener(Box\Mod\Order\Event\AfterAdminOrderRenewEvent::class, function () use (&$attempts): void {
        if (++$attempts === 1) {
            throw new RuntimeException('delivery failed after renewal');
        }
    });
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('pending_setup')
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())['paid_renewal_outcome_' . $item->getId()])->toBe('completed');
    $renewedExpiry = $order->getExpiresAt()->getTimestamp();
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('executed')->and($attempts)->toBe(2)
        ->and($order->getExpiresAt()->getTimestamp())->toBe($renewedExpiry);
});

test('ordinary order edits cannot erase or overwrite a durable renewal outcome', function (bool $clearAll): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $key = 'paid_renewal_outcome_' . $item->getId();
    $em->persist((new OrderMeta())->setOrder($order)->setName($key)->setValue('pending'));
    $em->flush();
    expect(fn () => $orders->updateOrder($order, ['meta' => $clearAll ? [] : [$key => 'retry']]))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Renewal outcome checkpoints');
    expect($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[$key])->toBe('pending');
})->with([true, false]);

test('a crash before item acknowledgement recovers the completed renewal without repeating it', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $connection = $em->getConnection();
    $connection->executeStatement("CREATE TRIGGER fail_ack BEFORE UPDATE OF status ON invoice_item WHEN NEW.status = 'executed' BEGIN SELECT RAISE(ABORT, 'crash before acknowledgement'); END");
    $executor = ($cron->getDi()['mod_service'])('Invoice', 'InvoiceItem');
    expect(fn () => $executor->executeTask($item))->toThrow(Doctrine\DBAL\Exception\DriverException::class, 'crash before acknowledgement');
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('pending_setup')
        ->and($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['paid_renewal_outcome_' . $item->getId()]))->toBe('completed');
    $connection->executeStatement('DROP TRIGGER fail_ack');
    $newEm = new EntityManager($connection, $em->getConfiguration());
    [$newOrders, $newCron] = cronRenewalRegressionServices($newEm);
    $newOrders->shouldNotReceive('_callOnService');
    $newCron->doBatchPaidInvoiceActivation();
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('executed');
});

test('an interrupted outcome commit holds the provider attempt for staff review', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $connection = $em->getConnection();
    $connection->executeStatement("CREATE TRIGGER fail_outcome BEFORE UPDATE ON client_order_meta WHEN NEW.value = 'completed' BEGIN SELECT RAISE(ABORT, 'crash after provider'); END");
    $executor = ($cron->getDi()['mod_service'])('Invoice', 'InvoiceItem');
    expect(fn () => $executor->executeTask($item))->toThrow(Exception::class);
    expect($connection->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['paid_renewal_outcome_' . $item->getId()]))->toBe('pending');
    $connection->executeStatement('DROP TRIGGER fail_outcome');
    $newEm = new EntityManager($connection, $em->getConfiguration());
    [$newOrders, $newCron] = cronRenewalRegressionServices($newEm);
    $newOrders->shouldNotReceive('_callOnService');
    $newCron->doBatchPaidInvoiceActivation();
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('failed');
    (new Doctrine\ORM\Tools\SchemaTool($newEm))->createSchema([$newEm->getClassMetadata(Box\Mod\Product\Entity\Product::class)]);
    $api = Mockery::mock(Box\Mod\Invoice\Api\Admin::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $api->shouldReceive('checkPermissions')->once()->with('invoice', 'view');
    $api->setDi($newCron->getDi());
    expect($api->get_failed_items([])[0]['renewal_outcome'])->toBe('pending');
    // Staff verifies the provider and reconciles expiry before acknowledging it.
    ($newCron->getDi()['mod_service'])('Invoice', 'InvoiceItem')->reviewPaidRenewal((int) $item->getId(), 'completed');
    expect($connection->fetchOne('SELECT status FROM invoice_item WHERE id = ?', [$item->getId()]))->toBe('executed');
});

test('cron renewal recovery preserves scheduled cancellation holds', function (string $name, string $value, string $status): void {
    [$em, $order, $item, $orderService, $cron] = cronRenewalRegressionContext();
    $order->setStatus($status);
    $em->persist((new OrderMeta())->setOrder($order)->setName($name)->setValue($value));
    $em->flush();
    $orderService->shouldNotReceive('_callOnService');
    $cron->doBatchPaidInvoiceActivation();
    expect($item->getStatus())->toBe('pending_setup')->and($item->getAttempts())->toBe(1)
        ->and($order->getStatus())->toBe($status)
        ->and($em->getRepository(OrderMeta::class)->getPairsForOrder($order->getId())[$name])->toBe($value);
})->with([
    'scheduled' => [Service::META_CANCEL_AT_PERIOD_END, '1'],
    'provider review' => [Service::META_SCHEDULED_CANCEL_CLAIM, '1'],
    'cleanup pending' => [Service::META_SCHEDULED_CANCEL_CLAIM, '2'],
])->with([Order::STATUS_ACTIVE, Order::STATUS_SUSPENDED]);

test('a stale callback cannot repeat a renewal already completed by cron', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $em2 = new EntityManager($em->getConnection(), $em->getConfiguration());
    $stale = $em2->find(Box\Mod\Invoice\Entity\InvoiceItem::class, $item->getId());
    $orders2 = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $orders2->shouldNotReceive('_callOnService');
    $di = container();
    $di['em'] = $em2;
    $di['mod_service'] = $di->protect(fn (): object => $orders2);
    $orders2->setDi($di);
    $executor = new Box\Mod\Invoice\ServiceInvoiceItem();
    $executor->setDi($di);

    $cron->doBatchPaidInvoiceActivation();
    expect($stale->getStatus())->toBe('pending_setup');
    expect($executor->executeTask($stale))->toBeTrue()
        ->and($stale->getStatus())->toBe('executed')
        ->and($stale->getAttempts())->toBe(0);
});

test('direct renewal execution respects a busy item lock without consuming attempts', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW);
    $executor = ($cron->getDi()['mod_service'])('Invoice', 'InvoiceItem');
    $name = 'fb:invoice-item-renewal:' . $item->getId();
    expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($em->getConnection(), $name, 0))->toBeTrue();

    try {
        expect($executor->executeTask($item))->toBeFalse()
            ->and($item->getAttempts())->toBe(0)->and($item->getStatus())->toBe('pending_setup');
    } finally {
        FOSSBilling\Core\Doctrine\NamedLock::release($em->getConnection(), $name);
    }
    $executor->executeTask($item);
    expect($item->getStatus())->toBe('executed');
});

test('suspended renewal keeps the cancellation lock across unsuspension and renewal', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $order->setStatus(Order::STATUS_SUSPENDED);
    $em->flush();
    $assertLocked = function () use ($em, $order): void {
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($em->getConnection(), 'fb:scheduled-cancel:' . $order->getId(), 0))->toBeFalse();
    };
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_UNSUSPEND)->ordered()->andReturnUsing($assertLocked);
    $orders->shouldReceive('_callOnService')->once()->with($order, Order::ACTION_RENEW)->ordered()->andReturnUsing($assertLocked);
    $cron->doBatchPaidInvoiceActivation();
    expect($order->getStatus())->toBe(Order::STATUS_ACTIVE)->and($item->getStatus())->toBe('executed');
});

test('renewal pending outcome replaces a stale cached pending value before provider I/O', function (): void {
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $key = 'paid_renewal_outcome_' . $item->getId();
    $meta = (new OrderMeta())->setOrder($order)->setName($key)->setValue('pending');
    $em->persist($meta);
    $em->flush();
    $em->getConnection()->update('client_order_meta', ['value' => 'retry'], ['id' => $meta->getId()]);
    $orders->shouldReceive('_callOnService')->once()->andReturnUsing(function () use ($em, $key): void {
        expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [$key]))->toBe('pending');

        throw new Error('crash at provider');
    });
    expect(fn () => $orders->renewInvoiceItem($order, $item->getId()))->toThrow(Error::class);
    expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', [$key]))->toBe('pending');
    expect(fn () => $orders->renewInvoiceItem($order, $item->getId()))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'unresolved');
});
