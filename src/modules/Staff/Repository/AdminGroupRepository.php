<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Repository;

use Box\Mod\Staff\Entity\AdminGroup;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

class AdminGroupRepository extends EntityRepository
{
    /**
     * @param array<int, array{name:string|null, parent_id:int|null}> $groups
     */
    private function buildPath(array $groups, int $groupId): string
    {
        $names = [];
        $seen = [];
        while (isset($groups[$groupId]) && !in_array($groupId, $seen, true)) {
            $seen[] = $groupId;
            array_unshift($names, (string) $groups[$groupId]['name']);
            $groupId = $groups[$groupId]['parent_id'] ?? 0;
        }

        return implode(' / ', $names);
    }

    public function findSuperAdministratorGroup(): AdminGroup
    {
        $group = $this->findOneBy(['systemName' => AdminGroup::SYSTEM_SUPER_ADMIN]);
        if (!$group instanceof AdminGroup) {
            throw new \FOSSBilling\InformationException('Super Administrator group not found');
        }

        return $group;
    }

    public function findById(int $id): ?AdminGroup
    {
        return $this->find($id);
    }

    /**
     * @return array<int, string|null>
     */
    public function getParentPairs(?int $excludeGroupId = null): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT id, name, parent_id FROM admin_group ORDER BY id ASC');
        $groups = [];
        foreach ($rows as $row) {
            $groups[(int) $row['id']] = [
                'name' => $row['name'],
                'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            ];
        }

        $excludedIds = [];
        if ($excludeGroupId !== null) {
            $excludedIds[] = $excludeGroupId;
            $childrenByParent = [];
            foreach ($rows as $row) {
                $childrenByParent[$row['parent_id'] === null ? 0 : (int) $row['parent_id']][] = (int) $row['id'];
            }

            $queue = $childrenByParent[$excludeGroupId] ?? [];
            while ($queue !== []) {
                $id = array_shift($queue);
                if (in_array($id, $excludedIds, true)) {
                    continue;
                }

                $excludedIds[] = $id;
                foreach ($childrenByParent[$id] ?? [] as $childId) {
                    $queue[] = $childId;
                }
            }
        }

        $pairs = [];
        foreach ($rows as $row) {
            if (in_array((int) $row['id'], $excludedIds, true)) {
                continue;
            }

            $pairs[(int) $row['id']] = $this->buildPath($groups, (int) $row['id']);
        }

        return $pairs;
    }

    /**
     * @return AdminGroup[]
     */
    public function findTreeSorted(array $data = []): array
    {
        $groups = $this->getSearchQueryBuilder($data)->getQuery()->getResult();

        $ids = [];
        foreach ($groups as $group) {
            $ids[(int) $group->getId()] = true;
        }

        $childrenByParent = [];
        foreach ($groups as $group) {
            $parentId = $group->getParent()?->getId();
            $childrenByParent[$parentId !== null && isset($ids[$parentId]) ? $parentId : 0][] = $group;
        }

        $sorted = [];
        $append = static function (int $parentId) use (&$append, &$childrenByParent, &$sorted): void {
            foreach ($childrenByParent[$parentId] ?? [] as $group) {
                $sorted[] = $group;
                $append((int) $group->getId());
            }
        };
        $append(0);

        return $sorted;
    }

    public function isDescendantOf(int $groupId, int $ancestorId): bool
    {
        return in_array($groupId, $this->getDescendantIdsForGroups([$ancestorId]), true);
    }

    /**
     * @param int[] $groupIds
     *
     * @return int[]
     */
    public function getDescendantIdsForGroups(array $groupIds): array
    {
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative('SELECT id, parent_id FROM admin_group');
        $childrenByParent = [];
        foreach ($rows as $row) {
            $parentId = $row['parent_id'] === null ? 0 : (int) $row['parent_id'];
            $childrenByParent[$parentId][] = (int) $row['id'];
        }

        $descendants = [];
        $queue = [];
        foreach ($groupIds as $groupId) {
            $queue = array_merge($queue, $childrenByParent[(int) $groupId] ?? []);
        }

        while ($queue !== []) {
            $id = array_shift($queue);
            if (in_array($id, $descendants, true)) {
                continue;
            }

            $descendants[] = $id;
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return $descendants;
    }

    public function getSearchQueryBuilder(array $data = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('g');

        if (!empty($data['search'])) {
            $qb->andWhere('g.name LIKE :search')
                ->setParameter('search', '%' . $data['search'] . '%');
        }

        return $qb->orderBy('g.id', 'ASC');
    }
}
