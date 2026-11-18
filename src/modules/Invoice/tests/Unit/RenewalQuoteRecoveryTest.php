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
        $order = (new Order())->setClientId($client->getId())->setCurrency($currency)->setPrice(20)->setQuantity(1)->setStatus(Order::STATUS_ACTIVE)->setInvoiceOption('issue-invoice')->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M');
        $em->persist($order);
        $orders[] = $order;
    }
    $orders[2]->setUnpaidInvoiceId(999);
    $em->flush();
    $ids = array_map(fn (Order $order): int => (int) $order->getId(), $orders);
    $service = gatewayDepositTestService($em);
    $service->shouldAllowMockingProtectedMethods()->shouldReceive('createEntityManager')->byDefault()->andReturnUsing(fn ($connection) => new Doctrine\ORM\EntityManager($connection, $em->getConfiguration()));
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
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
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
            $subscriptions->shouldReceive('hasUncanceledSubscriptionForOrder')->andReturn(false);

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
            $products->shouldReceive('releaseReservedPromoRedemptionsForOrder', 'commitReservedPromoRedemptionsForInvoice')->zeroOrMoreTimes();
            $products->shouldReceive('getRenewalPromoAdjustments')->byDefault()->andReturn([]);

            return $products;
        }
        if (strtolower($module) === 'client' && $sub === '') {
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

class RenewalQuoteCommitConnection extends Doctrine\DBAL\Connection
{
    public ?Closure $beforeOuterCommit = null;

    public function commit(): void
    {
        if ($this->getTransactionNestingLevel() === 1) {
            $this->beforeOuterCommit?->__invoke();
        }
        parent::commit();
    }
}

test('renewal creation owns the request mutex until the quote and entitlement are committed', function (): void {
    $connection = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true, 'wrapperClass' => RenewalQuoteCommitConnection::class]);
    [, $em, $client] = receiptTestContext($connection);
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $probe = static function (bool $held) use ($order): void {
        $other = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $name = 'fb:cancel-request-order:' . $order->getId();
        $acquired = FOSSBilling\Core\Doctrine\NamedLock::acquire($other, $name, 0);
        if ($acquired) {
            FOSSBilling\Core\Doctrine\NamedLock::release($other, $name);
        }
        expect($acquired)->toBe(!$held);
    };
    $service->shouldReceive('setInvoiceDefaults')->once()->andReturnUsing(function () use ($probe): void {
        $probe(true);
    });
    $listener = new class($probe) {
        public int $writes = 0;

        public function __construct(private readonly Closure $probe)
        {
        }

        public function postPersist(Doctrine\ORM\Event\PostPersistEventArgs $event): void
        {
            $entity = $event->getObject();
            if ($entity instanceof OrderMeta && str_starts_with($entity->getName(), 'free_domain_renewal_invoice_')) {
                ++$this->writes;
                ($this->probe)(true);
            }
        }
    };
    $em->getEventManager()->addEventListener(['postPersist'], $listener);
    $commits = 0;
    $connection->beforeOuterCommit = static function () use ($probe, &$commits): void {
        ++$commits;
        $probe(true);
    };
    $invoice = $service->generateForOrder($order, null, false);
    expect($listener->writes)->toBe(1)->and($commits)->toBe(1);
    $probe(false);
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
    $original->setSubscriptionPaymentDraft();
    $em->flush();
    $replacementId = $service->reissueInvoice($original);
    expect((bool) $em->getConnection()->fetchOne('SELECT subscription_payment_draft FROM invoice WHERE id = ?', [$replacementId]))->toBeTrue();
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

test('a stale renewal worker reloads the committed invoice link before creating any quote', function (): void {
    [, $em, $client] = receiptTestContext();
    $first = renewalReviewOrder($em, $client->getId());
    $second = renewalReviewOrder($em, $client->getId());
    $worker = new Doctrine\ORM\EntityManager($em->getConnection(), $em->getConfiguration());
    $stale = $worker->find(Order::class, $first->getId());
    $quote = renewalReviewService($em)->generateForOrder($first, null, false);
    expect($stale->getUnpaidInvoiceId())->toBeNull();
    $service = renewalReviewService($worker);
    expect($service->generateForOrder($stale, null, false)->getId())->toBe($quote->getId());
    $merged = $service->generateMergedInvoiceForOrders([$stale, $worker->find(Order::class, $second->getId())], false);
    expect($em->getConnection()->fetchFirstColumn('SELECT rel_id FROM invoice_item WHERE invoice_id = ?', [$merged->getId()]))->toBe([(string) $second->getId()])
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE rel_id = ?', [(string) $first->getId()]))->toBe(1);
});

test('generation retries on the same service never return an invoice rolled back after its line flush', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $db = $em->getConnection();
    $db->executeStatement("CREATE TRIGGER abort_quote BEFORE INSERT ON client_order_meta BEGIN SELECT RAISE(ABORT, 'quote interrupted'); END");
    expect(fn () => $service->generateForOrder($order, null, false))->toThrow(Exception::class, 'quote interrupted');
    expect($em->isOpen())->toBeFalse()->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(0);
    $db->executeStatement('DROP TRIGGER abort_quote');
    $quote = $service->generateForOrder($order, null, false);
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice WHERE id = ?', [$quote->getId()]))->toBe(1)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$quote->getId()]))->toBe(1)
        ->and((int) $db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBe($quote->getId());
});

test('an existing renewal draft survives mutex contention and is issued by the next cron pass', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    $em->flush();
    $service = renewalReviewService($em);
    $di = $service->getDi();
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $invoice = $service->generateForOrder($order, null, false);
    $service->shouldReceive('tryPayWithCredits')->once();
    $base = $di['mod_service'];
    $orders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orders->setDi($di);
    $orders->shouldReceive('getSoonExpiringActiveOrders')->andReturn([]);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = '') => $module === 'Order' ? $orders : $base($module, $sub));
    $other = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $name = 'fb:cancel-request-order:' . $order->getId();
    expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($other, $name, 0))->toBeTrue();

    try {
        expect($service->generateInvoicesForExpiringOrders())->toBeTrue()->and($invoice->isIssued())->toBeFalse();
    } finally {
        FOSSBilling\Core\Doctrine\NamedLock::release($other, $name);
    }
    expect($service->generateInvoicesForExpiringOrders())->toBeTrue()->and($invoice->isIssued())->toBeTrue()
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item'))->toBe(1);
});

