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
use Box\Mod\Invoice\Entity\PayGateway;
use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use FOSSBilling\Pagination\Options;
use FOSSBilling\Pagination\Service;
use Symfony\Component\Filesystem\Path;

function transactionEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

function transactionSearchQuery(array $data = []): object
{
    return transactionEntityManager()->getRepository(Transaction::class)
        ->getSearchQueryBuilder($data)
        ->getQuery();
}

test('competingTransactionQuery filters by txn id, gateway id, active statuses and excludes id', function (): void {
    $repository = transactionEntityManager()->getRepository(Transaction::class);

    $query = $repository->competingTransactionQuery('pi_123', 5, 9, [
        Transaction::STATUS_RECEIVED,
        Transaction::STATUS_PROCESSING,
        Transaction::STATUS_PROCESSED,
    ])->getQuery();
    $dql = $query->getDQL();

    expect($dql)->toContain('t.txnId = :txn_id')
        ->and($dql)->toContain('IDENTITY(t.gateway) = :gateway_id')
        ->and($dql)->toContain('t.id != :exclude_id')
        ->and($dql)->toContain('t.status IN (:statuses)');
    expect($query->getParameter('txn_id')->getValue())->toBe('pi_123')
        ->and($query->getParameter('gateway_id')->getValue())->toBe(5)
        ->and($query->getParameter('exclude_id')->getValue())->toBe(9)
        ->and($query->getParameter('statuses')->getValue())->toBe([
            Transaction::STATUS_RECEIVED,
            Transaction::STATUS_PROCESSING,
            Transaction::STATUS_PROCESSED,
        ]);
});

test('competingTransactionQuery omits gateway and exclude filters when not provided', function (): void {
    $repository = transactionEntityManager()->getRepository(Transaction::class);

    $query = $repository->competingTransactionQuery('pi_456', null, null, [
        Transaction::STATUS_PROCESSING,
        Transaction::STATUS_PROCESSED,
    ])->getQuery();
    $dql = $query->getDQL();

    expect($dql)->toContain('t.txnId = :txn_id')
        ->and($dql)->toContain('t.status IN (:statuses)')
        ->and($dql)->not->toContain('IDENTITY(t.gateway)')
        ->and($dql)->not->toContain('t.id !=');
    expect($query->getParameter('statuses')->getValue())->toBe([
        Transaction::STATUS_PROCESSING,
        Transaction::STATUS_PROCESSED,
    ]);
});

test('competingTransactionQuery applies gateway and exclude filters when provided', function (): void {
    $repository = transactionEntityManager()->getRepository(Transaction::class);

    $query = $repository->competingTransactionQuery('pi_789', 3, 7, [
        Transaction::STATUS_PROCESSING,
        Transaction::STATUS_PROCESSED,
    ])->getQuery();

    expect($query->getParameter('gateway_id')->getValue())->toBe(3)
        ->and($query->getParameter('exclude_id')->getValue())->toBe(7);
});

test('getSearchQueryBuilder orders by id descending and selects the gateway name', function (): void {
    $query = transactionSearchQuery([]);

    expect($query->getDQL())->toContain('SELECT t, pg.name AS gateway FROM ' . Transaction::class . ' t LEFT JOIN t.gateway pg')
        ->and($query->getDQL())->toContain('ORDER BY t.id DESC');
});

test('getSearchQueryBuilder filters by id, status, invoice_id, gateway_id, currency, type and txn_id', function (): void {
    $query = transactionSearchQuery([
        'id' => 5,
        'status' => 'processed',
        'invoice_id' => 12,
        'gateway_id' => 3,
        'currency' => 'USD',
        'type' => 'payment',
        'txn_id' => 'txn_abc',
    ]);

    $dql = $query->getDQL();
    expect($dql)->toContain('t.id = :id')
        ->and($dql)->toContain('t.status = :status')
        ->and($dql)->toContain('IDENTITY(t.invoice) = :invoice_id')
        ->and($dql)->toContain('IDENTITY(t.gateway) = :gateway_id')
        ->and($dql)->toContain('t.currency = :currency')
        ->and($dql)->toContain('t.type = :type')
        ->and($dql)->toContain('t.txnId = :txn_id');

    expect($query->getParameter('id')->getValue())->toBe(5)
        ->and($query->getParameter('status')->getValue())->toBe('processed')
        ->and($query->getParameter('invoice_id')->getValue())->toBe(12)
        ->and($query->getParameter('gateway_id')->getValue())->toBe(3)
        ->and($query->getParameter('currency')->getValue())->toBe('USD')
        ->and($query->getParameter('type')->getValue())->toBe('payment')
        ->and($query->getParameter('txn_id')->getValue())->toBe('txn_abc');
});

