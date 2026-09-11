<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Invoice;
use Box\Mod\Invoice\Entity\InvoiceItem;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function invoiceEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

function invoiceSearchDql(array $data = []): string
{
    return invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder($data)
        ->getDQL();
}

test('getSearchQueryBuilder orders by id descending with no filters', function (): void {
    $dql = invoiceSearchDql([]);

    expect($dql)->toBe('SELECT i FROM ' . Invoice::class . ' i ORDER BY i.id DESC');
});

test('getSearchQueryBuilder filters by client_id, status, currency and approved', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder([
            'client_id' => 7,
            'status' => Invoice::STATUS_PAID,
            'currency' => 'USD',
            'approved' => 1,
        ])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.clientId = :client_id')
        ->and($dql)->toContain('i.status = :status')
        ->and($dql)->toContain('i.currency = :currency')
        ->and($dql)->toContain('i.approved = :approved')
        ->and($dql)->toContain('ORDER BY i.id DESC');

    expect($query->getParameter('client_id')->getValue())->toBe(7)
        ->and($query->getParameter('status')->getValue())->toBe(Invoice::STATUS_PAID)
        ->and($query->getParameter('currency')->getValue())->toBe('USD')
        ->and($query->getParameter('approved')->getValue())->toBeTrue();
});

test('getSearchQueryBuilder normalizes the approved filter via \FOSSBilling\Core\Utils\Normalizer::normalizeBoolean', function (): void {
    $repository = invoiceEntityManager()->getRepository(Invoice::class);

    foreach ([1, '1', true, 'on', 'true'] as $truthy) {
        $qb = $repository->getSearchQueryBuilder(['approved' => $truthy]);
        expect($qb->getParameter('approved')->getValue())->toBeTrue();
    }

    foreach (['false', 'off', '0', 0, false] as $falsey) {
        $qb = $repository->getSearchQueryBuilder(['approved' => $falsey]);
        expect($qb->getParameter('approved')->getValue())->toBeFalse();
    }
});

test('getSearchQueryBuilder skips the approved filter when unset or empty', function (): void {
    $repository = invoiceEntityManager()->getRepository(Invoice::class);

    foreach ([null, ''] as $empty) {
        $qb = $repository->getSearchQueryBuilder(['approved' => $empty]);
        expect($qb->getDQL())->not->toContain('i.approved');
    }
});

test('getSearchQueryBuilder filters by id and nr separately', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['id' => 5, 'nr' => '2026-001'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.id = :id')
        ->and($dql)->toContain('i.id = :id_nr OR i.nr = :id_nr');

    expect($query->getParameter('id')->getValue())->toBe(5)
        ->and($query->getParameter('id_nr')->getValue())->toBe('2026-001');
});

test('getSearchQueryBuilder uses InvoiceItem subquery for order_id filter', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['order_id' => 42])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('SELECT IDENTITY(ii.invoice) FROM ' . InvoiceItem::class . ' ii WHERE ii.relId = :order_id AND ii.type = :item_type');

    expect($query->getParameter('order_id')->getValue())->toBe(42)
        ->and($query->getParameter('item_type')->getValue())->toBe(InvoiceItem::TYPE_ORDER);
});

test('getSearchQueryBuilder uses Client subquery for client-name filter', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['client' => 'alice'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('SELECT c.id FROM ' . Box\Mod\Client\Entity\Client::class . ' c WHERE c.firstName LIKE :client_search OR c.lastName LIKE :client_search OR c.id = :client OR c.email = :client');

    expect($query->getParameter('client_search')->getValue())->toBe('alice%')
        ->and($query->getParameter('client')->getValue())->toBe('alice');
});

test('getSearchQueryBuilder applies created_at as a day range', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['created_at' => '2026-08-12'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.createdAt >= :created_at_start AND i.createdAt < :created_at_end');

    expect($query->getParameter('created_at_start')->getValue())->toBe('2026-08-12 00:00:00')
        ->and($query->getParameter('created_at_end')->getValue())->toBe('2026-08-13 00:00:00');
});

test('getSearchQueryBuilder applies date_from and date_to on createdAt', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['date_from' => '2026-01-01', 'date_to' => '2026-01-15'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.createdAt >= :date_from')
        ->and($dql)->toContain('i.createdAt <= :date_to');

    // date_from starts at midnight; date_to covers the whole day (end-of-day
    // fix: invoices created later on the date_to day are included).
    expect($query->getParameter('date_from')->getValue())->toBe('2026-01-01 00:00:00')
        ->and($query->getParameter('date_to')->getValue())->toBe('2026-01-15 23:59:59');
});

test('getSearchQueryBuilder applies paid_at as a day range on paidAt', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['paid_at' => '2026-08-12'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.paidAt >= :paid_at_start AND i.paidAt < :paid_at_end');

    expect($query->getParameter('paid_at_start')->getValue())->toBe('2026-08-12 00:00:00')
        ->and($query->getParameter('paid_at_end')->getValue())->toBe('2026-08-13 00:00:00');
});

