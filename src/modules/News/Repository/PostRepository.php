<?php

namespace Box\Mod\News\Repository;

use Box\Mod\News\Entity\Post;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class PostRepository extends EntityRepository
{
    /**
     * Build a QueryBuilder for searching posts with optional filters.
     *
     * @param array $data Array of filters: 'status', 'search', etc.
     * @return QueryBuilder
     */
    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p');

        // Apply status filter
        if (!empty($data['status'])) {
            $qb->andWhere('p.status = :status')
            ->setParameter('status', $data['status']);
        }

        // Apply search filter (title OR content)
        if (!empty($data['search'])) {
            $qb->andWhere('p.title LIKE :search OR p.content LIKE :search')
               ->setParameter('search', '%' . $data['search'] . '%');
        }

        // Apply section filter
        if (!empty($data['section'])) {
            $qb->andWhere('p.section = :section')
               ->setParameter('section', $data['section']);
        }

        $qb->orderBy('p.createdAt', 'DESC');

        return $qb;
    }
    
    /**
     * Find an active post by its slug
     * @param string $slug
     */
    public function findOneActiveBySlug(string $slug): ?Post
    {
        return $this->findOneBy([
            'slug'   => $slug,
            'status' => Post::STATUS_ACTIVE,
        ]);
    }

    /**
     * Find an active post by its ID
     * @param string $id
     */
    public function findOneActiveById(int $id): ?Post
    {
        return $this->findOneBy([
            'id'     => $id,
            'status' => Post::STATUS_ACTIVE,
        ]);
    }

    /**
     * Delete posts by a list of IDs in one go.
     *
     * @param int[] $ids
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
