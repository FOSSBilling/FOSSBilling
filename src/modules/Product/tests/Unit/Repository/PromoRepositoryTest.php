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

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrderBy, bool $expectsTieBreak): void {
    $dql = promoEntityManager()->getRepository(Promo::class)->getSearchQueryBuilder($data)->getDQL();

    expect($dql)->toContain($expectedOrderBy);
    if ($expectsTieBreak) {
        expect($dql)->toContain(', p.id');
    } else {
        expect($dql)->not->toContain(', p.id');
    }
})->with([
    'id ascending' => [['sort' => 'id'], 'ORDER BY p.id ASC', false],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 'ORDER BY p.id DESC', false],
    'code' => [['sort' => 'code'], 'ORDER BY p.code ASC, p.id ASC', true],
    'type' => [['sort' => 'type'], 'ORDER BY p.type ASC, p.id ASC', true],
    'value' => [['sort' => 'value', 'direction' => 'desc'], 'ORDER BY p.value DESC, p.id DESC', true],
    'active' => [['sort' => 'active'], 'ORDER BY p.active ASC, p.id ASC', true],
    'priority' => [['sort' => 'priority'], 'ORDER BY p.priority ASC, p.id ASC', true],
    'start_at' => [['sort' => 'start_at'], 'ORDER BY p.startAt ASC, p.id ASC', true],
    'end_at' => [['sort' => 'end_at'], 'ORDER BY p.endAt ASC, p.id ASC', true],
    'created_at' => [['sort' => 'created_at'], 'ORDER BY p.createdAt ASC, p.id ASC', true],
    'updated_at' => [['sort' => 'updated_at'], 'ORDER BY p.updatedAt ASC, p.id ASC', true],
    'invalid sort falls back to default' => [['sort' => 'p.id; DROP TABLE promo'], 'ORDER BY p.id ASC', false],
    'invalid direction falls back to ascending' => [['sort' => 'code', 'direction' => 'sideways'], 'ORDER BY p.code ASC, p.id ASC', true],
]);
