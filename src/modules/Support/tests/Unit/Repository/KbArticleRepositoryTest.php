<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\KbArticle;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function kbArticleSearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by title ascending with no filters', function (): void {
    $dql = kbArticleSearchEntityManager()->getRepository(KbArticle::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toContain('ORDER BY a.title ASC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = kbArticleSearchEntityManager()->getRepository(KbArticle::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'a.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'a.id', 'DESC'],
    'title' => [['sort' => 'title'], 'a.title', 'ASC'],
    'slug' => [['sort' => 'slug'], 'a.slug', 'ASC'],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'a.status', 'DESC'],
    'views' => [['sort' => 'views'], 'a.views', 'ASC'],
    'category' => [['sort' => 'category'], 'c.title', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 'a.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'a.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'a.id; DROP TABLE kb_article'], 'a.title', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'views', 'direction' => 'sideways'], 'a.views', 'ASC'],
]);
