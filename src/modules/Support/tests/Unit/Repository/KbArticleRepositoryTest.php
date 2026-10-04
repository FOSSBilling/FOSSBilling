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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = kbArticleSearchEntityManager()->getRepository(KbArticle::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', a.id');
    } else {
        expect($dql)->not->toContain(', a.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY a.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY a.id DESC', false],
    'title' => [['sort' => 'title'], 'ORDER BY a.title ASC, a.id ASC', true],
    'slug' => [['sort' => 'slug'], 'ORDER BY a.slug ASC, a.id ASC', true],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'ORDER BY a.status DESC, a.id DESC', true],
    'views' => [['sort' => 'views'], 'ORDER BY a.views ASC, a.id ASC', true],
    'category' => [['sort' => 'category'], 'ORDER BY c.title ASC, a.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY a.createdAt ASC, a.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY a.updatedAt ASC, a.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'a.id; DROP TABLE kb_article'], 'ORDER BY a.title ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'views', 'direction' => 'sideways'], 'ORDER BY a.views ASC, a.id ASC', true],
]);
