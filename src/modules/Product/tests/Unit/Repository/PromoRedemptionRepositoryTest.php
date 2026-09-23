<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Promo;
use Box\Mod\Product\Entity\PromoRedemption;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function promoRedemptionEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBillingTestProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

// The promo is mapped as a `promo` association rather than a `promoId` field, so these queries
// have to filter on the association or they fail to compile at all.
test('promo filtered queries match only redemptions of the given promo', function (): void {
    $entityManager = promoRedemptionEntityManager();
    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Promo::class, PromoRedemption::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $promo = new Promo();
    $unused = new Promo();
    $entityManager->persist($promo);
    $entityManager->persist($unused);

    $entityManager->persist((new PromoRedemption())
        ->setPromo($promo)
        ->setClientId(1)
        ->setClientOrderId(1)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_COMMITTED));
    $entityManager->flush();

    $repository = $entityManager->getRepository(PromoRedemption::class);
    $promoId = (int) $promo->getId();
    $unusedId = (int) $unused->getId();

    expect($repository->countByPromoId($promoId))->toBe(1)
        ->and($repository->countByPromoId($unusedId))->toBe(0);

    expect($repository->clientHasActiveCheckoutApplication($promoId, 1))->toBeTrue()
        ->and($repository->clientHasActiveCheckoutApplication($unusedId, 1))->toBeFalse()
        ->and($repository->clientHasActiveCheckoutApplication($promoId, 2))->toBeFalse();

    expect($repository->getUsageStatsByPromoId($promoId))
        ->toMatchArray([
            'recorded_applications' => 1,
            'checkout_applications' => 1,
            'committed_applications' => 1,
            'distinct_clients' => 1,
        ])
        ->and($repository->getUsageStatsByPromoId($unusedId)['recorded_applications'])->toBe(0);

    expect($repository->getSearchQueryBuilder(['promo_id' => $promoId])->getQuery()->getResult())->toHaveCount(1)
        ->and($repository->getSearchQueryBuilder(['promo_id' => $unusedId])->getQuery()->getResult())->toBe([])
        ->and($repository->getSearchQueryBuilder(['promo_id' => 0])->getQuery()->getResult())->toBe([]);
});

test('locking checkout-application check requires a transaction and sees committed rows', function (): void {
    $entityManager = promoRedemptionEntityManager();
    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Promo::class, PromoRedemption::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);
    // The locking variant mutexes on the client row, which the entity schema above does not
    // create; a minimal table is enough since the queries only touch id and updated_at.
    $entityManager->getConnection()->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, updated_at TEXT)');
    $entityManager->getConnection()->insert('client', ['id' => 1]);

    $promo = new Promo();
    $entityManager->persist($promo);
    $entityManager->flush();
    $promoId = (int) $promo->getId();

    $repository = $entityManager->getRepository(PromoRedemption::class);

    // Outside a transaction no lock can be held until the redemption rows are written.
    expect(fn () => $repository->clientHasActiveCheckoutApplicationForUpdate($promoId, 1))
        ->toThrow(FOSSBilling\Exception::class);

    expect($entityManager->wrapInTransaction(
        fn () => $repository->clientHasActiveCheckoutApplicationForUpdate($promoId, 1)
    ))->toBeFalse();

    $entityManager->persist((new PromoRedemption())
        ->setPromo($promo)
        ->setClientId(1)
        ->setClientOrderId(1)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_COMMITTED));
    $entityManager->flush();

    expect($entityManager->wrapInTransaction(
        fn () => $repository->clientHasActiveCheckoutApplicationForUpdate($promoId, 1)
    ))->toBeTrue();
});

test('locking checkout-application check mutexes the client row before reading', function (): void {
    // The COUNT alone cannot serialize insert-only rows; the client-row lock is what makes
    // concurrent checkouts wait for each other. Pin the order: mutex first, read second.
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturn(true);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\MySQLPlatform::class));
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with('SELECT id FROM client WHERE id = :client_id FOR UPDATE', ['client_id' => 1])
        ->andReturn(1);
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with(
            'SELECT COUNT(pr.id) FROM promo_redemption pr WHERE pr.promo_id = :promo_id AND pr.client_id = :client_id AND pr.phase = :phase AND pr.status IN (:statuses) FOR UPDATE',
            [
                'promo_id' => 5,
                'client_id' => 1,
                'phase' => PromoRedemption::PHASE_CHECKOUT,
                'statuses' => [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED],
            ],
            ['statuses' => Doctrine\DBAL\ArrayParameterType::STRING]
        )
        ->andReturn(0);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connection);

    $repository = new Box\Mod\Product\Repository\PromoRedemptionRepository(
        $emMock,
        new Doctrine\ORM\Mapping\ClassMetadata(PromoRedemption::class)
    );

    expect($repository->clientHasActiveCheckoutApplicationForUpdate(5, 1))->toBeFalse();
});

