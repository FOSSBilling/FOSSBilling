<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages\Repository;

use Box\Mod\Custompages\Entity\CustomPage;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class CustomPageRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for searching custom pages with optional filters.
     *
     * @param array $data filters: 'id', 'slug', 'search'
     */
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($data['id'])) {
            $qb->andWhere('p.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        if (!empty($data['slug'])) {
            $qb->andWhere('p.slug LIKE :slug')
                ->setParameter('slug', '%' . $data['slug'] . '%');
        }

        if (!empty($data['search'])) {
            $qb->andWhere('(p.title LIKE :q OR p.slug LIKE :q OR p.description LIKE :q OR p.keywords LIKE :q OR p.content LIKE :q)')
                ->setParameter('q', '%' . $data['search'] . '%');
        }

        $qb->orderBy('p.id', 'DESC');

        return $qb;
    }

    /**
     * Find a custom page by its slug.
     */
    public function findOneBySlug(string $slug): ?CustomPage
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * Find a custom page by slug, excluding a given id.
     *
     * Used by the update path to detect slug conflicts against other pages,
     * mirroring the legacy `WHERE slug = ? AND id <> ?` check.
     */
    public function findOneBySlugExcludingId(string $slug, int $id): ?CustomPage
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.id != :id')
            ->setParameter('slug', $slug)
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete custom pages by a list of IDs in one go.
     *
     * @param int[] $ids
     *
     * @return int Number of affected rows
     */
    public function deleteByIds(array $ids): int
    {
        return (int) $this->createQueryBuilder('p')
            ->delete()
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
