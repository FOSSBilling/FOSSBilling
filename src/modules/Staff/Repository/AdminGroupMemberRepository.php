<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff\Repository;

use Box\Mod\Staff\Entity\AdminGroup;
use Doctrine\ORM\EntityRepository;

class AdminGroupMemberRepository extends EntityRepository
{
    private function mergePermissions(array $permissions, array $groupPermissions): array
    {
        foreach ($groupPermissions as $module => $modulePermissions) {
            if (!is_array($modulePermissions)) {
                continue;
            }

            $permissions[$module] ??= [];

            foreach ($modulePermissions as $key => $value) {
                $permissions[$module][$key] = !empty($permissions[$module][$key]) || !empty($value);
            }
        }

        return $permissions;
    }

    /**
     * @return AdminGroup[]
     */
    public function findGroupsForAdmin(int $adminId): array
    {
        return $this->createQueryBuilder('m')
            ->select('g')
            ->innerJoin('m.adminGroup', 'g')
            ->andWhere('m.adminId = :admin_id')
            ->setParameter('admin_id', $adminId)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function adminBelongsToSystemGroup(int $adminId, string $systemName): bool
    {
        return (bool) $this->createQueryBuilder('m')
            ->select('1')
            ->innerJoin('m.adminGroup', 'g')
            ->andWhere('m.adminId = :admin_id')
            ->andWhere('g.systemName = :system_name')
            ->setParameter('admin_id', $adminId)
            ->setParameter('system_name', $systemName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<string, array<string, bool>>
     */
    public function getPermissionsForAdmin(int $adminId): array
    {
        $permissions = [];

        foreach ($this->findGroupsForAdmin($adminId) as $group) {
            $permissions = $this->mergePermissions($permissions, $group->getPermissions());
        }

        return $permissions;
    }

    public function countActiveMembersInSystemGroup(string $systemName): int
    {
        $connection = $this->getEntityManager()->getConnection();

        return (int) $connection->fetchOne(
            'SELECT COUNT(DISTINCT a.id)
             FROM admin a
             INNER JOIN admin_group_member m ON m.admin_id = a.id
             INNER JOIN admin_group g ON g.id = m.admin_group_id
             WHERE a.status = :status AND g.system_name = :system_name',
            [
                'status' => \Model_Admin::STATUS_ACTIVE,
                'system_name' => $systemName,
            ],
        );
    }

    public function countMembersInGroup(int $groupId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT admin_id)
             FROM (
                SELECT admin_id FROM admin_group_member WHERE admin_group_id = :group_id
                UNION
                SELECT id AS admin_id FROM admin WHERE admin_group_id = :group_id
             ) members',
            ['group_id' => $groupId],
        );
    }
}
