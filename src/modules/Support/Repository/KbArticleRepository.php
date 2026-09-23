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
use Box\Mod\Support\KbSearch;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\SortOptions;

class KbArticleRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c');

        $categoryId = $data['kb_article_category_id'] ?? null;
        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('IDENTITY(a.category) = :categoryId')
                ->setParameter('categoryId', (int) $categoryId);
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== null && trim($search) !== '') {
            foreach (KbSearch::terms($search) as $index => $term) {
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

        $sort = SortOptions::fromArray($data, [
            'id' => 'a.id',
            'title' => 'a.title',
            'slug' => 'a.slug',
            'status' => 'a.status',
            'views' => 'a.views',
            'category' => 'c.title',
            'created_at' => 'a.createdAt',
            'updated_at' => 'a.updatedAt',
        ]);
        if ($sort->isSorted()) {
            $qb->orderBy($sort->expression, $sort->direction);
        } else {
            $qb->orderBy('a.title', 'ASC');
        }

        return $qb;
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
