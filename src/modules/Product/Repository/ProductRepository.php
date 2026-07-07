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
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class ProductRepository extends EntityRepository
{
    public function getMaxPriority(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('MAX(p.priority)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<int, string|null>
     */
    public function getAddonPairs(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id, p.title')
            ->where('p.isAddon = :isAddon')
            ->setParameter('isAddon', true)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = $row['title'];
        }

        return $pairs;
    }

    /**
     * @return array<int, string|null>
     */
    public function getPairs(array $data): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id, p.title');

        if ($data['products_only'] ?? true) {
            $qb->andWhere('p.isAddon = :isAddon')
                ->setParameter('isAddon', false);
        }

        if ($data['active_only'] ?? true) {
            $qb->andWhere('p.active = :active')
                ->setParameter('active', true);
        }

        if (!empty($data['type'])) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $data['type']);
        }

        $rows = $qb->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $pairs = [];
        foreach ($rows as $row) {
            $pairs[(int) $row['id']] = $row['title'];
        }

        return $pairs;
    }

    public function getSearchQueryBuilder(array $data): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isAddon = :isAddon')
            ->setParameter('isAddon', false);

        if (!empty($data['type'])) {
            $qb->andWhere('p.type = :type')
                ->setParameter('type', $data['type']);
        }

        if (!empty($data['status'])) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $data['status']);
        }

        if (!($data['show_hidden'] ?? true)) {
            $qb->andWhere('p.hidden = :hidden')
                ->setParameter('hidden', false);
        }

        if (!empty($data['search'])) {
            $qb->andWhere('p.title LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        return $qb->orderBy('p.priority', 'ASC');
    }

    public function findActiveById(int $id): ?Product
    {
        return $this->findOneBy([
            'id' => $id,
            'active' => true,
            'status' => 'enabled',
            'isAddon' => false,
        ]);
    }

    public function findActiveBySlug(string $slug): ?Product
    {
        return $this->findOneBy([
            'slug' => $slug,
            'active' => true,
            'status' => 'enabled',
            'isAddon' => false,
        ]);
    }

    public function findMainDomainProduct(): ?Product
    {
        return $this->findOneBy([
            'type' => 'domain',
            'isAddon' => false,
        ]);
    }

    public function hasProductsInCategory(int $categoryId): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.productCategoryId = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * @return list<Product>
     */
    public function findEnabledVisibleByCategoryId(int $categoryId): array
    {
        return $this->findBy([
            'isAddon' => false,
            'status' => 'enabled',
            'hidden' => false,
            'productCategoryId' => $categoryId,
        ], [
            'priority' => 'ASC',
        ]);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->findBy([
            'id' => $ids,
        ]);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Product>
     */
    public function findAddonsByIds(array $ids, ?int $excludeId = null, bool $includeUnavailable = false): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isAddon = :isAddon')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('type', 'custom')
            ->setParameter('isAddon', true)
            ->setParameter('ids', $ids)
            ->orderBy('p.id', 'ASC');

        if (!$includeUnavailable) {
            $qb->andWhere('p.active = :active')
                ->andWhere('p.status = :status')
                ->setParameter('active', true)
                ->setParameter('status', 'enabled');
        }

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findEnabledAddonById(int $id): ?Product
    {
        return $this->findOneBy([
            'id' => $id,
            'type' => 'custom',
            'isAddon' => true,
            'active' => true,
            'status' => 'enabled',
        ]);
    }
}
