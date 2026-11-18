<?php

declare(strict_types=1);

use Box\Mod\Order\Entity\Order;
use Box\Mod\Order\Entity\OrderMeta;
use Box\Mod\Order\Service;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\container;

function cancellationRegressionContext(): array
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', '..')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    (new SchemaTool($em))->createSchema([$em->getClassMetadata(Order::class), $em->getClassMetadata(OrderMeta::class), $em->getClassMetadata(Box\Mod\Order\Entity\ClientOrderCancelRequest::class)]);
    $order = (new Order())->setStatus(Order::STATUS_ACTIVE)->setExpiresAt(new DateTimeImmutable('-1 day'));
    $em->persist($order);
    $em->flush();
    $em->persist((new OrderMeta())->setOrder($order)->setName(Service::META_CANCEL_AT_PERIOD_END)->setValue('1'));
    $em->flush();

    return [$em, $order];
}

function cronRenewalRegressionContext(): array
{
    [$em, $order] = cancellationRegressionContext();
    $em->getConnection()->executeStatement('DELETE FROM client_order_meta WHERE client_order_id = ?', [$order->getId()]);
    (new SchemaTool($em))->createSchema([
        $em->getClassMetadata(Box\Mod\Invoice\Entity\Invoice::class),
        $em->getClassMetadata(Box\Mod\Invoice\Entity\InvoiceItem::class),
    ]);
    $invoice = (new Box\Mod\Invoice\Entity\Invoice())->setStatus('paid')->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $item = (new Box\Mod\Invoice\Entity\InvoiceItem())->setInvoice($invoice)->setType('order')->setTask('renew')
        ->setRelId((string) $order->getId())->setPrice(10)->setQuantity(1)->setStatus('pending_setup')->setCharged(true);
    $em->persist($item);
    $em->flush();
    [$orderService, $invoiceService] = cronRenewalRegressionServices($em);

    return [$em, $order, $item, $orderService, $invoiceService];
}

function cronRenewalRegressionServices(EntityManager $em): array
{
    $orderService = Mockery::mock(Service::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $orderService->shouldReceive('saveStatusChange')->zeroOrMoreTimes();
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $invoiceService = new Box\Mod\Invoice\Service();
    $di = container();
    $di['em'] = $em;
    $di['event_dispatcher'] = new Symfony\Component\EventDispatcher\EventDispatcher();
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => $module === 'Order' ? $orderService : $itemService);
    $orderService->setDi($di);
    $itemService->setDi($di);
    $invoiceService->setDi($di);

    return [$orderService, $invoiceService];
}

function freeHoldRegressionContext(string $kind): array
{
    [$em, $order, $item, $orders, $cron] = cronRenewalRegressionContext();
    $order->setProductId(5);
    $item->setPrice(0)->setStatus('failed');
    $prefix = $kind === 'free' ? 'free_domain_renewal_invoice_' : 'domain_renewal_zero_price_invoice_';
    $em->persist((new OrderMeta())->setOrder($order)->setName($prefix . $item->getInvoice()->getId())->setValue('1'));
    $em->persist((new OrderMeta())->setOrder($order)->setName('free_domain_renewal_claim_' . $item->getId())->setValue('1'));
    $em->flush();
    $products = Mockery::mock(Box\Mod\Product\Service::class);
    $products->shouldReceive('findProductById')->with(5)->andReturn((new Box\Mod\Product\Entity\Product())->setType(Box\Mod\Product\Service::DOMAIN));
    $products->shouldReceive('freeDomainRenewalUntil')->andReturn(time() + 86400 * 365);
    $di = $cron->getDi();
    $executor = ($di['mod_service'])('Invoice', 'InvoiceItem');
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($module) {
        'Order' => $orders,
        'Product' => $products,
        default => $executor,
    });

    return [$em, $order, $item, $orders, $cron, $executor];
}