test('getSearchQueryBuilder uses Invoice subqueries for invoice_hash and client_id filters', function (): void {
    $query = transactionSearchQuery(['invoice_hash' => 'abc123', 'client_id' => 7]);

    $dql = $query->getDQL();
    expect($dql)->toContain('SELECT i.id FROM ' . Invoice::class . ' i WHERE i.hash = :hash')
        ->and($dql)->toContain('SELECT i.id FROM ' . Invoice::class . ' i WHERE i.clientId = :client_id');

    expect($query->getParameter('hash')->getValue())->toBe('abc123')
        ->and($query->getParameter('client_id')->getValue())->toBe(7);
});

test('getSearchQueryBuilder applies date_from and date_to with end-of-day date_to', function (): void {
    $query = transactionSearchQuery(['date_from' => '2026-01-01', 'date_to' => '2026-01-15']);

    $dql = $query->getDQL();
    expect($dql)->toContain('t.createdAt >= :date_from')
        ->and($dql)->toContain('t.createdAt <= :date_to');

    expect($query->getParameter('date_from')->getValue())->toBe('2026-01-01 00:00:00')
        ->and($query->getParameter('date_to')->getValue())->toBe('2026-01-15 23:59:59');
});

test('getSearchQueryBuilder applies the search filter on note, invoice id, txn id and ipn', function (): void {
    $query = transactionSearchQuery(['search' => 'keyword']);

    $dql = $query->getDQL();
    expect($dql)->toContain('t.note LIKE :note')
        ->and($dql)->toContain('IDENTITY(t.invoice) LIKE :search_invoice_id')
        ->and($dql)->toContain('t.txnId LIKE :search_txn_id')
        ->and($dql)->toContain('t.ipn LIKE :ipn');

    expect($query->getParameter('note')->getValue())->toBe('%keyword%')
        ->and($query->getParameter('search_invoice_id')->getValue())->toBe('%keyword%')
        ->and($query->getParameter('search_txn_id')->getValue())->toBe('%keyword%')
        ->and($query->getParameter('ipn')->getValue())->toBe('%keyword%');
});

test('paginateMappedQuery yields gateway-aware mixed rows', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Transaction::class, PayGateway::class],
    );
    (new SchemaTool($entityManager))->createSchema($metadata);

    $gateway = new PayGateway();
    $gateway->setName('Stripe');
    $entityManager->persist($gateway);

    $withGateway = new Transaction();
    $withGateway->setGateway($gateway);
    $withGateway->setStatus(Transaction::STATUS_RECEIVED);
    $entityManager->persist($withGateway);

    $withoutGateway = new Transaction();
    $withoutGateway->setStatus(Transaction::STATUS_RECEIVED);
    $entityManager->persist($withoutGateway);

    $entityManager->flush();

    $qb = $entityManager->getRepository(Transaction::class)->getSearchQueryBuilder([]);
    $result = (new Service())->paginateMappedQuery(
        $qb,
        new Options(perPage: 25),
        static fn ($row): array => [$row[0]::class, $row['gateway'] ?? null],
    );

    expect($result['total'])->toBe(2)
        ->and($result['list'])->toHaveCount(2);

    // ORDER BY t.id DESC: the second transaction (no gateway) comes first.
    $noGatewayRow = $result['list'][0];
    expect($noGatewayRow)->toBe([Transaction::class, null]);

    $gatewayRow = $result['list'][1];
    expect($gatewayRow)->toBe([Transaction::class, 'Stripe']);
});
