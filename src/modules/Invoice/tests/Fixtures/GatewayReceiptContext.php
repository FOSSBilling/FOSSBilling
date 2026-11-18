<?php

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Box\Mod\Invoice\Service;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Filesystem\Path;

use function Tests\Helpers\container;

function receiptTestContext(?Doctrine\DBAL\Connection $connection = null): array
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', '..')], true);
    $config->setUseSchemaEditorApi(true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $em = new EntityManager($connection ?? Tests\Helpers\fencedConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    if ($em->getConnection()->getDatabasePlatform() instanceof Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
        $em->getEventManager()->addEventListener(Doctrine\ORM\Tools\ToolEvents::postGenerateSchemaTable, new FOSSBilling\Core\Doctrine\IndexLengthsListener());
    }
    (new SchemaTool($em))->createSchema(array_map($em->getClassMetadata(...), [Box\Mod\Invoice\Entity\GatewayChargeAttempt::class, Box\Mod\Invoice\Entity\GatewayRefundAttempt::class, Box\Mod\Order\Entity\ClientOrderCancelRequest::class, Client::class, ClientBalance::class, PayGateway::class, Invoice::class, InvoiceItem::class, Box\Mod\Invoice\Entity\InvoiceCredit::class, Box\Mod\Order\Entity\Order::class, Box\Mod\Order\Entity\OrderMeta::class, Transaction::class]));
    $client = (new Client())->setCurrency('USD');
    $gateway = (new PayGateway())->setGateway('Stripe');
    $em->persist($client);
    $em->persist($gateway);
    $em->flush();
    $transaction = (new Transaction())->setGateway($gateway)->setAmount('15.00')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $em->persist($transaction);
    $em->flush();
    $di = container();
    $di['em'] = $em;
    $service = new Service();
    $service->setDi($di);

    return [$service, $em, $client, $gateway, $transaction];
}

/** @param class-string<Service> $serviceClass */
function gatewayDepositTestService(EntityManager $em, string $serviceClass = Service::class): Service
{
    $itemService = new Box\Mod\Invoice\ServiceInvoiceItem();
    $feeService = new Box\Mod\Invoice\ServiceGatewayFees();
    $balanceService = new Box\Mod\Client\ServiceBalance();
    $clientService = new Box\Mod\Client\Service();
    $productService = Mockery::mock(Box\Mod\Product\Service::class);
    $productService->shouldReceive('commitReservedPromoRedemptionsForInvoice')->zeroOrMoreTimes();
    $currencyRepo = Mockery::mock(Box\Mod\Currency\Repository\CurrencyRepository::class);
    $currencyRepo->shouldReceive('getRateByCode')->with('USD')->andReturn(1.0);
    $currencyService = Mockery::mock(Box\Mod\Currency\Service::class);
    $currencyService->shouldReceive('getCurrencyRepository')->andReturn($currencyRepo);
    $service = Mockery::mock($serviceClass)->makePartial();
    $service->shouldReceive('countIncome')->zeroOrMoreTimes();
    $service->shouldReceive('recordJournalEvent')->zeroOrMoreTimes();
    $di = container();
    $di['em'] = $em;
    $di['mod_service'] = $di->protect(fn (string $module, string $sub = ''): object => match ([$module, $sub]) {
        ['Invoice', 'InvoiceItem'] => $itemService,
        ['Invoice', 'GatewayFees'] => $feeService,
        ['Invoice', ''] => $service,
        ['Client', 'Balance'] => $balanceService,
        ['client', ''] => $clientService,
        ['Product', ''] => $productService,
        ['currency', ''] => $currencyService,
        ['Order', ''] => Mockery::mock(Box\Mod\Order\Service::class),
    });
    $itemService->setDi($di);
    $feeService->setDi($di);
    $balanceService->setDi($di);
    $clientService->setDi($di);
    $service->setDi($di);

    return $service;
}

function mixedManualDepositContext(float $serviceCharge): array
{
    [, $em, $client, $gateway, $first] = receiptTestContext();
    $invoice = (new Invoice())->setClientId($client->getId())->setCurrency('USD')->setStatus(Invoice::STATUS_UNPAID)->setIssued(true);
    $em->persist($invoice);
    $em->flush();
    $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_DEPOSIT)->setTask(InvoiceItem::TASK_VOID)
        ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice(100 - $serviceCharge)->setQuantity(1)->setCharged(true));
    if ($serviceCharge > 0) {
        $em->persist((new InvoiceItem())->setInvoice($invoice)->setType(InvoiceItem::TYPE_CUSTOM)->setTask(InvoiceItem::TASK_VOID)
            ->setStatus(InvoiceItem::STATUS_PENDING_PAYMENT)->setPrice($serviceCharge)->setQuantity(1)->setCharged(false));
    }
    $em->persist((new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('40')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSED));
    $first->setInvoice($invoice)->setAmount('20');
    $last = (new Transaction())->setGateway($gateway)->setInvoice($invoice)->setAmount('40')->setCurrency('USD')->setStatus(Transaction::STATUS_PROCESSING);
    $em->persist($last);
    $em->flush();

    return [$em, $client, $invoice, $first, $last];
}