test('canceled renewal drafts remain unchanged when issuance encounters a late hold', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $invoice->setStatus(Invoice::STATUS_CANCELED);
    $em->persist((new OrderMeta())->setOrder($order)->setName('cancel_at_period_end')->setValue('1'));
    $em->flush();
    $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM invoice_item');
    expect(fn () => $service->issueInvoice($invoice, []))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'Only unpaid draft');
    expect($em->getConnection()->fetchAllAssociative('SELECT * FROM invoice_item'))->toBe($before)
        ->and((int) $em->getConnection()->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBe($invoice->getId())
        ->and($em->getConnection()->fetchOne('SELECT value FROM client_order_meta WHERE name = ?', ['free_domain_renewal_invoice_' . $invoice->getId()]))->toBe('1');
});

test('only empty unnumbered unfunded canceled drafts gain the strict cleanup exception', function (): void {
    [, $em, $client, , $receipt] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_CANCELED)->setIssued(false);
    $em->persist($invoice);
    $em->flush();
    $service = renewalReviewService($em);
    expect($service->isDeletableByAdmin($invoice))->toBeTrue();
    $invoice->setIssued(true);
    expect($service->isDeletableByAdmin($invoice))->toBeFalse();
    $invoice->setIssued(false)->setNr(1);
    expect($service->isDeletableByAdmin($invoice))->toBeFalse();
    $invoice->setNr(null);
    $receipt->setInvoice($invoice);
    $em->flush();
    expect($service->isDeletableByAdmin($invoice))->toBeFalse();
});

test('staff reissue cannot bypass a pending cancellation on an issued renewal', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $invoice->setIssued(true);
    $em->persist((new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending'));
    $em->flush();
    expect(fn () => $service->reissueInvoice($invoice))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'cancellation request');
    expect($em->isOpen())->toBeTrue()->and($invoice->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($invoice->getReplacedByInvoiceId())->toBeNull()
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
});

test('subscription invoice selection retries mutex contention and settles exactly once across a completion crash', function (string $provider, string $type): void {
    [, $em, $client, $gateway, $transaction] = receiptTestContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $order = renewalReviewOrder($em, $client->getId());
    $source = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setIssued(true)->setStatus(Invoice::STATUS_PAID);
    $em->persist($source);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($source)->setType('order')->setTask('activate')->setRelId((string) $order->getId())->setPrice(15)->setQuantity(1));
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setClientId($client->getId())->setPayGateway($gateway)->setSid('sub_contention')->setRelType('invoice')->setRelId($source->getId())->setStatus('active');
    $em->persist($subscription);
    $em->flush();
    $service = renewalReviewService($em);
    $service->shouldReceive('resolveRenewalLine')->andReturn(['price' => 15.0, 'line' => ['quantity' => 1]]);
    $quote = $service->generateForOrder($order, null, false);
    $service->issueInvoice($quote, ['defer_issue_event' => true]);
    $other = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $lock = 'fb:cancel-request-order:' . $order->getId();
    expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($other, $lock, 0))->toBeTrue();

    try {
        expect(fn () => $service->generateRenewalInvoiceForSubscriptionPayment('sub_contention', $client->getId(), $gateway->getId(), $source->getId()))->toThrow(Box\Mod\Invoice\Exception\RenewalLockException::class);
        expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
    } finally {
        FOSSBilling\Core\Doctrine\NamedLock::release($other, $lock);
    }
    $selected = $service->generateRenewalInvoiceForSubscriptionPayment('sub_contention', $client->getId(), $gateway->getId(), $source->getId());
    expect($selected?->getId())->toBe($quote->getId());
    $transaction->setInvoice($selected);
    $em->flush();
    $service = renewalReviewService($em);
    $service->shouldReceive('completeGatewayPayment')->once()->andThrow(new Error('stopped after settlement'));
    expect(fn () => $service->recordGatewayPayment($transaction, $client, $provider, 'Subscription receipt ' . $provider, $type, (string) $transaction->getId()))->toThrow(Error::class, 'stopped after settlement');
    $fresh = new Doctrine\ORM\EntityManager($em->getConnection(), $em->getConfiguration());
    $retry = renewalReviewService($fresh);
    $retryDi = $retry->getDi();
    $retryOrders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $retryOrders->setDi($retryDi);
    $retryOrders->shouldReceive('renewInvoiceItem')->once()->andReturn(true);
    $retryServices = $retryDi['mod_service'];
    $retryDi['mod_service'] = $retryDi->protect(fn (string $name, string $sub = ''): object => $name === 'Order' ? $retryOrders : $retryServices($name, $sub));
    $retry->shouldReceive('completeGatewayPayment')->once()->passthru();
    foreach ([1, 2] as $attempt) {
        $replaying = $fresh->find(Box\Mod\Invoice\Entity\Transaction::class, $transaction->getId());
        $retry->recordGatewayPayment($replaying, $fresh->find(Box\Mod\Client\Entity\Client::class, $client->getId()), $provider, 'Subscription receipt ' . $provider, $type, (string) $transaction->getId());
        $fresh->refresh($replaying);
        expect($replaying->getGatewayCompletionState())->toBe(Box\Mod\Invoice\Entity\Transaction::GATEWAY_COMPLETION_DONE);
    }
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
        ->and((float) $em->getConnection()->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0)
        ->and($em->getConnection()->fetchOne('SELECT status FROM invoice WHERE id = ?', [$quote->getId()]))->toBe('paid')
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(2);
})->with([['PAYPAL-CONTENTION', 'PayPal'], ['in_contention', 'transaction']]);

