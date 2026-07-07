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
        return $this->createQueryBuilder('c')
            ->innerJoin(Product::class, 'p', 'WITH', 'p.productCategoryId = c.id')
            ->andWhere('p.status = :status')
            ->andWhere('p.hidden = :hidden')
            ->andWhere('p.isAddon = :isAddon')
            ->setParameter('status', 'enabled')
            ->setParameter('hidden', false)
            ->setParameter('isAddon', false)
            ->addSelect('MAX(p.priority) AS HIDDEN maxPriority')
            ->groupBy('c.id')
            ->orderBy('maxPriority', 'ASC');
    }

    public function findById(int $id): ?ProductCategory
    {
        return $this->find($id);
    }
}
