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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = kbArticleCategorySearchEntityManager()->getRepository(KbArticleCategory::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', c.id');
    } else {
        expect($dql)->not->toContain(', c.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY c.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY c.id DESC', false],
    'title' => [['sort' => 'title'], 'ORDER BY c.title ASC, c.id ASC', true],
    'slug' => [['sort' => 'slug', 'direction' => 'desc'], 'ORDER BY c.slug DESC, c.id DESC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY c.createdAt ASC, c.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY c.updatedAt ASC, c.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'c.id; DROP TABLE kb_article_category'], 'ORDER BY c.title ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'slug', 'direction' => 'sideways'], 'ORDER BY c.slug ASC, c.id ASC', true],
]);