test('reissue refuses renewal members added after its order mutexes were selected', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $second = renewalReviewOrder($em, $client->getId());
    $service = renewalReviewService($em);
    $invoice = $service->generateForOrder($order, null, false);
    $invoice->setIssued(true);
    $em->flush();
    $service->shouldReceive('getNextInvoiceNumber')->once()->andReturnUsing(function () use ($em, $invoice, $second): int {
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType('order')->setTask('renew')->setRelId((string) $second->getId())->setPrice(10)->setQuantity(1));
        $em->flush();

        return 2;
    });
    expect(fn () => $service->reissueInvoice($invoice))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'retry reissue');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1)
        ->and($em->getConnection()->fetchOne('SELECT status FROM invoice'))->toBe('unpaid');
});

test('background Stripe recovery replays authenticated callbacks after the signature window', function (string $failure): void {
    $renewal = $failure !== 'provider';
    [, $em, $client, $gateway] = receiptTestContext();
    $db = $em->getConnection();
    $table = $db->quoteSingleIdentifier('transaction');
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $order = renewalReviewOrder($em, $client->getId());
    if ($failure === 'cancellation') {
        $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    }
    $original = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setGateway($gateway)->setIssued(true)->setStatus('paid');
    $em->persist($original);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($original)->setType('order')->setTask('activate')->setRelId((string) $order->getId())->setPrice(15)->setQuantity(1));
    $subscription = (new Box\Mod\Invoice\Entity\Subscription())->setClientId($client->getId())->setPayGateway($gateway)->setSid('sub_background')->setRelType('invoice')->setRelId($original->getId())->setStatus('pending_cancellation');
    $em->persist($subscription);
    $em->flush();

    $billing = renewalReviewService($em);
    $billing->shouldReceive('resolveRenewalLine')->andReturn(['price' => 15.0, 'line' => ['quantity' => 1]]);
    $di = $billing->getDi();
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    if ($failure === 'persistent') {
        $billing->shouldReceive('recordGatewayPayment')->once()->andThrow(new Box\Mod\Invoice\Exception\RetryableCallbackException('Interrupted before retaining the receipt'));
        $billing->shouldReceive('recordGatewayPayment')->once()->passthru();
    }
    if ($failure === 'changed target' || $failure === 'malformed draft') {
        $peer = renewalReviewOrder($em, $client->getId());
        $draft = $billing->generateForOrder($order, subscriptionPayment: true);
        if ($failure === 'changed target') {
            $dispatcher = (new ReflectionProperty(FOSSBilling\Core\Events\EventDispatcher::class, 'dispatcher'))->getValue($di['event_dispatcher']);
            $dispatcher->addListener(Box\Mod\Invoice\Event\BeforeAdminInvoiceIssueEvent::class, static function () use ($em, $draft, $peer): void {
                $em->persist((new InvoiceItem())->setInvoice($draft)->setType(InvoiceItem::TYPE_ORDER)
                    ->setTask(InvoiceItem::TASK_ACTIVATE)->setOrderId($peer->getId()));
                $em->flush();
            });
        } else {
            $db->update('invoice_item', ['order_id' => null, 'rel_id' => $order->getId() . ' '], ['invoice_id' => $draft->getId()]);
        }
    }
    $issuanceAttempts = 0;
    if (in_array($failure, ['issuance', 'cancellation', 'persistent'], true)) {
        $dispatcher = (new ReflectionProperty(FOSSBilling\Core\Events\EventDispatcher::class, 'dispatcher'))->getValue($di['event_dispatcher']);
        $dispatcher->addListener(Box\Mod\Invoice\Event\BeforeAdminInvoiceIssueEvent::class, function () use ($db, $failure, &$issuanceAttempts): void {
            ++$issuanceAttempts;
            static $failed = false;
            if (!$failed || $failure === 'persistent') {
                $failed = true;
                expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice WHERE subscription_payment_draft = 1 AND issued = 0'))->toBe(1);

                throw new RuntimeException('Invoice issuer unavailable');
            }
        });
    }
    $metadata = ['invoice_id' => (string) $original->getId(), 'gateway_id' => (string) $gateway->getId(), 'client_id' => (string) $client->getId()];
    $provider = Mockery::mock();
    $current = Stripe\Subscription::constructFrom(['id' => 'sub_background', 'status' => 'active', 'metadata' => $metadata]);
    $providerError = match ($failure) {
        'provider', 'connection' => new Stripe\Exception\ApiConnectionException('Provider unavailable'),
        'rate limit' => Stripe\Exception\RateLimitException::factory('Too many requests', 429),
        'server error' => Stripe\Exception\UnknownApiErrorException::factory('Provider unavailable', 503),
        'invalid request' => Stripe\Exception\InvalidRequestException::factory('Subscription missing', 404, null, ['error' => ['type' => 'invalid_request_error', 'message' => 'Subscription missing']]),
        default => null,
    };
    if ($providerError !== null) {
        $provider->shouldReceive('retrieve')->once()->andThrow($providerError);
    }
    $provider->shouldReceive('retrieve')->andReturn($current);
    $sdk = Mockery::mock(Stripe\StripeClient::class);
    $sdk->subscriptions = $provider;
    $stripeInvoice = ['id' => 'in_background', 'object' => 'invoice', 'subscription' => 'sub_background', 'billing_reason' => 'subscription_cycle', 'amount_paid' => 1500, 'currency' => 'usd', 'metadata' => $metadata];
    if ($failure === 'invoice lookup') {
        $invoiceLookup = Mockery::mock();
        $invoiceLookup->shouldReceive('retrieve')->once()->with('in_background', [])->andThrow(new Stripe\Exception\ApiConnectionException('Provider unavailable'));
        $invoiceLookup->shouldReceive('retrieve')->with('in_background', [])->andReturn(Stripe\Invoice::constructFrom($stripeInvoice));
        $sdk->invoices = $invoiceLookup;
    }
    $adapter = new Payment_Adapter_Stripe(['test_mode' => true, 'test_api_key' => 'sk_test_dummy', 'test_pub_key' => 'pk_test_dummy', 'test_webhook_secret' => 'whsec_background', 'gateway_id' => $gateway->getId()]);
    (new ReflectionProperty($adapter, 'stripe'))->setValue($adapter, $sdk);
    $adapter->setDi($di);
    $subscriptions = new Box\Mod\Invoice\ServiceSubscription();
    $subscriptions->setDi($di);
    $gateways = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->with($gateway)->andReturn($adapter);
    $orders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orders->setDi($di);
    $orders->shouldReceive('renewInvoiceItem')->times($renewal && !in_array($failure, ['invalid request', 'persistent', 'changed target', 'malformed draft'], true) ? 1 : 0)->andReturn(true);
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => match ($sub) {
        'PayGateway' => $gateways,
        'Subscription' => $subscriptions,
        default => $name === 'Order' ? $orders : $services($name, $sub),
    });
    // Lifecycle and renewal handling use internal services, with no API calls.
    $di['api_system'] = (new ReflectionClass(FOSSBilling\Core\Api\Proxy::class))->newInstanceWithoutConstructor();
    $worker = new Box\Mod\Invoice\ServiceTransaction();
    $worker->setDi($di);

    [$type, $object] = match ($failure) {
        'provider' => ['customer.subscription.updated', ['id' => 'sub_background', 'object' => 'subscription', 'metadata' => $metadata]],
        'invoice lookup' => ['invoice_payment.paid', ['id' => 'inpay_background', 'object' => 'invoice_payment', 'invoice' => 'in_background']],
        default => ['invoice.paid', $stripeInvoice],
    };
    $payload = json_encode(['id' => 'evt_background', 'object' => 'event', 'type' => $type, 'data' => ['object' => $object]], JSON_THROW_ON_ERROR);
    $sign = fn (int $time): string => 't=' . $time . ',v1=' . hash_hmac('sha256', $time . '.' . $payload, 'whsec_background');
    $callback = ['gateway_id' => $gateway->getId(), 'skip_validation' => true, 'http_raw_post_data' => $payload, 'server' => ['HTTP_STRIPE_SIGNATURE' => $sign(time())]];
    $id = $worker->create($callback);
    $tx = $em->find(Box\Mod\Invoice\Entity\Transaction::class, $id);
    $other = Doctrine\DBAL\DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $name = 'fb:cancel-request-order:' . $order->getId();
    if ($failure === 'lock') {
        expect(FOSSBilling\Core\Doctrine\NamedLock::acquire($other, $name, 0))->toBeTrue();
    }

    try {
        $worker->processAndCatchErrors($id);
    } finally {
        if ($failure === 'lock') {
            FOSSBilling\Core\Doctrine\NamedLock::release($other, $name);
        }
    }
    if (in_array($failure, ['changed target', 'malformed draft'], true)) {
        $worker->processAndCatchErrors($id);
        expect($di['em']->isOpen())->toBeTrue()
            ->and($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('processed')
            ->and($db->fetchOne("SELECT invoice_id FROM $table WHERE id = ?", [$id]))->toBeNull()
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(15.0)
            ->and((bool) $db->fetchOne('SELECT issued FROM invoice WHERE id = ?', [$draft->getId()]))->toBeFalse();

        return;
    }
    if ($failure === 'invalid request') {
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('error')
            ->and(array_column($worker->getReceived(), 'id'))->not->toContain($id)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);

        return;
    }
    expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('received')
        ->and(array_column($worker->getReceived(), 'id'))->toContain($id)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
    if (in_array($failure, ['issuance', 'cancellation', 'persistent'], true)) {
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice WHERE subscription_payment_draft = 1 AND issued = 0'))->toBe(1);
    }
    // Simulate elapsed time by backdating the signature in the trusted fixture.
    $callback['server']['HTTP_STRIPE_SIGNATURE'] = $sign(time() - 600);
    expect(fn () => $worker->create($callback))->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Invalid Stripe webhook signature');
    $saved = $tx->getIpn();
    $saved['server']['HTTP_STRIPE_SIGNATURE'] = $callback['server']['HTTP_STRIPE_SIGNATURE'];
    $tx->setIpn($saved);
    $em->flush();

    if ($failure === 'persistent') {
        $draftId = $order->getUnpaidInvoiceId();
        $worker->processReceivedATransactions();
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('received')
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
        $worker->processReceivedATransactions();
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('received')
            ->and($issuanceAttempts)->toBe(3)
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$draftId]))->toBe(Invoice::STATUS_CANCELED)
            ->and($db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBeNull();
        $em->refresh($tx);
        expect($tx->getIpn()['subscription_renewal_draft_id'] ?? null)->toBe($draftId);
        $replacement = $billing->generateForOrder($order, subscriptionPayment: true);
        $worker->processReceivedATransactions();
        $worker->processReceivedATransactions();
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('processed')
            ->and($issuanceAttempts)->toBe(3)
            ->and($db->fetchOne("SELECT note FROM $table WHERE id = ?", [$id]))->toContain('Review the retained payment')
            ->and($db->fetchOne("SELECT invoice_id FROM $table WHERE id = ?", [$id]))->toBeNull()
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(15.0)
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$replacement->getId()]))->toBe(Invoice::STATUS_UNPAID)
            ->and($db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBe($replacement->getId());

        return;
    }

    if ($failure === 'cancellation') {
        $draftId = $order->getUnpaidInvoiceId();
        expect($draftId)->not->toBeNull();
        $request = (new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending')->setType('end_of_period');
        $em->persist($request);
        $em->flush();

        $worker->processReceivedATransactions();
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('processed')
            ->and($db->fetchOne("SELECT invoice_id FROM $table WHERE id = ?", [$id]))->toBeNull()
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(15.0)
            ->and($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$draftId]))->toBe(Invoice::STATUS_CANCELED)
            ->and($db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBeNull();
        $billing->generateInvoicesForExpiringOrders();
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(2);

        $requests = new Box\Mod\Order\ServiceCancelRequest();
        $requests->setDi($di);
        $requests->withdraw($order, $client, $request->getId());
        expect($db->fetchOne('SELECT status FROM client_order_cancel_request WHERE id = ?', [$request->getId()]))->toBe('withdrawn');
        $billing->shouldReceive('shouldMergeRenewalsForOrder')->andReturn(false);
        $billing->generateInvoicesForExpiringOrders();
        $worker->processReceivedATransactions();
        expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(3)
            ->and((int) $db->fetchOne("SELECT COUNT(*) FROM invoice WHERE id <> ? AND status = 'paid' AND subscription_payment_draft = 0", [$original->getId()]))->toBe(1)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1)
            ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(0.0);

        return;
    }

    $worker->processReceivedATransactions();
    if ($renewal) {
        expect($db->fetchOne("SELECT error FROM $table WHERE id = ?", [$id]))->toBeNull();
        expect($db->fetchOne("SELECT status FROM $table WHERE id = ?", [$id]))->toBe('processed')
            ->and((int) $db->fetchOne("SELECT gateway_completion_state FROM $table WHERE id = ?", [$id]))->toBe(Box\Mod\Invoice\Entity\Transaction::GATEWAY_COMPLETION_DONE)
            ->and((int) $db->fetchOne("SELECT COUNT(*) FROM invoice WHERE status = 'paid'"))->toBe(2)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(2)
            ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance WHERE gateway_receipt_key IS NOT NULL'))->toBe(1);
    } else {
        expect($db->fetchOne("SELECT id FROM $table WHERE id = ?", [$id]))->toBeFalse()
            ->and($db->fetchOne('SELECT status FROM subscription WHERE id = ?', [$subscription->getId()]))->toBe('active');
    }
})->with([
    'subscription update outage' => 'provider',
    'renewal connection outage' => 'connection',
    'renewal rate limit' => 'rate limit',
    'renewal provider server error' => 'server error',
    'invoice lookup outage during routing' => 'invoice lookup',
    'permanent Stripe request failure' => 'invalid request',
    'renewal mutex contention' => 'lock',
    'failed draft issuance' => 'issuance',
    'persistent issuance failure retains one payment' => 'persistent', 'changed target', 'malformed draft',
    'failed draft issuance followed by cancellation and withdrawal' => 'cancellation',
]);

