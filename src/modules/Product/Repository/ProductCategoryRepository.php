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
use FOSSBilling\SortOptions;

class ProductCategoryRepository extends EntityRepository
{
    public function getMaxPriority(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('MAX(c.priority)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, string|null>
     */
    public function getPairs(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.id, c.title')
            ->orderBy('c.priority', \SortDirection::Ascending)
            ->addOrderBy('c.id', \SortDirection::Ascending)
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
        $productFilter = $this->applyEnabledVisibleProductFilters(
            $this->getEntityManager()->createQueryBuilder()
                ->select('1')
                ->from(Product::class, 'p'),
            'p',
        );

        $maximumPriority = $this->applyEnabledVisibleProductFilters(
            $this->getEntityManager()->createQueryBuilder()
                ->select('MAX(priorityProduct.priority)')
                ->from(Product::class, 'priorityProduct'),
            'priorityProduct',
        );

        $queryBuilder = $this->createQueryBuilder('c');

        return $queryBuilder
            ->andWhere($queryBuilder->expr()->exists($productFilter->getDQL()))
            ->setParameter('status', 'enabled')
            ->setParameter('hidden', false)
            ->setParameter('isAddon', false)
            ->addSelect(sprintf('(%s) AS HIDDEN maxPriority', $maximumPriority->getDQL()))
            ->orderBy('c.priority', \SortDirection::Ascending)
            ->addOrderBy('maxPriority', \SortDirection::Ascending)
            ->addOrderBy('c.id', \SortDirection::Ascending);
    }

    public function getAdminSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($data['search'])) {
            $qb->andWhere('c.title LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        $sort = SortOptions::fromArray($data, [
            'id' => 'c.id',
            'title' => 'c.title',
            'priority' => 'c.priority',
            'created_at' => 'c.createdAt',
            'updated_at' => 'c.updatedAt',
        ]);
        if ($sort->isSorted()) {
            $qb->orderBy($sort->expression, $sort->direction);
            if ($sort->expression !== 'c.id') {
                $qb->addOrderBy('c.id', $sort->direction);
            }
        } else {
            $qb->orderBy('c.priority', \SortDirection::Ascending)
                ->addOrderBy('c.id', \SortDirection::Ascending);
        }

        return $qb;
    }

    private function applyEnabledVisibleProductFilters(QueryBuilder $queryBuilder, string $alias): QueryBuilder
    {
        return $queryBuilder
            ->andWhere(sprintf('IDENTITY(%s.productCategory) = c.id', $alias))
            ->andWhere(sprintf('%s.status = :status', $alias))
            ->andWhere(sprintf('%s.hidden = :hidden', $alias))
            ->andWhere(sprintf('%s.isAddon = :isAddon', $alias));
    }

    public function findById(int $id): ?ProductCategory
    {
        return $this->find($id);
    }
}
