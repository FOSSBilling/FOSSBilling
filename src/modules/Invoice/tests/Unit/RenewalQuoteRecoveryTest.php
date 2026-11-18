<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Product\Entity\Product;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('domain quotes retain provenance and reuse an already-funded pending renewal', function (float $quote): void {
    [, $em, $client] = receiptTestContext();
    $order = (new Order())->setClientId($client->getId())->setProductId(5)->setCurrency('USD')->setStatus(Order::STATUS_ACTIVE)->setPrice(20)->setQuantity(1);
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($order);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('order')->setTask('renew')->setRelId((string) $order->getId())->setPrice(0)->setQuantity(1)->setCharged(true)->setStatus(InvoiceItem::STATUS_PENDING_SETUP));
    $em->flush();
    $service = gatewayDepositTestService($em);
    $products = Mockery::mock(Box\Mod\Product\Service::class);
    $products->shouldReceive('findProductById')->with(5)->andReturn((new Product())->setType('domain'));
    $products->shouldReceive('getProductRenewalLineConfig')->andReturn(['price' => $quote, 'quantity' => 1]);
    $products->shouldReceive('hasFreeDomainRenewal')->with($order->getId())->andReturn(false);
    $products->shouldReceive('isFreeDomainRenewalOffered')->with($order->getId())->andReturn(false);
    $products->shouldReceive('getProductModuleService')->andReturn(new stdClass());
    $di = $service->getDi();
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($module) {
        'Product' => $products,
        'Currency' => $services('currency', $sub),
        default => $services($module, $sub),
    });
    expect($service->resolveRenewalLine($order))->toMatchArray(['price' => $quote, 'line' => ['price' => $quote, 'quantity' => 1, 'free_domain_renewal' => false, 'free_domain_waiting_hosting' => false]])
        ->and($service->generateForOrder($order))->toBe($invoice);
    expect(fn () => $service->generateMergedInvoiceForOrders([$order]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already waiting');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
})->with([0.0, 25.0]);

test('renewal batches reload single and bucketed orders after credit closes the manager', function (bool $merge): void {
    [, $em, $client] = receiptTestContext();
    $orders = [];
    foreach (['EUR', 'EUR', 'USD', 'USD', 'USD'] as $currency) {
        $order = (new Order())->setClientId($client->getId())->setCurrency($currency)->setPrice(20)->setQuantity(1)->setStatus(Order::STATUS_ACTIVE);
        $em->persist($order);
        $orders[] = $order;
    }
    $orders[2]->setUnpaidInvoiceId(999);
    $em->flush();
    $ids = array_map(fn (Order $order): int => (int) $order->getId(), $orders);
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('setInvoiceDefaults')->byDefault()->zeroOrMoreTimes();
    $number = 1;
    $service->shouldReceive('getNextInvoiceNumber')->andReturnUsing(function () use (&$number): int { return $number++; });
    $service->shouldReceive('resolveRenewalLine')->andReturn(['price' => 20, 'line' => ['price' => 20, 'quantity' => 1]]);
    $service->shouldReceive('shouldMergeRenewalsForOrder')->andReturnUsing(fn (Order $order): bool => $merge && in_array($order->getId(), [$ids[0], $ids[3], $ids[4]], true));
    $resets = 0;
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->andReturnUsing(function ($connection) use ($em, &$resets): Doctrine\ORM\EntityManager {
        ++$resets;

        return new Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
    });
    $di = $service->getDi();
    $base = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(function (string $module, string $sub = '') use ($di, $base): object {
        if ($module === 'Order') {
            $orders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
            $orders->setDi($di);
            $orders->shouldReceive('getSoonExpiringActiveOrders')->andReturnUsing(fn (): array => $di['em']->getConnection()->fetchAllAssociative("SELECT co.id FROM client_order co LEFT JOIN invoice i ON i.id = co.unpaid_invoice_id AND i.status = 'unpaid' WHERE i.id IS NULL ORDER BY co.id"));

            return $orders;
        }
        if ($sub === 'Subscription') {
            $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
            $subscriptions->shouldReceive('hasActiveSubscriptionForOrder')->andReturn(false);

            return $subscriptions;
        }
        if ($module === 'Product') {
            $products = Mockery::mock(Box\Mod\Product\Service::class);
            $products->shouldReceive('getRenewalPromoAdjustments')->andReturn([]);

            return $products;
        }
        if ($module === 'system') {
            $settings = Mockery::mock(Box\Mod\System\Service::class);
            $settings->shouldReceive('getParamValue')->andReturn(null);
            $settings->shouldReceive('getCompany')->andReturn(['name' => 'Issuer']);

            return $settings;
        }
        if (strtolower($module) === 'client' && $sub === '') {
            $clients = Mockery::mock(Box\Mod\Client\Service::class);
            $clients->shouldReceive('isClientTaxable')->andReturn(false);
            $clients->shouldReceive('toApiArray')->andReturn(['first_name' => 'Buyer']);

            return $clients;
        }

        return $base($module, $sub);
    });
    foreach ([1, 2] as $attempt) {
        expect($service->generateInvoicesForExpiringOrders())->toBeTrue()
            ->and($resets)->toBe(2)
            ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe($merge ? 4 : 5)
            ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item'))->toBe(5);
        foreach ($ids as $id) {
            $linked = (int) $em->getConnection()->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$id]);
            expect($linked)->toBeGreaterThan(0)
                ->and((int) $em->getConnection()->fetchOne("SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ? AND rel_id = ? AND task = 'renew'", [$linked, (string) $id]))->toBe(1);
        }
    }
})->with([false, true]);

