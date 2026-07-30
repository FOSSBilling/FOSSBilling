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
