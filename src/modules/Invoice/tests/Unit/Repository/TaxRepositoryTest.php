<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Invoice\Entity\Tax;
use Box\Mod\Invoice\Repository\TaxRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\ORMSetup;

test('findOneByStateAndCountry returns null when state is null', function (): void {
    $entityManager = Mockery::mock(EntityManager::class);
    $repository = new TaxRepository($entityManager, new ClassMetadata(Tax::class));

    expect($repository->findOneByStateAndCountry(null, 'US'))->toBeNull();
});

test('findOneByStateAndCountry returns null when country is null', function (): void {
    $entityManager = Mockery::mock(EntityManager::class);
    $repository = new TaxRepository($entityManager, new ClassMetadata(Tax::class));

    expect($repository->findOneByStateAndCountry('CA', null))->toBeNull();
});

test('findOneByCountry returns null when country is null', function (): void {
    $entityManager = Mockery::mock(EntityManager::class);
    $repository = new TaxRepository($entityManager, new ClassMetadata(Tax::class));

    expect($repository->findOneByCountry(null))->toBeNull();
});

test('getSearchQueryBuilder orders by id descending', function (): void {
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);
    $repository = $entityManager->getRepository(Tax::class);

    $dql = $repository->getSearchQueryBuilder([])->getDQL();

    expect($dql)->toBe('SELECT t FROM ' . Tax::class . ' t ORDER BY t.id DESC');
});

function taxSearchDql(array $data = []): string
{
    $config = ORMSetup::createAttributeMetadataConfig([dirname(__DIR__, 3) . '/Entity'], true);
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBilling\\Tests\\DoctrineProxies');
    $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $config);

    return $entityManager->getRepository(Tax::class)->getSearchQueryBuilder($data)->getDQL();
}

test('getSearchQueryBuilder sorts by allowlisted columns', function (array $data, string $expectedOrder, string $expectedDirection): void {
    expect(taxSearchDql($data))->toContain("ORDER BY {$expectedOrder} {$expectedDirection}");
})->with([
    'id ascending' => [['sort' => 'id'], 't.id', 'ASC'],
    'id descending' => [['sort' => 'id', 'direction' => 'DESC'], 't.id', 'DESC'],
    'name' => [['sort' => 'name'], 't.name', 'ASC'],
    'country' => [['sort' => 'country', 'direction' => 'desc'], 't.country', 'DESC'],
    'state' => [['sort' => 'state'], 't.state', 'ASC'],
    'taxrate' => [['sort' => 'taxrate'], 't.taxrate', 'ASC'],
    'created_at' => [['sort' => 'created_at'], 't.createdAt', 'ASC'],
    'updated_at' => [['sort' => 'updated_at'], 't.updatedAt', 'ASC'],
    'invalid sort falls back to default' => [['sort' => 't.id; DROP TABLE tax'], 't.id', 'DESC'],
    'invalid direction falls back to ascending' => [['sort' => 'name', 'direction' => 'sideways'], 't.name', 'ASC'],
]);
