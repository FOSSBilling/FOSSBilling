<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Product;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function productSearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by priority ascending with no filters', function (): void {
    $dql = productSearchEntityManager()->getRepository(Product::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toContain('ORDER BY p.priority ASC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = productSearchEntityManager()->getRepository(Product::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'p.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'p.id', 'DESC'],
    'title' => [['sort' => 'title'], 'p.title', 'ASC'],
    'slug' => [['sort' => 'slug', 'direction' => 'desc'], 'p.slug', 'DESC'],
    'status' => [['sort' => 'status'], 'p.status', 'ASC'],
    'type' => [['sort' => 'type'], 'p.type', 'ASC'],
    'priority' => [['sort' => 'priority'], 'p.priority', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 'p.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'p.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'p.id; DROP TABLE product'], 'p.priority', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'p.title', 'ASC'],
]);