test('cron leaves a draft created for a subscription payment to its callback', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $billing = renewalReviewService($em);
    $draft = $billing->generateForOrder($order, null, false, subscriptionPayment: true);
    $di = $billing->getDi();
    $services = $di['mod_service'];
    $orders = Mockery::mock(Box\Mod\Order\Service::class);
    $orders->shouldReceive('getSoonExpiringActiveOrders')->once()->andReturn([]);
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => $name === 'Order' ? $orders : $services($name, $sub));
    $billing->shouldNotReceive('tryPayWithCredits');
    $billing->generateInvoicesForExpiringOrders();
    $em->refresh($draft);
    expect($draft->isSubscriptionPaymentDraft())->toBeTrue()->and($draft->isIssued())->toBeFalse();
});

test('a held subscription callback leaves issued, ordinary and payment-linked invoices alone', function (string $invoiceType): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $billing = renewalReviewService($em);
    $draft = $billing->generateForOrder($order, subscriptionPayment: $invoiceType !== 'ordinary');
    if ($invoiceType === 'issued') {
        expect($billing->issueInvoice($draft, ['use_credits' => false]))->toBeTrue();
    }
    if ($invoiceType === 'linked to payment') {
        $transaction->setInvoice($draft);
    }
    $request = (new Box\Mod\Order\Entity\ClientOrderCancelRequest())->setOrder($order)->setClient($client)->setStatus('pending')->setType('end_of_period');
    $em->persist($request);
    $em->flush();

    expect(fn () => $billing->generateForOrder($order, subscriptionPayment: true))
        ->toThrow(Box\Mod\Invoice\Exception\RenewalHoldException::class);
    $db = $em->getConnection();
    expect($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$draft->getId()]))->toBe(Invoice::STATUS_UNPAID)
        ->and((int) $db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$order->getId()]))->toBe($draft->getId())
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$draft->getId()]))->toBe(1);
})->with(['issued', 'ordinary', 'linked to payment']);