function renewalReviewService(Doctrine\ORM\EntityManager $em): Box\Mod\Invoice\Service
{
    $service = gatewayDepositTestService($em);
    $service->shouldReceive('setInvoiceDefaults')->byDefault()->zeroOrMoreTimes();
    $service->shouldReceive('resolveRenewalLine')->byDefault()->andReturn(['price' => 0.0, 'line' => ['price' => 0.0, 'quantity' => 1, 'free_domain_renewal' => true]]);
    $di = $service->getDi();
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(function (string $module, string $sub = '') use ($di, $services): object {
        if ($module === 'Order') {
            $orders = new Box\Mod\Order\Service();
            $orders->setDi($di);

            return $orders;
        }
        if ($module === 'Product') {
            $products = Mockery::mock(Box\Mod\Product\Service::class);
            $products->shouldReceive('releaseReservedPromoRedemptionsForOrder')->zeroOrMoreTimes();

            return $products;
        }
        if (strtolower($module) === 'client') {
            $clients = Mockery::mock(Box\Mod\Client\Service::class);
            $clients->shouldReceive('isClientTaxable')->andReturn(false);
            $clients->shouldReceive('toApiArray')->andReturn([]);

            return $clients;
        }

        if ($module === 'system') {
            $settings = Mockery::mock(Box\Mod\System\Service::class);
            $settings->shouldReceive('getCompany')->andReturn([]);
            $settings->shouldReceive('getParamValue')->andReturn(null);

            return $settings;
        }

        return $services($module, $sub);
    });
    $service->shouldReceive('getNextInvoiceNumber')->byDefault()->andReturn(1);

    return $service;
}

function renewalReviewOrder(Doctrine\ORM\EntityManager $em, int $clientId): Order
{
    $order = (new Order())->setClientId($clientId)->setCurrency('USD')->setStatus(Order::STATUS_ACTIVE)->setPrice(12)->setQuantity(1)->setTitle('example.test');
    $em->persist($order);
    $em->flush();

    return $order;
}

