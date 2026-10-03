<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Entity\ClientGroup;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function clientBalanceEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    $metadata = array_map($entityManager->getClassMetadata(...), [Client::class, ClientBalance::class]);
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    return $entityManager;
}

test('getClientBalanceSum sums balance rows for the given client', function (): void {
    $entityManager = clientBalanceEntityManager();
    $client = new Client();
    $entityManager->persist($client);

    foreach (['10.00', '5.50', '-2.25'] as $amount) {
        $balance = new ClientBalance();
        $balance->setClient($client);
        $balance->setAmount($amount);
        $entityManager->persist($balance);
    }
    $entityManager->flush();

    $sum = $entityManager->getRepository(ClientBalance::class)->getClientBalanceSum($client->getId());

    expect($sum)->toBe(13.25);
});

test('getClientBalanceSum returns 0 for a client with no balance rows', function (): void {
    $entityManager = clientBalanceEntityManager();

    expect($entityManager->getRepository(ClientBalance::class)->getClientBalanceSum(999))->toBe(0.0);
});

test('getClientBalanceSumForUpdate locks and sums inside a transaction on every supported platform', function (): void {
    // A real connection, not a mock: this is the regression test for FOR UPDATE portability -
    // SQLite has no such clause, and would raise a syntax error here if RowLock ever regressed
    // to appending it unconditionally.
    $entityManager = clientBalanceEntityManager();
    $client = new Client();
    $entityManager->persist($client);

    $balance = new ClientBalance();
    $balance->setClient($client);
    $balance->setAmount('42.00');
    $entityManager->persist($balance);
    $entityManager->flush();

    $connection = $entityManager->getConnection();
    $connection->beginTransaction();

    try {
        $sum = $entityManager->getRepository(ClientBalance::class)->getClientBalanceSumForUpdate($client->getId());
    } finally {
        $connection->rollBack();
    }

    expect($sum)->toBe(42.0);
});

test('getClientBalanceSumForUpdate rejects being called outside of a transaction', function (): void {
    $entityManager = clientBalanceEntityManager();

    expect(fn () => $entityManager->getRepository(ClientBalance::class)->getClientBalanceSumForUpdate(1))
        ->toThrow(FOSSBilling\Exception::class, 'Client balance cannot be locked outside of a transaction.');
});

test('getClientBalanceSumForUpdate skips the aggregate lock on PostgreSQL', function (): void {
    // PostgreSQL rejects FOR UPDATE on aggregate queries outright. The client-row mutex is a
    // plain row select and stays locking; the SUM goes without, which is still correct there
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
        ->with('SELECT SUM(amount) FROM client_balance WHERE client_id = :client_id', ['client_id' => 1])
        ->andReturn('42.00');

    $emMock = Mockery::mock(Doctrine\ORM\EntityManagerInterface::class);
    $emMock->shouldReceive('getConnection')->andReturn($connection);

    $repository = new Box\Mod\Client\Repository\ClientBalanceRepository(
        $emMock,
        new Doctrine\ORM\Mapping\ClassMetadata(ClientBalance::class)
    );

    expect($repository->getClientBalanceSumForUpdate(1))->toBe(42.0);
});

function clientBalancePostgresDsn(): string
{
    return getenv('FOSSBILLING_TEST_PGSQL_DSN') ?: 'pgsql://postgres:postgres@127.0.0.1:5432/postgres';
}

function clientBalancePostgresAvailable(): bool
{
    try {
        DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(clientBalancePostgresDsn()))->fetchOne('SELECT 1');

        return true;
    } catch (Throwable) {
        return false;
    }
}

function clientBalancePostgresEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection((new Doctrine\DBAL\Tools\DsnParser())->parse(clientBalancePostgresDsn())), $config);
}

test('getClientBalanceSumForUpdate runs on real PostgreSQL', function (): void {
    $entityManager = clientBalancePostgresEntityManager();
    $connection = $entityManager->getConnection();
    // A single fixed, always-recreated schema rather than a fresh randomly-named one per test:
    // dropping-and-recreating it here means a run never leaves a stray schema behind.
    $connection->executeStatement('DROP SCHEMA IF EXISTS fb_client_balance_test CASCADE');
    $connection->executeStatement('CREATE SCHEMA fb_client_balance_test');
    $connection->executeStatement('SET search_path TO fb_client_balance_test');
    // ClientGroup is only here for Client's foreign key; the test never touches it.
    $metadata = array_map($entityManager->getClassMetadata(...), [Client::class, ClientBalance::class, ClientGroup::class]);
    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema($metadata);

    $client = new Client();
    $entityManager->persist($client);

    $balance = new ClientBalance();
    $balance->setClient($client);
    $balance->setAmount('42.00');
    $entityManager->persist($balance);
    $entityManager->flush();

    $sum = $entityManager->wrapInTransaction(
        fn () => $entityManager->getRepository(ClientBalance::class)->getClientBalanceSumForUpdate($client->getId())
    );

    expect($sum)->toBe(42.0);
})->skip(fn (): bool => !clientBalancePostgresAvailable(), 'No PostgreSQL server reachable at FOSSBILLING_TEST_PGSQL_DSN (or the localhost:5432 default) - this test only runs when one is available.');
