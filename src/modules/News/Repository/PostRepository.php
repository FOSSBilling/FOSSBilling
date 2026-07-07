<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News\Repository;

use Box\Mod\News\Entity\Post;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PostRepository extends EntityRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findAdminSummary(int $adminId): ?array
    {
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT name FROM admin WHERE id = :id',
            ['id' => $adminId],
        );

        return $row !== false ? $row : null;
    }

    /**
     * Build a QueryBuilder for searching posts with optional filters.
     *
     * @param array $data array of filters: 'status', 'search', etc
     */
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        if (!empty($data['id'])) {
            $qb->andWhere('p.id = :id')
                ->setParameter('id', (int) $data['id']);
        }

        // Apply status filter
        if (!empty($data['status'])) {
            $qb->andWhere('p.status = :status')
            ->setParameter('status', $data['status']);
        }

        // Apply search filter (title OR content)
        if (!empty($data['search'])) {
            $qb->andWhere('(p.title LIKE :search OR p.slug LIKE :search OR COALESCE(p.description, \'\') LIKE :search OR COALESCE(p.section, \'\') LIKE :search OR COALESCE(p.content, \'\') LIKE :search)')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        // Apply section filter
        if (!empty($data['section'])) {
            $qb->andWhere('p.section LIKE :section')
               ->setParameter('section', '%' . $data['section'] . '%');
        }

        $qb->orderBy('p.createdAt', 'DESC');

        return $qb;
    }

    /**
     * Find an active post by its slug.
     */
    public function findOneActiveBySlug(string $slug): ?Post
    {
        return $this->findOneBy([
            'slug' => $slug,
            'status' => Post::STATUS_ACTIVE,
        ]);
    }

    /**
     * Find an active post by its ID.
     */
    public function findOneActiveById(int $id): ?Post
    {
        return $this->findOneBy([
            'id' => $id,
            'status' => Post::STATUS_ACTIVE,
        ]);
    }

    /**
     * Delete posts by a list of IDs in one go.
     *
     * @param int[] $ids
     *
     * @return int Number of affected rows
     */
    public function deleteByIds(array $ids): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }
}