test('locking checkout-application check skips the aggregate lock on PostgreSQL', function (): void {
    // PostgreSQL rejects FOR UPDATE on aggregate queries outright. The client-row mutex is a
    // plain row select and stays locking; the COUNT goes without, which is still correct there
    // because READ COMMITTED gives every statement a fresh snapshot once the mutex is held.
    $connection = Mockery::mock(Doctrine\DBAL\Connection::class);
    $connection->shouldReceive('isTransactionActive')->once()->andReturn(true);
    $connection->shouldReceive('getDatabasePlatform')->andReturn(Mockery::mock(Doctrine\DBAL\Platforms\PostgreSQLPlatform::class));
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with('SELECT id FROM client WHERE id = :client_id FOR UPDATE', ['client_id' => 1])
        ->andReturn(1);
    $connection->shouldReceive('fetchOne')
        ->once()
        ->ordered()
        ->with(
            'SELECT COUNT(pr.id) FROM promo_redemption pr WHERE pr.promo_id = :promo_id AND pr.client_id = :client_id AND pr.phase = :phase AND pr.status IN (:statuses)',
            [
                'promo_id' => 5,
                'client_id' => 1,
                'phase' => PromoRedemption::PHASE_CHECKOUT,
                'statuses' => [PromoRedemption::STATUS_RESERVED, PromoRedemption::STATUS_COMMITTED],
            ],
            ['statuses' => Doctrine\DBAL\ArrayParameterType::STRING]
        )
        ->andReturn(0);

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connection);

    $repository = new Box\Mod\Product\Repository\PromoRedemptionRepository(
        $emMock,
        new Doctrine\ORM\Mapping\ClassMetadata(PromoRedemption::class)
    );

    expect($repository->clientHasActiveCheckoutApplicationForUpdate(5, 1))->toBeFalse();
});

function promoRedemptionPostgresDsn(): string
{
    return getenv('FOSSBILLING_TEST_PGSQL_DSN') ?: 'pgsql://postgres:postgres@127.0.0.1:5432/postgres';
}

function promoRedemptionPostgresAvailable(): bool
{
    try {
        DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(promoRedemptionPostgresDsn()))->fetchOne('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function promoRedemptionPostgresEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBillingTestProxies');

    return new EntityManager(DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(promoRedemptionPostgresDsn())), $config);
}

test('locking checkout-application check runs on real PostgreSQL', function (): void {
    $entityManager = promoRedemptionPostgresEntityManager();
    $connection = $entityManager->getConnection();
    // A single fixed, always-recreated schema rather than a fresh randomly-named one per test:
    // dropping-and-recreating it here means a run never leaves a stray schema behind.
    $connection->executeStatement('DROP SCHEMA IF EXISTS fb_promo_redemption_test CASCADE');
    $connection->executeStatement('CREATE SCHEMA fb_promo_redemption_test');
    $connection->executeStatement('SET search_path TO fb_promo_redemption_test');
    $metadata = array_map(
        $entityManager->getClassMetadata(...),
        [Promo::class, PromoRedemption::class],
    );
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);
    $connection->executeStatement('CREATE TABLE client (id SERIAL PRIMARY KEY, updated_at TIMESTAMP NULL)');
    $connection->insert('client', ['id' => 1]);

    $promo = new Promo();
    $entityManager->persist($promo);
    $entityManager->flush();
    $promoId = (int) $promo->getId();

    $repository = $entityManager->getRepository(PromoRedemption::class);

    expect($entityManager->wrapInTransaction(
        fn () => $repository->clientHasActiveCheckoutApplicationForUpdate($promoId, 1)
    ))->toBeFalse();

    $entityManager->persist((new PromoRedemption())
        ->setPromo($promo)
        ->setClientId(1)
        ->setClientOrderId(1)
        ->setPhase(PromoRedemption::PHASE_CHECKOUT)
        ->setStatus(PromoRedemption::STATUS_COMMITTED));
    $entityManager->flush();

    expect($entityManager->wrapInTransaction(
        fn () => $repository->clientHasActiveCheckoutApplicationForUpdate($promoId, 1)
    ))->toBeTrue();
})->skip(fn (): bool => !promoRedemptionPostgresAvailable(), 'No PostgreSQL server reachable at FOSSBILLING_TEST_PGSQL_DSN (or the localhost:5432 default) - this test only runs when one is available.');

test('find invoice summary selects stored serie and nr columns', function (): void {
    $entityManager = promoRedemptionEntityManager();
    $connection = $entityManager->getConnection();

    $connection->executeStatement('CREATE TABLE invoice (id INTEGER PRIMARY KEY, serie VARCHAR(50), nr VARCHAR(255), status VARCHAR(50), created_at VARCHAR(255))');
    $connection->insert('invoice', [
        'id' => 10,
        'serie' => 'INV-',
        'nr' => '42',
        'status' => 'paid',
        'created_at' => '2026-09-01 00:00:00',
    ]);

    $repository = $entityManager->getRepository(PromoRedemption::class);

    // Would throw if the query still selected the computed serie_nr column.
    $row = $repository->findInvoiceSummary(10);

    expect($row)->toMatchArray([
        'id' => 10,
        'serie' => 'INV-',
        'nr' => '42',
        'status' => 'paid',
    ])->and($row)->not->toHaveKey('serie_nr');

    expect($repository->findInvoiceSummary(999))->toBeNull();
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = promoRedemptionEntityManager()->getRepository(PromoRedemption::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'pr.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'pr.id', 'DESC'],
    'phase' => [['sort' => 'phase'], 'pr.phase', 'ASC'],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'pr.status', 'DESC'],
    'discount_amount' => [['sort' => 'discount_amount'], 'pr.discountAmount', 'ASC'],
    'committed_at' => [['sort' => 'committed_at'], 'pr.committedAt', 'ASC'],
    'released_at' => [['sort' => 'released_at'], 'pr.releasedAt', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 'pr.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'pr.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'pr.id; DROP TABLE promo_redemption'], 'pr.id', 'DESC'],
    'invalid direction falls back to ascending' => [['sort' => 'phase', 'direction' => 'sideways'], 'pr.phase', 'ASC'],
]);
