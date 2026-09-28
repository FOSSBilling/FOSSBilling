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
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

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

test('getSearchQueryBuilder filters by client_id, status, currency and issued', function (): void {
    $query = invoiceEntityManager()->getRepository(Invoice::class)
        ->getSearchQueryBuilder([
            'client_id' => 7,
            'status' => Invoice::STATUS_PAID,
            'currency' => 'USD',
            'issued' => 1,
        ])
        ->getQuery();

    $dql = $query->getDQL();
    expect($dql)->toContain('i.clientId = :client_id')
        ->and($dql)->toContain('i.status = :status')
        ->and($dql)->toContain('i.currency = :currency')
        ->and($dql)->toContain('i.issued = :issued')
        ->and($dql)->toContain('ORDER BY i.id DESC');

    expect($query->getParameter('client_id')->getValue())->toBe(7)
        ->and($query->getParameter('status')->getValue())->toBe(Invoice::STATUS_PAID)
        ->and($query->getParameter('currency')->getValue())->toBe('USD')
        ->and($query->getParameter('issued')->getValue())->toBeTrue();
});

test('getSearchQueryBuilder normalizes the issued filter via Tools::normalizeBoolean', function (): void {
    $repository = invoiceEntityManager()->getRepository(Invoice::class);

    foreach ([1, '1', true, 'on', 'true'] as $truthy) {
        $qb = $repository->getSearchQueryBuilder(['issued' => $truthy]);
        expect($qb->getParameter('issued')->getValue())->toBeTrue();
    }

    foreach (['false', 'off', '0', 0, false] as $falsey) {
        $qb = $repository->getSearchQueryBuilder(['issued' => $falsey]);
        expect($qb->getParameter('issued')->getValue())->toBeFalse();
    }
});

test('getSearchQueryBuilder skips the issued filter when unset or empty', function (): void {
    $repository = invoiceEntityManager()->getRepository(Invoice::class);

    foreach ([null, ''] as $empty) {
        $qb = $repository->getSearchQueryBuilder(['issued' => $empty]);
        expect($qb->getDQL())->not->toContain('i.issued');
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
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
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
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
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
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
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

test('lockAndGetState reads status and issue state inside a transaction', function (): void {
    $entityManager = invoiceEntityManager();
    $metadata = [$entityManager->getClassMetadata(Invoice::class)];
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $invoice = new Invoice();
    $invoice->setStatus(Invoice::STATUS_UNPAID);
    $invoice->setIssued(true);
    $entityManager->persist($invoice);
    $entityManager->flush();

    $connection = $entityManager->getConnection();
    $connection->beginTransaction();

    try {
        $state = $entityManager->getRepository(Invoice::class)->lockAndGetState($invoice->getId());
    } finally {
        $connection->rollBack();
    }

    expect($state)->toBe([
        'status' => Invoice::STATUS_UNPAID,
        'issued' => true,
    ]);
});

test('lockAndGetState uses DBAL boolean conversion for database text values', function (): void {
    $entityManager = invoiceEntityManager();
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturnTrue();
    $connection->shouldReceive('getDatabasePlatform')->once()->andReturn(new Doctrine\DBAL\Platforms\SQLitePlatform());
    $connection->shouldReceive('fetchAssociative')->once()->andReturn([
        'status' => Invoice::STATUS_UNPAID,
        'issued' => 'f',
    ]);
    $connection->shouldReceive('convertToPHPValue')
        ->once()
        ->with('f', Doctrine\DBAL\Types\Types::BOOLEAN)
        ->andReturnFalse();

    $repositoryEntityManager = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $repositoryEntityManager->shouldReceive('getConnection')->andReturn($connection);
    $repository = new Box\Mod\Invoice\Repository\InvoiceRepository(
        $repositoryEntityManager,
        $entityManager->getClassMetadata(Invoice::class),
    );

    expect($repository->lockAndGetState(1))->toBe([
        'status' => Invoice::STATUS_UNPAID,
        'issued' => false,
    ]);
});

test('lockAndGetStatus rejects being called outside of a transaction', function (): void {
    $entityManager = invoiceEntityManager();
    $metadata = [$entityManager->getClassMetadata(Invoice::class)];
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    expect(fn () => $entityManager->getRepository(Invoice::class)->lockAndGetStatus(1))
        ->toThrow(FOSSBilling\Exception::class, 'Invoice status cannot be locked outside of a transaction.');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = invoiceSearchDql($data);

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', i.id');
    } else {
        expect($dql)->not->toContain(', i.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY i.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY i.id DESC', false],
    'nr' => [['sort' => 'nr'], 'ORDER BY i.nr ASC, i.id ASC', true],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'ORDER BY i.status DESC, i.id DESC', true],
    'currency' => [['sort' => 'currency'], 'ORDER BY i.currency ASC, i.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY i.createdAt ASC, i.id ASC', true],
    'updated_at' => [['sort' => 'updated_at', 'direction' => 'DESC'], 'ORDER BY i.updatedAt DESC, i.id DESC', true],
    'paid_at' => [['sort' => 'paid_at'], 'ORDER BY i.paidAt ASC, i.id ASC', true],
    'due_at' => [['sort' => 'due_at'], 'ORDER BY i.dueAt ASC, i.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'i.id; DROP TABLE invoice'], 'ORDER BY i.id DESC', false],
    'invalid direction falls back to ascending' => [['sort' => 'status', 'direction' => 'sideways'], 'ORDER BY i.status ASC, i.id ASC', true],
]);
