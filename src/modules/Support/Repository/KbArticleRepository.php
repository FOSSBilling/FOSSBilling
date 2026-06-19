<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Support\Repository;

use Box\Mod\Support\Entity\KbArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class KbArticleRepository extends EntityRepository
{
    public function getSearchQueryBuilder(?string $status = null, ?string $search = null, int|string|null $categoryId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a');

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('IDENTITY(a.category) = :categoryId')
                ->setParameter('categoryId', (int) $categoryId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('(a.title LIKE :search OR a.content LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('a.title', 'ASC');
    }

    public function findOneActiveById(int $id): ?KbArticle
    {
        return $this->findOneBy([
            'id' => $id,
            'status' => KbArticle::ACTIVE,
        ]);
    }

    public function findOneActiveBySlug(string $slug): ?KbArticle
    {
        return $this->findOneBy([
            'slug' => $slug,
            'status' => KbArticle::ACTIVE,
        ]);
    }

    /**
     * @return KbArticle[]
     */
    public function findActive(): array
    {
        return $this->findBy(['status' => KbArticle::ACTIVE], ['title' => 'ASC']);
    }

    /**
     * @return KbArticle[]
     */
    public function findByCategory(int $categoryId, bool $admin, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('IDENTITY(a.category) = :categoryId')
            ->setParameter('categoryId', $categoryId);

        if (!$admin) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', KbArticle::ACTIVE);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('(a.title LIKE :search OR a.content LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('a.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByCategoryId(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('IDENTITY(a.category) = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