test('a cron merge bucket drops an order whose expiry changed after grouping', function (string $change): void {
    [, $em, $client] = receiptTestContext();
    $first = renewalReviewOrder($em, $client->getId());
    $second = renewalReviewOrder($em, $client->getId());
    $date = new DateTimeImmutable('2030-01-01');
    $first->setExpiresAt($date)->setInvoiceOption('issue-invoice');
    $second->setExpiresAt($date)->setInvoiceOption('issue-invoice');
    $em->flush();
    $billing = renewalReviewService($em);
    $di = $billing->getDi();
    $services = $di['mod_service'];
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasUncanceledSubscriptionForOrder')->with($first)->andReturn(false);
    $subscriptions->shouldReceive('hasUncanceledSubscriptionForOrder')->with($second)->andReturn($change === 'subscription');
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $sub === 'Subscription' ? $subscriptions : $services($module, $sub));
    $changed = match ($change) {
        'expiry' => ['expires_at' => '2030-07-01 00:00:00'],
        'invoice option' => ['invoice_option' => 'no-invoice'],
        default => [],
    };
    if ($changed !== []) {
        $em->getConnection()->update('client_order', $changed, ['id' => $second->getId()]);
    }
    $invoice = $billing->generateMergedInvoiceForOrders([$first, $second], false, $client->getId() . '|USD|2030-01-01|');
    expect($invoice->getDueAt()?->format('Y-m-d'))->toBe('2030-01-01')
        ->and($second->getUnpaidInvoiceId())->toBeNull()
        ->and($em->getConnection()->fetchFirstColumn('SELECT rel_id FROM invoice_item WHERE invoice_id = ?', [$invoice->getId()]))->toBe([(string) $first->getId()]);
})->with(['expiry', 'invoice option', 'subscription']);

test('wallet credit is rejected before writing a reservation on a subscription payment invoice', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $billing = renewalReviewService($em);
    $billing->shouldReceive('resolveRenewalLine')->andReturn(['price' => 15.0, 'line' => ['quantity' => 1]]);
    $invoice = $billing->generateForOrder($order, subscriptionPayment: true);
    expect($billing->issueInvoice($invoice, ['use_credits' => false]))->toBeTrue();
    $db = $em->getConnection();
    $db->insert('client_balance', ['client_id' => $client->getId(), 'amount' => 15]);
    $staleFlag = new ReflectionProperty(Invoice::class, 'subscriptionPaymentDraft');
    $staleFlag->setValue($invoice, false);

    expect(fn () => $billing->applyCredit($invoice, 15.0))
        ->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'awaiting a subscription payment');
    expect((int) $db->fetchOne('SELECT COUNT(*) FROM invoice_credit'))->toBe(0)
        ->and($db->fetchOne('SELECT status FROM invoice WHERE id = ?', [$invoice->getId()]))->toBe(Invoice::STATUS_UNPAID)
        ->and((float) $db->fetchOne('SELECT SUM(amount) FROM client_balance'))->toBe(15.0);
});

