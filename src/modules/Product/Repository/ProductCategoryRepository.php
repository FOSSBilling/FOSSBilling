<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Product\Repository;

use Box\Mod\Product\Entity\Product;
use Box\Mod\Product\Entity\ProductCategory;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ProductCategoryRepository extends EntityRepository
{
    /**
     * @return array<int, string|null>
     */
    public function getPairs(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id, c.title')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = $row['title'];
        }

        return $pairs;
    }

    public function getEnabledVisibleSearchQueryBuilder(): QueryBuilder
    {
        // Arbitrary joins are treated as additional roots by Doctrine's paginator, which adds
        // their identifiers to the SELECT and breaks this aggregate under ONLY_FULL_GROUP_BY.
        $productFilter = $this->getEntityManager()->createQueryBuilder()
            ->select('1')
            ->from(Product::class, 'p')
            ->andWhere('p.productCategoryId = c.id')
            ->andWhere('p.status = :status')
            ->andWhere('p.hidden = :hidden')
            ->andWhere('p.isAddon = :isAddon');

        $maximumPriority = $this->getEntityManager()->createQueryBuilder()
            ->select('MAX(priorityProduct.priority)')
            ->from(Product::class, 'priorityProduct')
            ->andWhere('priorityProduct.productCategoryId = c.id')
            ->andWhere('priorityProduct.status = :status')
            ->andWhere('priorityProduct.hidden = :hidden')
            ->andWhere('priorityProduct.isAddon = :isAddon');

        $queryBuilder = $this->createQueryBuilder('c');

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->exists($productFilter->getDQL()))
            ->setParameter('status', 'enabled')
            ->setParameter('hidden', false)
            ->setParameter('isAddon', false)
            ->addSelect(sprintf('(%s) AS HIDDEN maxPriority', $maximumPriority->getDQL()))
            ->orderBy('maxPriority', 'ASC');
    }

    public function findById(int $id): ?ProductCategory
    {
        return $this->find($id);
    }
}
