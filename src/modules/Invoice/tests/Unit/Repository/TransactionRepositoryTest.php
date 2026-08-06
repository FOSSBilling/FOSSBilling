<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Transaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function transactionEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
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
        ->and($dql)->toContain('t.gatewayId = :gateway_id')
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
        ->and($dql)->not->toContain('t.gatewayId')
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