test('renewal cron skips a moved order on single and fallback paths', function (string $path, bool $noInvoice): void {
    [, $em, $client] = receiptTestContext();
    $moved = renewalReviewOrder($em, $client->getId());
    $orders = [$moved];
    if ($path === 'fallback') {
        $orders[] = renewalReviewOrder($em, $client->getId());
    }
    foreach ($orders as $order) {
        $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    }
    $em->flush();
    $db = $em->getConnection();
    $billing = renewalReviewService($em);
    $di = $billing->getDi();
    $subs = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subs->shouldReceive('hasUncanceledSubscriptionForOrder')->andReturn(false);
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => $sub === 'Subscription' ? $subs : $services($name, $sub));
    $fields = $noInvoice ? ['invoice_option' => 'no-invoice'] : ['expires_at' => (new DateTimeImmutable('+6 months'))->format('Y-m-d H:i:s')];
    $move = fn () => $db->update('client_order', $fields, ['id' => $moved->getId()]);
    $logger = Mockery::mock(Psr\Log\LoggerInterface::class)->shouldIgnoreMissing();
    $di['logger'] = $logger;
    if ($path === 'fallback') {
        // Both orders were selected before the sibling entered deletion recovery.
        $billing->shouldReceive('shouldMergeRenewalsForOrder')->once()->with($moved)->andReturn(true);
        $billing->shouldReceive('shouldMergeRenewalsForOrder')->once()->with($orders[1])->andReturnUsing(function () use ($db, $orders): bool {
            Box\Mod\Order\DeletionRecovery::setState($db, $orders[1]->getId(), Box\Mod\Order\DeletionRecovery::STARTED);

            return true;
        });
        $logger->shouldReceive('warning')->twice()->with(Mockery::pattern('/deletion is awaiting recovery/'))->andReturnUsing($move, static fn () => null);
    } else {
        // The bucket uses the expiry that was selected before this edit.
        $billing->shouldReceive('shouldMergeRenewalsForOrder')->once()->with($moved)->andReturnUsing(function () use ($path, $move): bool {
            $move();

            return $path === 'single bucket';
        });
    }

    expect($billing->generateInvoicesForExpiringOrders())->toBeTrue()
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(0)
        ->and($db->fetchOne('SELECT unpaid_invoice_id FROM client_order WHERE id = ?', [$moved->getId()]))->toBeNull();
    $logger->shouldHaveReceived('warning')->with('The order changed after renewal selection.')->once();
})->with(['single bucket', 'separate invoice', 'fallback'])->with(['expiry changed' => false, 'invoice option changed' => true]);

test('cron recovers zero-priced renewal settlement from the issued checkpoint', function (bool $existingDraft): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    $em->flush();
    $billing = renewalReviewService($em);
    $billing->shouldReceive('resolveRenewalLine')->andReturn(['price' => 0.0, 'line' => ['quantity' => 1]]);
    $billing->shouldReceive('shouldMergeRenewalsForOrder')->andReturn(false);
    $di = $billing->getDi();
    $services = $di['mod_service'];
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $di['mod_service'] = $di->protect(fn (string $name, string $sub = ''): object => $sub === 'Subscription' ? $subscriptions : $services($name, $sub));
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    if ($existingDraft) {
        $billing->generateForOrder($order);
    }
    $billing->shouldReceive('tryPayWithCredits')->once()->andThrow(new RuntimeException('Credit processing interrupted'));
    $billing->shouldReceive('notifyDeferredInvoiceIssued')->once()->andThrow(new RuntimeException('Worker stopped after issuance'));
    $billing->generateInvoicesForExpiringOrders();

    $db = $em->getConnection();
    expect($db->fetchOne('SELECT status FROM invoice'))->toBe('unpaid')
        ->and((int) $db->fetchOne('SELECT issue_notification_state FROM invoice'))->toBe(1)
        ->and((int) $db->fetchOne('SELECT COUNT(*) FROM client_balance'))->toBe(0);
    $retry = renewalReviewService($em);
    $retryDi = $retry->getDi();
    $retryOrders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $retryOrders->setDi($retryDi);
    $retryOrders->shouldReceive('renewInvoiceItem')->once()->andReturn(true);
    $retryServices = $retryDi['mod_service'];
    $products = Mockery::mock(Box\Mod\Product\Service::class);
    $products->shouldReceive('findProductById')->andReturn((new Product())->setType('hosting'));
    $products->shouldReceive('commitReservedPromoRedemptionsForInvoice')->zeroOrMoreTimes();
    $retryDi['mod_service'] = $retryDi->protect(fn (string $name, string $sub = ''): object => match ($name) {
        'Order' => $retryOrders,
        'Product' => $products,
        default => $retryServices($name, $sub),
    });
    $retry->doBatchDeliverIssuedNotifications();
    $retry->doBatchDeliverIssuedNotifications();
    expect($db->fetchOne('SELECT status FROM invoice'))->toBe('paid')
        ->and((int) $db->fetchOne('SELECT issue_notification_state FROM invoice'))->toBe(0);
})->with(['new quote' => false, 'existing draft' => true]);

