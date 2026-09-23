<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\KbArticleCategory;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function kbArticleCategorySearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by title ascending with no filters', function (): void {
    $dql = kbArticleCategorySearchEntityManager()->getRepository(KbArticleCategory::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toContain('ORDER BY c.title ASC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = kbArticleCategorySearchEntityManager()->getRepository(KbArticleCategory::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'c.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'c.id', 'DESC'],
    'title' => [['sort' => 'title'], 'c.title', 'ASC'],
    'slug' => [['sort' => 'slug', 'direction' => 'desc'], 'c.slug', 'DESC'],
    'created_at' => [['sort' => 'created_at'], 'c.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'c.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'c.id; DROP TABLE kb_article_category'], 'c.title', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'slug', 'direction' => 'sideways'], 'c.slug', 'ASC'],
]);
