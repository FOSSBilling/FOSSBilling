<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Subscription;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function subscriptionSearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by id descending with no filters', function (): void {
    $dql = subscriptionSearchEntityManager()->getRepository(Subscription::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toBe('SELECT s FROM ' . Subscription::class . ' s ORDER BY s.id DESC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = subscriptionSearchEntityManager()->getRepository(Subscription::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 's.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 's.id', 'DESC'],
    'sid' => [['sort' => 'sid'], 's.sid', 'ASC'],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 's.status', 'DESC'],
    'currency' => [['sort' => 'currency'], 's.currency', 'ASC'],
    'period' => [['sort' => 'period'], 's.period', 'ASC'],
    'amount' => [['sort' => 'amount'], 's.amount', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 's.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 's.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 's.id; DROP TABLE subscription'], 's.id', 'DESC'],
    'invalid direction falls back to ascending' => [['sort' => 'sid', 'direction' => 'sideways'], 's.sid', 'ASC'],
]);