test('getSearchQueryBuilder applies search filter with id, nr, title subquery and bindings', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder(['search' => 'Hosting 42'])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.id = :search_numeric_id')
        ->and($dql)->toContain('i.nr LIKE :search_like')
        ->and($dql)->toContain('i.id LIKE :search')
        ->and($dql)->toContain('SELECT IDENTITY(ii.invoice) FROM ' . InvoiceItem::class . ' ii WHERE ii.title LIKE :search_like');

    expect($query->getParameter('search_numeric_id')->getValue())->toBe(42)
        ->and($query->getParameter('search_like')->getValue())->toBe('%Hosting 42%')
        ->and($query->getParameter('search')->getValue())->toBe('Hosting 42');
});

test('getInvoiceTotals returns empty array for no ids', function (): void {
    $repository = invoiceEntityManager()->getRepository(Invoice::class);

    expect($repository->getInvoiceTotals([]))->toBe([]);
});

test('getInvoiceTotals aggregates subtotal and taxable subtotal per invoice', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Invoice::class, InvoiceItem::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $invoice = new Invoice();
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $entityManager->persist($invoice);

    foreach ([
        ['price' => 10.0, 'quantity' => 2, 'taxed' => true],
        ['price' => 5.5, 'quantity' => 1, 'taxed' => false],
        ['price' => 3.0, 'quantity' => 4, 'taxed' => true],
    ] as $item) {
        $invoiceItem = new InvoiceItem();
        $invoiceItem->setInvoice($invoice);
        $invoiceItem->setPrice($item['price']);
        $invoiceItem->setQuantity($item['quantity']);
        $invoiceItem->setTaxed($item['taxed']);
        $entityManager->persist($invoiceItem);
    }
    $entityManager->flush();

    $totals = $entityManager->getRepository(Invoice::class)->getInvoiceTotals([1]);

    expect($totals)->toHaveKey(1);
    // 10*2 + 5.5*1 + 3*4 = 37.5 ; taxable: 20 + 12 = 32
    expect($totals[1]['subtotal'])->toBe(37.5)
        ->and($totals[1]['taxable_subtotal'])->toBe(32.0);
});

test('getInvoiceTotals omits invoices without items', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Invoice::class, InvoiceItem::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $invoice = new Invoice();
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $entityManager->persist($invoice);
    $entityManager->flush();

    $totals = $entityManager->getRepository(Invoice::class)->getInvoiceTotals([1]);

    expect($totals)->toBe([]);
});

test('findUnpaidOlderThan returns only unpaid invoices whose due date is far enough in the past', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Invoice::class, InvoiceItem::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $farOverdue = new Invoice();
    $farOverdue->setStatus(Invoice::STATUS_UNPAID);
    $farOverdue->setDueAt(new DateTime('-10 days'));
    $entityManager->persist($farOverdue);

    $recentlyOverdue = new Invoice();
    $recentlyOverdue->setStatus(Invoice::STATUS_UNPAID);
    $recentlyOverdue->setDueAt(new DateTime('-2 days'));
    $entityManager->persist($recentlyOverdue);

    $noDueDate = new Invoice();
    $noDueDate->setStatus(Invoice::STATUS_UNPAID);
    $entityManager->persist($noDueDate);

    $paidButOverdue = new Invoice();
    $paidButOverdue->setStatus(Invoice::STATUS_PAID);
    $paidButOverdue->setDueAt(new DateTime('-10 days'));
    $entityManager->persist($paidButOverdue);

    $entityManager->flush();

    $result = $entityManager->getRepository(Invoice::class)->findUnpaidOlderThan(5);

    expect($result)->toHaveCount(1)
        ->and($result[0]->getId())->toBe($farOverdue->getId());
});

test('lockAndGetStatus reads the status inside a transaction on every supported platform', function (): void {
    // A real connection, not a mock: this is the regression test for FOR UPDATE portability -
    // SQLite has no such clause, and would raise a syntax error here if RowLock ever regressed
    // to appending it unconditionally.
    $entityManager = invoiceEntityManager();
    $metadata = [$entityManager->getClassMetadata(Invoice::class)];
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $invoice = new Invoice();
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $entityManager->persist($invoice);
    $entityManager->flush();

    $connection = $entityManager->getConnection();
    $connection->beginTransaction();

    try {
        $status = $entityManager->getRepository(Invoice::class)->lockAndGetStatus($invoice->getId());
    } finally {
        $connection->rollBack();
    }

    expect($status)->toBe(Invoice::STATUS_UNPAID);
});

test('lockAndGetStatus rejects being called outside of a transaction', function (): void {
    $entityManager = invoiceEntityManager();
    $metadata = [$entityManager->getClassMetadata(Invoice::class)];
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    expect(fn () => $entityManager->getRepository(Invoice::class)->lockAndGetStatus(1))
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Invoice status cannot be locked outside of a transaction.');
});