test('cron uses the original expiry cutoff when an order moves before ORM loading', function (bool $withinCutoff, bool $mayMerge): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    if ($mayMerge) {
        $peer = renewalReviewOrder($em, $client->getId());
        $peer->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    }
    $em->flush();
    $billing = renewalReviewService($em);
    $billing->shouldReceive('resolveRenewalLine')->andReturn(['price' => 15.0, 'line' => ['quantity' => 1]]);
    $billing->shouldReceive('shouldMergeRenewalsForOrder')->andReturn($mayMerge);
    $di = $billing->getDi();
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $orders = Mockery::mock(Box\Mod\Order\Service::class)->makePartial();
    $orders->setDi($di);
    $db = $em->getConnection();
    $orders->shouldReceive('getSoonExpiringActiveOrders')->once()->andReturnUsing(function () use ($orders, $db, $em, $order, $withinCutoff): array {
        [$query, $params] = $orders->getSoonExpiringActiveOrdersQuery();
        $selected = $db->fetchAllAssociative($query, $params);
        $db->update('client_order', ['expires_at' => (new DateTimeImmutable($withinCutoff ? 'yesterday' : '+6 months'))->format('Y-m-d H:i:s')], ['id' => $order->getId()]);
        $em->clear();

        return $selected;
    });
    $subscriptions = Mockery::mock(Box\Mod\Invoice\ServiceSubscription::class);
    $subscriptions->shouldReceive('hasUncanceledSubscriptionForOrder')->andReturn(false);
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ([$module, $sub]) {
        ['Order', ''] => $orders,
        ['Invoice', 'Subscription'] => $subscriptions,
        default => $services($module, $sub),
    });

    expect($billing->generateInvoicesForExpiringOrders())->toBeTrue();
    $billedOrders = $db->fetchFirstColumn('SELECT rel_id FROM invoice_item ORDER BY rel_id');
    expect(in_array((string) $order->getId(), $billedOrders, true))->toBe($withinCutoff)
        ->and(count($billedOrders))->toBe((int) $withinCutoff + (int) $mayMerge);
})->with([false, true])->with([false, true]);

test('recurring invoice selection requires the verified gateway and original invoice for a reused SID', function (): void {
    [, $em, $client, $gateway] = receiptTestContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $otherGateway = (new Box\Mod\Invoice\Entity\PayGateway())->setGateway('Stripe');
    $em->persist($otherGateway);
    $links = [];
    foreach ([$otherGateway, $gateway] as $linkedGateway) {
        $order = renewalReviewOrder($em, $client->getId());
        $source = (new Invoice())->setClientId($client->getId())->setIssued(true)->setStatus('paid')->setGateway($linkedGateway);
        $em->persist($source);
        $em->flush();
        $em->persist((new InvoiceItem())->setInvoice($source)->setType('order')->setTask('activate')->setRelId($order->getId() . ' '));
        $em->persist((new Box\Mod\Invoice\Entity\Subscription())->setPayGateway($linkedGateway)->setSid('sub_shared_id')->setRelType('invoice')->setRelId($source->getId())->setClientId($client->getId()));
        $em->flush();
        $links[] = [$order, $source];
    }
    [[$otherOrder, $otherSource], [$verifiedOrder, $verifiedSource]] = $links;
    $billing = renewalReviewService($em);
    $billing->shouldReceive('resolveRenewalLine')->andReturn(['price' => 15.0, 'line' => ['quantity' => 1]]);
    expect($billing->generateRenewalInvoiceForSubscriptionPayment('sub_shared_id', $client->getId(), $gateway->getId(), $otherSource->getId()))->toBeNull();
    $renewal = $billing->generateRenewalInvoiceForSubscriptionPayment('sub_shared_id', $client->getId(), $gateway->getId(), $verifiedSource->getId());
    expect($renewal)->toBeInstanceOf(Invoice::class)
        ->and($verifiedOrder->getUnpaidInvoiceId())->toBe($renewal->getId())
        ->and($otherOrder->getUnpaidInvoiceId())->toBeNull();
});

test('cron retires a stale quote only when its order is known and it has no payment activity', function (string $evidence): void {
    [, $em, $client, , $transaction] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    $em->flush();

    $billing = renewalReviewService($em);
    $di = $billing->getDi();
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $key = $client->getId() . '|USD|' . $order->getExpiresAt()->format('Y-m-d') . '|1M';
    $draft = $billing->generateForOrder($order, expectedMergeKey: $key);
    expect($draft->getCronRenewalExpiries())->toBe([$order->getId() => $order->getExpiresAt()->format('Y-m-d H:i:s')]);
    $billing->shouldNotReceive('tryPayWithCredits', 'getNextInvoiceNumber');
    if ($evidence === 'linked payment') {
        $transaction->setInvoice($draft);
        $em->flush();
    }
    $connection = $em->getConnection();
    if ($evidence === 'ambiguous order') {
        $connection->update('invoice_item', ['order_id' => null, 'rel_id' => 'no-order-link'], ['invoice_id' => $draft->getId()]);
    }
    $connection->update('client_order', ['expires_at' => (new DateTimeImmutable('+6 months'))->format('Y-m-d H:i:s')], ['id' => $order->getId()]);

    expect($billing->generateInvoicesForExpiringOrders())->toBeTrue();
    $em->refresh($draft);
    $em->refresh($order);
    $preserve = $evidence !== 'unfunded';
    expect($draft->isIssued())->toBeFalse()
        ->and($draft->getStatus())->toBe($preserve ? Invoice::STATUS_UNPAID : Invoice::STATUS_CANCELED)
        ->and($order->getUnpaidInvoiceId())->toBe($preserve ? $draft->getId() : null)
        ->and((int) $connection->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$draft->getId()]))->toBe((int) $preserve);
})->with(['unfunded', 'linked payment', 'ambiguous order']);

