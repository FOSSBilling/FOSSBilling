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

use Box\Mod\Support\Entity\KbArticle;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class KbArticleRepository extends EntityRepository
{
    public function getSearchQueryBuilder(?string $status = null, ?string $search = null, int|string|null $categoryId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c');

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('IDENTITY(a.category) = :categoryId')
                ->setParameter('categoryId', (int) $categoryId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            $search = mb_strtolower(trim($search));
            $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($terms as $index => $term) {
                $qb->andWhere(sprintf(
                    '(LOWER(a.title) LIKE :searchTerm%s OR LOWER(a.content) LIKE :searchTerm%s OR LOWER(c.title) LIKE :searchTerm%s OR LOWER(c.description) LIKE :searchTerm%s)',
                    $index,
                    $index,
                    $index,
                    $index
                ))
                    ->setParameter('searchTerm' . $index, '%' . $term . '%');
            }
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

    public function countByCategoryId(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('IDENTITY(a.category) = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function incrementViews(KbArticle $article): void
    {
        $id = $article->getId();
        if ($id === null) {
            return;
        }

        $this->getEntityManager()->createQueryBuilder()
            ->update(KbArticle::class, 'a')
            ->set('a.views', 'a.views + 1')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->execute();

        $article->incrementViews();
    }
}
