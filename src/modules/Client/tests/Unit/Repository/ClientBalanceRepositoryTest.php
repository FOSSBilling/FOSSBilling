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
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function clientBalanceEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Core\\Tests\\DoctrineProxies');
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
        ->toThrow(FOSSBilling\Core\Exception\BaseException::class, 'Client balance cannot be locked outside of a transaction.');
});