test('a verified subscription cannot use a draft shared with a different order', function (bool $beforeIssue): void {
    [, $em, $client, $gateway] = receiptTestContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $order = renewalReviewOrder($em, $client->getId());
    $peer = renewalReviewOrder($em, $client->getId());
    $source = (new Invoice())->setClientId($client->getId())->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($source);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($source)->setType(InvoiceItem::TYPE_ORDER)->setOrderId($order->getId()));
    $em->persist((new Box\Mod\Invoice\Entity\Subscription())->setPayGateway($gateway)->setSid('sub_late')->setRelType('invoice')->setRelId($source->getId()));
    $em->flush();

    $billing = renewalReviewService($em);
    if ($beforeIssue) {
        $draft = $billing->generateForOrder($order, null, false);
        $dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
        $dispatcher->addListener(Box\Mod\Invoice\Event\BeforeAdminInvoiceIssueEvent::class, function () use ($em, $draft, $peer): void {
            $em->persist((new InvoiceItem())->setInvoice($draft)->setType(InvoiceItem::TYPE_ORDER)
                ->setTask(InvoiceItem::TASK_ACTIVATE)->setOrderId($peer->getId()));
            $em->flush();
        });
        $billing->getDi()['event_dispatcher'] = $dispatcher;
    } else {
        $draft = $billing->generateMergedInvoiceForOrders([$order, $peer], false);
        $billing->shouldNotReceive('issueInvoice');
    }
    expect($billing->generateRenewalInvoiceForSubscriptionPayment('sub_late', $client->getId(), $gateway->getId(), $source->getId()))->toBeNull()
        ->and($draft->isIssued())->toBeFalse()
        ->and($order->getUnpaidInvoiceId())->toBe($draft->getId())
        ->and($peer->getUnpaidInvoiceId())->toBe($beforeIssue ? null : $draft->getId())
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$draft->getId()]))->toBe(2);
})->with(['before selection' => false, 'at issuance' => true]);

test('exhausted subscription recovery preserves a draft once payment is linked', function (): void {
    [, $em, $client, $gateway, $callback] = receiptTestContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $order = renewalReviewOrder($em, $client->getId());
    $source = (new Invoice())->setClientId($client->getId())->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($source);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($source)->setType(InvoiceItem::TYPE_ORDER)->setOrderId($order->getId()));
    $em->persist((new Box\Mod\Invoice\Entity\Subscription())->setPayGateway($gateway)->setSid('sub_exhausted')->setRelType('invoice')->setRelId($source->getId()));
    $em->flush();
    $billing = renewalReviewService($em);
    $draft = $billing->generateForOrder($order, subscriptionPayment: true);
    $callback->setIpn(['subscription_renewal_failures' => 3, 'subscription_renewal_draft_id' => $draft->getId()]);
    $em->persist((new Box\Mod\Invoice\Entity\Transaction())->setInvoice($draft));
    $em->flush();
    $billing->shouldNotReceive('issueInvoice');

    expect($billing->generateRenewalInvoiceForSubscriptionPayment('sub_exhausted', $client->getId(), $gateway->getId(), $source->getId(), $callback->getId()))->toBeNull()
        ->and($draft->getStatus())->toBe(Invoice::STATUS_UNPAID)
        ->and($order->getUnpaidInvoiceId())->toBe($draft->getId())
        ->and((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice_item WHERE invoice_id = ?', [$draft->getId()]))->toBe(1);
});

test('cron recovers a manual renewal draft without overriding its custom due date', function (): void {
    [, $em, $client] = receiptTestContext();
    $order = renewalReviewOrder($em, $client->getId());
    $order->setExpiresAt(new DateTimeImmutable('today'))->setPeriod('1M')->setInvoiceOption('issue-invoice');
    $em->flush();
    $billing = renewalReviewService($em);
    $di = $billing->getDi();
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $draft = $billing->generateForOrder($order, 7);
    $due = $draft->getDueAt()?->getTimestamp();
    expect($draft->getCronRenewalExpiries())->toBeNull();
    $billing->shouldReceive('tryPayWithCredits')->once()->with($draft)->andReturn(false);

    expect($billing->generateInvoicesForExpiringOrders())->toBeTrue();
    $em->refresh($draft);
    expect($draft->isIssued())->toBeTrue()
        ->and($draft->getDueAt()?->getTimestamp())->toBe($due)
        ->and($order->getUnpaidInvoiceId())->toBe($draft->getId());
});

test('cron keeps live subscriptions separate even when the subscription is pending cancellation', function (string $status, bool $merged): void {
    [, $em, $client, $gateway] = receiptTestContext();
    (new Doctrine\ORM\Tools\SchemaTool($em))->createSchema([$em->getClassMetadata(Box\Mod\Invoice\Entity\Subscription::class)]);
    $first = renewalReviewOrder($em, $client->getId());
    $second = renewalReviewOrder($em, $client->getId());
    $date = new DateTimeImmutable('today');
    foreach ([$first, $second] as $order) {
        $order->setExpiresAt($date)->setPeriod('1M')->setInvoiceOption('issue-invoice');
    }
    $source = (new Invoice())->setClientId($client->getId())->setStatus(Invoice::STATUS_PAID)->setIssued(true);
    $em->persist($source);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($source)->setType(InvoiceItem::TYPE_ORDER)->setOrderId($first->getId()));
    $em->persist((new Box\Mod\Invoice\Entity\Subscription())->setPayGateway($gateway)->setSid('sub_cron')->setRelType('invoice')
        ->setRelId($source->getId())->setStatus($status));
    $em->flush();

    $billing = renewalReviewService($em);
    $subscriptions = new Box\Mod\Invoice\ServiceSubscription();
    $di = $billing->getDi();
    $subscriptions->setDi($di);
    $di['period'] = $di->protect(fn (string $code): FOSSBilling\Core\Period => new FOSSBilling\Core\Period($code));
    $adapter = Mockery::mock(Payment_Adapter_Stripe::class);
    $adapter->shouldReceive('getSubscriptionStatus')->times($merged ? 2 : 0)->with('sub_cron')->andReturn('canceled');
    $gateways = Mockery::mock(Box\Mod\Invoice\ServicePayGateway::class);
    $gateways->shouldReceive('getPaymentAdapter')->andReturn($adapter);
    $services = $di['mod_service'];
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($sub) {
        'Subscription' => $subscriptions,
        'PayGateway' => $gateways,
        default => $services($module, $sub),
    });
    $billing->shouldReceive('shouldMergeRenewalsForOrder')->andReturn(true);
    $billing->shouldReceive('tryPayWithCredits')->andReturn(false);

    expect($billing->generateInvoicesForExpiringOrders())->toBeTrue()
        ->and($first->getUnpaidInvoiceId())->not->toBeNull()
        ->and($second->getUnpaidInvoiceId())->not->toBeNull()
        ->and($first->getUnpaidInvoiceId() === $second->getUnpaidInvoiceId())->toBe($merged);
})->with(['pending cancellation' => ['pending_cancellation', false], 'confirmed canceled' => ['canceled', true]]);
