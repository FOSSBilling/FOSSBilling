<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

use Box\Mod\Product\Entity\Promo;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Filesystem\Path;

function promoEntityManager(): EntityManager
{
    $config = ORMSetup::createAttributeMetadataConfig([Path::join(__DIR__, '..', '..', '..', 'Entity')], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    (new Doctrine\ORM\Tools\SchemaTool($entityManager))->createSchema([$entityManager->getClassMetadata(Promo::class)]);

    return $entityManager;
}

test('decrementUsage floors at zero rather than going negative on SQLite', function (): void {
    // A real connection, not a mock: this is the regression test for GREATEST() portability -
    // SQLite has no such function, and would raise a syntax error here if this ever regressed
    // to the raw MySQL form.
    $entityManager = promoEntityManager();
    $promo = new Promo();
    $promo->setUsed(2);
    $entityManager->persist($promo);
    $entityManager->flush();

    $repository = $entityManager->getRepository(Promo::class);
    $repository->decrementUsage($promo->getId(), 5, new DateTime());

    $entityManager->clear();
    $reloaded = $entityManager->getRepository(Promo::class)->find($promo->getId());

    expect($reloaded->getUsed())->toBe(0);
});

test('decrementUsage subtracts normally when it would not go negative', function (): void {
    $entityManager = promoEntityManager();
    $promo = new Promo();
    $promo->setUsed(10);
    $entityManager->persist($promo);
    $entityManager->flush();

    $repository = $entityManager->getRepository(Promo::class);
    $repository->decrementUsage($promo->getId(), 3, new DateTime());

    $entityManager->clear();
    $reloaded = $entityManager->getRepository(Promo::class)->find($promo->getId());

    expect($reloaded->getUsed())->toBe(7);
});

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    $dql = promoEntityManager()->getRepository(Promo::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 'p.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'p.id', 'DESC'],
    'code' => [['sort' => 'code'], 'p.code', 'ASC'],
    'type' => [['sort' => 'type'], 'p.type', 'ASC'],
    'value' => [['sort' => 'value', 'direction' => 'desc'], 'p.value', 'DESC'],
    'active' => [['sort' => 'active'], 'p.active', 'ASC'],
    'priority' => [['sort' => 'priority'], 'p.priority', 'ASC'],
    'start_at' => [['sort' => 'start_at'], 'p.startAt', 'ASC'],
    'end_at' => [['sort' => 'end_at'], 'p.endAt', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 'p.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 'p.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 'p.id; DROP TABLE promo'], 'p.id', 'ASC'],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 'p.code', 'ASC'],
]);
