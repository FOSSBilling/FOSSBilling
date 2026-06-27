<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CannedResponseRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.category', 'c')
            ->addSelect('c')
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('r.title', 'ASC');

        if (isset($data['id']) && $data['id'] !== '') {
            $qb->andWhere('r.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        if (isset($data['category_id']) && $data['category_id'] !== '') {
            $qb->andWhere('IDENTITY(r.category) = :categoryId')
                ->setParameter('categoryId', (int) $data['category_id']);
        }

        if (isset($data['search']) && trim((string) $data['search']) !== '') {
            $search = '%' . mb_strtolower(trim((string) $data['search'])) . '%';
            $qb->andWhere('(LOWER(r.title) LIKE :search OR LOWER(r.content) LIKE :search OR LOWER(c.title) LIKE :search)')
                ->setParameter('search', $search);
        }

        return $qb;
    }

    /**
     * @return array<string, array<int, string|null>>
     */
    public function getGroupedPairs(): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.id, r.title, c.title AS categoryTitle')
            ->leftJoin('r.category', 'c')
            ->orderBy('c.id', 'ASC')
            ->addOrderBy('r.title', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(string) $row['categoryTitle']][(int) $row['id']] = $row['title'];
        }

        return $pairs;
    }

    public function countByCategoryId(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('IDENTITY(r.category) = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
