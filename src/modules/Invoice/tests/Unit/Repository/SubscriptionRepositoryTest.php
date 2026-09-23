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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = subscriptionSearchEntityManager()->getRepository(Subscription::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', s.id');
    } else {
        expect($dql)->not->toContain(', s.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY s.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY s.id DESC', false],
    'sid' => [['sort' => 'sid'], 'ORDER BY s.sid ASC, s.id ASC', true],
    'status' => [['sort' => 'status', 'direction' => 'desc'], 'ORDER BY s.status DESC, s.id DESC', true],
    'currency' => [['sort' => 'currency'], 'ORDER BY s.currency ASC, s.id ASC', true],
    'period' => [['sort' => 'period'], 'ORDER BY s.period ASC, s.id ASC', true],
    'amount' => [['sort' => 'amount'], 'ORDER BY s.amount ASC, s.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY s.createdAt ASC, s.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY s.updatedAt ASC, s.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 's.id; DROP TABLE subscription'], 'ORDER BY s.id DESC', false],
    'invalid direction falls back to ascending' => [['sort' => 'sid', 'direction' => 'sideways'], 'ORDER BY s.sid ASC, s.id ASC', true],
]);
