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

use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\KbSearch;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use FOSSBilling\SortOptions;

class KbArticleCategoryRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->distinct();

        // Use a WITH condition on the JOIN so the status filter does not turn the
        // LEFT JOIN into an implicit INNER JOIN. Categories with no active articles
        // must still appear in the results.
        if (!empty($data['article_status'])) {
            $qb->leftJoin('c.articles', 'a', \Doctrine\ORM\Query\Expr\Join::WITH, 'a.status = :status')
                ->addSelect('a')
                ->setParameter('status', $data['article_status']);
        } else {
            $qb->leftJoin('c.articles', 'a')
                ->addSelect('a');
        }

        if (isset($data['q']) && trim((string) $data['q']) !== '') {
            foreach (KbSearch::terms((string) $data['q']) as $index => $term) {
                $qb->andWhere(sprintf(
                    '(LOWER(c.title) LIKE :searchTerm%s OR LOWER(c.description) LIKE :searchTerm%s OR LOWER(a.title) LIKE :searchTerm%s OR LOWER(a.content) LIKE :searchTerm%s)',
                    $index,
                    $index,
                    $index,
                    $index
                ))
                    ->setParameter('searchTerm' . $index, '%' . $term . '%');
            }
        }

        $sort = SortOptions::fromArray($data, [
            'id' => 'c.id',
            'title' => 'c.title',
            'slug' => 'c.slug',
            'created_at' => 'c.createdAt',
            'updated_at' => 'c.updatedAt',
        ]);
        if ($sort->isSorted()) {
            $qb->orderBy($sort->expression, $sort->direction);
        } else {
            $qb->orderBy('c.title', 'ASC');
        }

        return $qb;
    }

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

    public function findOneBySlug(string $slug): ?KbArticleCategory
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
