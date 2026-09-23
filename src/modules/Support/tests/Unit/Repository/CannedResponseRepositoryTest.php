<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\CannedResponse;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function cannedResponseSearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by category id and title with no filters', function (): void {
    $dql = cannedResponseSearchEntityManager()->getRepository(CannedResponse::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toContain('ORDER BY c.id ASC, r.title ASC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = cannedResponseSearchEntityManager()->getRepository(CannedResponse::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'r.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'r.id', 'DESC'],
    'title' => [['sort' => 'title'], 'r.title', 'ASC'],
    'category' => [['sort' => 'category', 'direction' => 'desc'], 'c.title', 'DESC'],
    'created_at' => [['sort' => 'created_at'], 'r.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'r.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'r.id; DROP TABLE canned_response'], 'c.id', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'r.title', 'ASC'],
]);
