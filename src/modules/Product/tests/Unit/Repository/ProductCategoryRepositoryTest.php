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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

function productCategoryRepositoryCreateRepository(): ProductCategoryRepository
{
    $entityManager = Mockery::mock(EntityManagerInterface::class);
    $classMetadata = Mockery::mock(ClassMetadata::class);
    $classMetadata->name = ProductCategory::class;
    $classMetadata->shouldReceive('getName')->andReturn(ProductCategory::class);

    $entityManager->shouldReceive('createQueryBuilder')
        ->andReturnUsing(static fn (): QueryBuilder => new QueryBuilder($entityManager));
    $entityManager->shouldReceive('getExpressionBuilder')->andReturn(new Expr());

    return new ProductCategoryRepository($entityManager, $classMetadata);
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
});
