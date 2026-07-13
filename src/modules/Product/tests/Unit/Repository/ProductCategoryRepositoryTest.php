<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

declare(strict_types=1);

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Box\Mod\Product\Repository\ProductCategoryRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

function productCategoryRepositoryCreateRepository(): ProductCategoryRepository
{
    $config = ORMSetup::createAttributeMetadataConfig(
        paths: [dirname(__DIR__, 3) . '/Entity'],
        isDevMode: true,
    );
    $config->setProxyDir(sys_get_temp_dir());
    $config->setProxyNamespace('FOSSBillingTestProxies');

    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    $entityManager = new EntityManager($connection, $config);

    return new ProductCategoryRepository($entityManager, $entityManager->getClassMetadata(ProductCategory::class));
}

test('enabled visible category query avoids a joined product paginator root', function (): void {
    $queryBuilder = productCategoryRepositoryCreateRepository()->getEnabledVisibleSearchQueryBuilder();
    $dql = $queryBuilder->getDQL();

    expect($queryBuilder->getDQLPart('join'))->toBe([]);
    expect($dql)
        ->toContain(sprintf('EXISTS(SELECT 1 FROM %s p', Product::class))
        ->toContain(sprintf('(SELECT MAX(priorityProduct.priority) FROM %s priorityProduct', Product::class))
        ->not->toContain('GROUP BY');
    expect($queryBuilder->getParameters()->toArray())->toHaveCount(3);

    expect($queryBuilder->getQuery()->getSQL())
        ->toBeString()
        ->not->toBeEmpty();
});
