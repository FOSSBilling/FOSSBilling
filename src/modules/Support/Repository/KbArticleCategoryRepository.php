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

use Box\Mod\Support\Entity\KbArticleCategory;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class KbArticleCategoryRepository extends EntityRepository
{
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.articles', 'a')
            ->groupBy('c.id')
            ->orderBy('c.title', 'ASC');

        if (!empty($data['article_status'])) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $data['article_status']);
        }

        if (!empty($data['q'])) {
            $qb->andWhere('(a.title LIKE :search OR a.content LIKE :search)')
                ->setParameter('search', '%' . $data['q'] . '%');
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
