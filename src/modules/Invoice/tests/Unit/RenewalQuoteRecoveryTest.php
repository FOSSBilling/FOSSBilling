<?php

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Product\Entity\Product;
use Doctrine\ORM\Tools\SchemaTool;

require_once dirname(__DIR__) . '/Fixtures/GatewayReceiptContext.php';

test('domain quotes retain provenance and reuse an already-funded pending renewal', function (float $quote): void {
    [, $em, $client] = receiptTestContext();
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(OrderMeta::class)]);
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
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(OrderMeta::class)]);
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
    $service->shouldReceive('setInvoiceDefaults')->zeroOrMoreTimes();
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
