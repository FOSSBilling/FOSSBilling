<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Client\Entity\Client;
use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceCredit;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function invoiceCreditEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map($entityManager->getClassMetadata(...), [Client::class, Invoice::class, InvoiceItem::class, InvoiceCredit::class, Transaction::class, PayGateway::class]);
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    return $entityManager;
}

function seedCreditInvoice(EntityManager $em, Client $client, string $status, float $price, bool $taxed, string $type = InvoiceItem::TYPE_ORDER): Invoice
{
    $invoice = new Invoice();
    $invoice->setClientId($client->getId());
    $invoice->setStatus($status);
    $invoice->setCurrency('USD');
    $em->persist($invoice);

    $item = new InvoiceItem();
    $item->setInvoice($invoice);
    $item->setType($type);
    $item->setPrice((string) $price);
    $item->setQuantity(1);
    $item->setTaxed($taxed);
    $em->persist($item);
    $em->flush();

    return $invoice;
}

test('invoice credit sums group applied amounts by invoice', function (): void {
    $em = invoiceCreditEntityManager();
    $client = new Client();
    $em->persist($client);
    $em->flush();

    $first = seedCreditInvoice($em, $client, Invoice::STATUS_UNPAID, 100.0, true);
    $second = seedCreditInvoice($em, $client, Invoice::STATUS_UNPAID, 30.0, false);

    foreach ([['invoice' => $first, 'amount' => '10.00'], ['invoice' => $first, 'amount' => '5.00'], ['invoice' => $second, 'amount' => '40.00']] as $row) {
        $credit = new InvoiceCredit();
        $credit->setInvoice($row['invoice']);
        $credit->setClient($client);
        $credit->setAmount($row['amount']);
        $em->persist($credit);
    }
    $em->flush();

    $repo = $em->getRepository(InvoiceCredit::class);

    expect($repo->getSumByInvoiceId((int) $first->getId()))->toBe(15.00)
        ->and($repo->getSumsByInvoiceIds([(int) $first->getId(), (int) $second->getId()]))->toBe([(int) $first->getId() => 15.00, (int) $second->getId() => 40.00])
        ->and($repo->getSumsByInvoiceIds([]))->toBe([])
        ->and($repo->getSumByInvoiceId(999999))->toBe(0.0);
});

test('processed transaction sums skip other statuses', function (): void {
    $em = invoiceCreditEntityManager();
    $client = new Client();
    $em->persist($client);
    $em->flush();

    $invoice = seedCreditInvoice($em, $client, Invoice::STATUS_UNPAID, 100.0, true);

    foreach ([[Transaction::STATUS_PROCESSED, '25.50'], [Transaction::STATUS_RECEIVED, '99.00']] as [$status, $amount]) {
        $tx = new Transaction();
        $tx->setInvoice($invoice);
        $tx->setStatus($status);
        $tx->setAmount($amount);
        $tx->setCurrency('USD');
        $em->persist($tx);
    }
    $em->flush();

    $sums = $em->getRepository(Transaction::class)->getProcessedSumsByInvoiceIds([(int) $invoice->getId(), 999999]);

    expect($sums)->toBe([(int) $invoice->getId() => 25.50]);
});

test('unpaid non-deposit rows skip paid, deposit and excluded invoices', function (): void {
    $em = invoiceCreditEntityManager();
    $client = new Client();
    $em->persist($client);
    $em->flush();

    $unpaid = seedCreditInvoice($em, $client, Invoice::STATUS_UNPAID, 100.0, true);
    seedCreditInvoice($em, $client, Invoice::STATUS_PAID, 50.0, false);
    $deposit = seedCreditInvoice($em, $client, Invoice::STATUS_UNPAID, 20.0, false, InvoiceItem::TYPE_DEPOSIT);

    $repo = $em->getRepository(Invoice::class);

    $rows = $repo->findUnpaidNonDepositRowsByClientIds([(int) $client->getId()]);

    expect($rows)->toBe([
        ['id' => (int) $unpaid->getId(), 'client_id' => (int) $client->getId(), 'taxrate' => 0.0],
    ]);
    expect($repo->findUnpaidNonDepositRowsByClientIds([(int) $client->getId()], (int) $unpaid->getId()))->toBe([]);
    expect($repo->findUnpaidNonDepositRowsByClientIds([]))->toBe([]);
    expect($deposit->getId())->not->toBeNull();
});