test('renewal generation and draft issuance respect a newly pending cancellation', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $request = (new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending')->setType('end_of_period');
    $em->persist($request);
    $em->flush();
    expect(fn () => $service->generateForOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'cancellation request')
        ->and(fn () => $service->issueInvoice($invoice, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Cancellation prevents');
    expect($invoice->isIssued())->toBeFalse()->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
});

test('merged generation omits the held order while keeping its eligible bucket mate', function (): void {
    [, $em, $client] = receiptTestContext();
    $held = renewalReviewOrder($em, $client->getId());
    $eligible = renewalReviewOrder($em, $client->getId());
    $em->persist((new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($held)->setClient($client)->setStatus('pending'));
    $em->flush();
    $invoice = renewalReviewService($em)->generateMergedInvoiceForOrders([$held, $eligible], false);
    expect($em->getConnection()->fetchFirstColumn('SELECT rel_id FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))->toBe([(string) $eligible->getId()])
        ->and($held->getUnpaidInvoiceId())->toBeNull()->and($eligible->getUnpaidInvoiceId())->toBe($invoice->getId());
});

test('renewal creation owns the request mutex until the quote and entitlement are committed', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $service->shouldReceive('setInvoiceDefaults')->once()->andReturnUsing(function () use ($order): void {
        $other = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $name = 'fb:cancel-request-order:' . $order->getId();
        $acquired = FOSSBilling\Core\Doctrine\NamedLock::acquire($other, $name, 0);
        if ($acquired) {
            FOSSBilling\Core\Doctrine\NamedLock::release($other, $name);
        }
        expect($acquired)->toBeFalse();
    });
    $invoice = $service->generateForOrder($order, null, false);
    expect($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$order->getId(), 'free_domain_renewal_invoice_' . $invoice->getId()]))->toBe('1');
});

test('an interrupted single renewal quote rolls back its line and order link before retry', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $connection = $em->getConnection();
    $connection->executeStatement("CREATE TRIGGER quote_crash BEFORE INSERT ON client_order_meta WHEN NEW.name LIKE 'free_domain_renewal_invoice_%' BEGIN SELECT RAISE(ABORT, 'quote interrupted'); END");
    expect(fn () => renewalReviewService($em)->generateForOrder($order, null, false))->toThrow(Exception::class, 'quote interrupted');
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(0)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice_item'))->toBe(0)
        ->and($connection->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBeNull();
    $connection->executeStatement('DROP TRIGGER quote_crash');
    $fresh = new Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
    $current = $fresh->find(Order::class, $order->getId());
    $invoice = renewalReviewService($fresh)->generateForOrder($current, null, false);
    expect((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice_item'))->toBe(1)
        ->and($connection->fetchOne('SELECT value FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$order->getId(), 'free_domain_renewal_invoice_' . $invoice->getId()]))->toBe('1');
});

test('reissue copies quote entitlement to the replacement without copying execution or retry claims', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $original = $service->generateForOrder($order, null, false);
    $original->setIssued(true);
    $em->flush();
    $orders = $service->getDi()['mod_service']('Order');
    $orders->updateOrderMeta($order, ['free_domain_renewal_claim_99' => '1', 'free_domain_renewal_retry_99' => '1']);
    $service->shouldReceive('getNextInvoiceNumber')->once()->andReturn(1);
    $service->shouldReceive('addNote')->zeroOrMoreTimes();
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('resendUpdatedInvoice')->once();
    $di = $service->getDi();
    $base = $di['mod_service'];
    $di['mod_service'] = $di->protect(function (string $module, string $sub = '') use ($base): object {
        if ($module === 'system') {
            $system = Mockery::mock(Box\Mod\System\Service::class);
            $system->shouldReceive('getParamValue')->andReturn('90');

            return $system;
        }
        if ($module === 'Product') {
            $products = Mockery::mock(Box\Mod\Product\Service::class);
            $products->shouldReceive('transferReservedPromoRedemptionsForOrders')->once();

            return $products;
        }

        return $base($module, $sub);
    });
    $replacementId = $service->reissueInvoice($original);
    $meta = $orders->getOrderMetaRepository()->getPairsForOrder($order->getId());
    expect($meta['free_domain_renewal_invoice_' . $replacementId])->toBe('1')
        ->and($order->getUnpaidInvoiceId())->toBe($replacementId)
        ->and(array_keys($meta))->toHaveCount(4);
});

test('initial invoicing joins order creation and records activation rather than renewal', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setStatus(Order::STATUS_PENDING_SETUP)->setQuantity(2);
    $em->flush();
    $service = renewalReviewService($em);
    // Initial prices belong to the newly created order, including its quantity.
    $service->shouldReceive('resolveRenewalLine')->with($order)->once()->passthru();
    $invoice = $em->wrapInTransaction(fn () => $service->generateInitialInvoiceForOrder($order));
    expect($invoice->isIssued())->toBeFalse()
        ->and($order->getUnpaidInvoiceId())->toBe($invoice->getId())
        ->and($em->getConnection()->fetchAssociative('SELECT task, price, quantity FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))
        ->toMatchArray(['task' => 'activate', 'price' => 12, 'quantity' => 2]);
    expect(fn () => $service->generateInitialInvoiceForOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class);
    expect($service->issueInvoice($invoice, ['defer_issue_event' => true]))->toBeTrue();
});

test('late cancellation removes only the held order from a merged unfunded draft', function (): void {
    [, $em, $client] = receiptTestContext();
    $held = renewalReviewOrder($em, $client->getId());
    $eligible = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $service->shouldReceive('resolveRenewalLine')->andReturn(['price' => 12.0, 'line' => ['quantity' => 1]]);
    $invoice = $service->generateMergedInvoiceForOrders([$held, $eligible], false);
    foreach ([$held, $eligible] as $order) {
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('custom')->setTask('void')->setUnit('discount')->setRelId((string) $order->getId())->setPrice(-2)->setQuantity(1));
    }
    $em->persist((new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($held)->setClient($client)->setStatus('pending'));
    $em->flush();
    expect($service->issueInvoice($invoice, ['defer_issue_event' => true]))->toBeTrue()
        ->and($em->isOpen())->toBeTrue()->and($invoice->isIssued())->toBeTrue()
        ->and($held->getUnpaidInvoiceId())->toBeNull()
        ->and($eligible->getUnpaidInvoiceId())->toBe($invoice->getId());
    expect($em->getConnection()->fetchFirstColumn('SELECT rel_id FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))
        ->toBe([(string) $eligible->getId(), (string) $eligible->getId()]);
    expect((float) $em->getConnection()->fetchOne('SELECT SUM(price * quantity) FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))->toBe(10.0);
});

test('an all-held draft releases its order for a fresh quote after withdrawal without closing the manager', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $request = (new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending');
    $em->persist($request);
    $em->flush();
    expect(fn () => $service->issueInvoice($invoice, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Cancellation prevents');
    expect($em->isOpen())->toBeTrue()->and($invoice->getStatus())->toBe(Invoice::STATUS_CANCELED)
        ->and($order->getUnpaidInvoiceId())->toBeNull()
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['free_domain_renewal_invoice_' . $invoice->getId()]))->toBeFalse();
    $request->setStatus('withdrawn');
    $em->flush();
    $replacement = $service->generateForOrder($order, null, false);
    expect($replacement->getId())->not->toBe($invoice->getId())
        ->and($order->getUnpaidInvoiceId())->toBe($replacement->getId());
});

test('late scheduled cancellation and recovery claims block stale quote generation and issuance', function (string $name, string $value): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $em->persist((new OrderMeta())->setOrder($order)->setName($name)->setValue($value));
    $em->flush();
    expect(fn () => $service->generateForOrder($order))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'resolve the order hold')
        ->and(fn () => $service->issueInvoice($invoice, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Cancellation prevents');
    expect($em->isOpen())->toBeTrue()->and($invoice->isIssued())->toBeFalse()->and($order->getUnpaidInvoiceId())->toBeNull();
})->with([
    'accepted schedule' => ['cancel_at_period_end', '1'],
    'uncertain cancellation' => ['scheduled_cancel_claim', '1'],
    'completed cancellation awaiting recovery' => ['scheduled_cancel_claim', '2'],
]);

test('incoming payment keeps a late-held draft intact for staff review', function (string $status): void {
    [, $em, $client, $gateway, $receipt] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $receipt->setInvoice($invoice)->setStatus($status);
    $em->persist((new OrderMeta())->setOrder($order)->setName('cancel_at_period_end')->setValue('1'));
    $em->flush();
    expect(fn () => $service->issueInvoice($invoice, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'received payment');
    expect($em->isOpen())->toBeTrue()->and($invoice->isIssued())->toBeFalse()
        ->and($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($order->getUnpaidInvoiceId())->toBe($invoice->getId())
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))->toBe(1);
})->with(['processing', 'processed']);

test('failed late-hold pruning rolls back lines links and provenance before retry', function (): void {
    [, $em, $client] = receiptTestContext();
    $held = renewalReviewOrder($em, $client->getId());
    $eligible = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateMergedInvoiceForOrders([$held, $eligible], false);
    $em->persist((new OrderMeta())->setOrder($held)->setName('cancel_at_period_end')->setValue('1'));
    $em->flush();
    $id = $invoice->getId();
    $heldId = $held->getId();
    $em->getConnection()->executeStatement("CREATE TRIGGER fail_issue BEFORE UPDATE OF issued ON invoice WHEN NEW.issued = 1 BEGIN SELECT RAISE(ABORT, 'interrupted issuance'); END");
    expect(fn () => $service->issueInvoice($invoice, ['defer_issue_event' => true]))->toThrow(Exception::class, 'interrupted issuance');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$id]))->toBe(2)
        ->and((int) $em->getConnection()->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$heldId]))->toBe($id)
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE client_order_id = ? AND name = ?', [$heldId, 'free_domain_renewal_invoice_' . $id]))->toBe('1');
    $connection = $em->getConnection();
    $connection->executeStatement('DROP TRIGGER fail_issue');
    $fresh = new Doctrine\ORM\EntityManager($connection, $em->getConfiguration());
    $service = renewalReviewService($fresh);
    $invoice = $fresh->find(Invoice::class, $id);
    foreach ([1, 2] as $attempt) {
        expect($service->issueInvoice($invoice, ['defer_issue_event' => true]))->toBeTrue()
            ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$id]))->toBe(1)
            ->and($connection->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$heldId]))->toBeNull();
    }
});

