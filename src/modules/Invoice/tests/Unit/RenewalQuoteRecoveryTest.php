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
    $products->shouldReceive('getProductModuleService')->andReturn(new stdClass());
    $di = $service->getDi();
    $services = $di['mod_service'];
    unset($di['mod_service']);
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ($module) {
        'Product' => $products,
        'Currency' => $services('currency', $sub),
        default => $services($module, $sub),
    });
    expect($service->resolveRenewalLine($order))->toMatchArray(['price' => $quote, 'line' => ['price' => $quote, 'quantity' => 1, 'free_domain_renewal' => false]])
        ->and($service->generateForOrder($order))->toBe($invoice);
    expect(fn () => $service->generateMergedInvoiceForOrders([$order]))->toThrow(FOSSBilling\Core\Exception\InformationException::class, 'already waiting');
    expect((int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM invoice'))->toBe(1);
})->with([0.0, 25.0]);
