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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = productSearchEntityManager()->getRepository(Product::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', p.id');
    } else {
        expect($dql)->not->toContain(', p.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY p.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY p.id DESC', false],
    'title' => [['sort' => 'title'], 'ORDER BY p.title ASC, p.id ASC', true],
    'slug' => [['sort' => 'slug', 'direction' => 'desc'], 'ORDER BY p.slug DESC, p.id DESC', true],
    'status' => [['sort' => 'status'], 'ORDER BY p.status ASC, p.id ASC', true],
    'type' => [['sort' => 'type'], 'ORDER BY p.type ASC, p.id ASC', true],
    'priority' => [['sort' => 'priority'], 'ORDER BY p.priority ASC, p.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY p.createdAt ASC, p.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY p.updatedAt ASC, p.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'p.id; DROP TABLE product'], 'ORDER BY p.priority ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'title', 'direction' => 'sideways'], 'ORDER BY p.title ASC, p.id ASC', true],
]);