test('pruning a held renewal recalculates the gateway fee on the surviving quote', function (): void {
    [, $em, $client, $gateway] = receiptTestContext();
    $held = renewalReviewOrder($em, $client->getId());
    $eligible = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $service->shouldReceive('resolveRenewalLine')->andReturn(['price' => 12.0, 'line' => ['quantity' => 1]]);
    $invoice = $service->generateMergedInvoiceForOrders([$held, $eligible], false);
    $gateway->setConfig(['fee' => ['percent' => 10]]);
    $invoice->setGateway($gateway);
    $em->flush();
    $service->getDi()['mod_service']('Invoice', 'GatewayFees')->apply($invoice, $gateway);
    expect((float) $em->getConnection()->fetchOne('SELECT price FROM invoice_item WHERE invoice_id = ? AND task = ?', [$invoice->getId(), Box\Mod\Invoice\ServiceGatewayFees::TASK]))->toBe(2.4);
    $em->persist((new OrderMeta())->setOrder($held)->setName('cancel_at_period_end')->setValue('1'));
    $em->flush();
    expect($service->issueInvoice($invoice, ['defer_issue_event' => true]))->toBeTrue();
    expect((float) $em->getConnection()->fetchOne('SELECT price FROM invoice_item WHERE invoice_id = ? AND task = ?', [$invoice->getId(), Box\Mod\Invoice\ServiceGatewayFees::TASK]))->toBe(1.2);
});

test('draft membership changed after acquiring renewal locks requires a fresh issuance attempt', function (): void {
    [, $em, $client] = receiptTestContext();
    $first = renewalReviewOrder($em, $client->getId());
    $second = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($first, null, false);
    $dispatcher = Mockery::mock(Psr\EventDispatcher\EventDispatcherInterface::class);
    $dispatcher->shouldReceive('dispatch')->once()->andReturnUsing(function (object $event) use ($em, $invoice, $second): object {
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('order')->setTask('renew')->setRelId((string) $second->getId())->setPrice(12)->setQuantity(1));
        $em->flush();

        return $event;
    });
    $service->getDi()['event_dispatcher'] = $dispatcher;
    expect(fn () => $service->issueInvoice($invoice, ['defer_issue_event' => true]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'changed');
    expect($em->isOpen())->toBeTrue()->and($invoice->isIssued())->toBeFalse();
});
