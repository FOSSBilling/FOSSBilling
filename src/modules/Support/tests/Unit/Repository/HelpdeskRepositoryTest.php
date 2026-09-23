<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Support\Entity\Helpdesk;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function helpdeskSearchEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');

    return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
}

test('getSearchQueryBuilder orders by id descending with no filters', function (): void {
    $dql = helpdeskSearchEntityManager()->getRepository(Helpdesk::class)->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toBe('SELECT h FROM ' . Helpdesk::class . ' h ORDER BY h.id DESC');
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = helpdeskSearchEntityManager()->getRepository(Helpdesk::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', h.id');
    } else {
        expect($dql)->not->toContain(', h.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY h.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY h.id DESC', false],
    'name' => [['sort' => 'name'], 'ORDER BY h.name ASC, h.id ASC', true],
    'email' => [['sort' => 'email', 'direction' => 'desc'], 'ORDER BY h.email DESC, h.id DESC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY h.createdAt ASC, h.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY h.updatedAt ASC, h.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'h.id; DROP TABLE support_helpdesk'], 'ORDER BY h.id DESC', false],
    'invalid direction falls back to ascending' => [['sort' => 'name', 'direction' => 'sideways'], 'ORDER BY h.name ASC, h.id ASC', true],
]);
