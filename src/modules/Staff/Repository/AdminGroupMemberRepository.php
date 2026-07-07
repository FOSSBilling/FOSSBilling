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
use Box\Mod\Staff\Entity\AdminGroupMember;
use Doctrine\DBAL\ArrayParameterType;
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
        $memberships = $this->createQueryBuilder('m')
            ->select('m', 'g')
            ->innerJoin('m.adminGroup', 'g')
            ->andWhere('m.adminId = :admin_id')
            ->setParameter('admin_id', $adminId)
            ->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (AdminGroupMember $membership): AdminGroup => $membership->getAdminGroup(),
            $memberships,
        );
    }

    /**
     * @return int[]
     */
    public function getGroupIdsForAdmin(int $adminId): array
    {
        return array_map(
            static fn (AdminGroup $group): int => (int) $group->getId(),
            $this->findGroupsForAdmin($adminId),
        );
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

    public function findMembership(int $adminId, int $groupId): ?AdminGroupMember
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.adminGroup', 'g')
            ->andWhere('m.adminId = :admin_id')
            ->andWhere('g.id = :group_id')
            ->setParameter('admin_id', $adminId)
            ->setParameter('group_id', $groupId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteMembershipsForAdmin(int $adminId): int
    {
        return (int) $this->getEntityManager()->getConnection()->delete('admin_group_member', ['admin_id' => $adminId]);
    }

    /**
     * @return int[]
     */
    public function getMemberIdsInGroup(int $groupId): array
    {
        return array_map(intval(...), $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT a.id
             FROM admin a
             INNER JOIN admin_group_member m ON m.admin_id = a.id
             WHERE m.admin_group_id = :group_id
             AND (a.system_name IS NULL OR a.system_name != :system_name)
             ORDER BY a.id ASC',
            [
                'group_id' => $groupId,
                'system_name' => \Model_Admin::SYSTEM_CRON,
            ],
        ));
    }

    /**
     * @param int[] $groupIds
     *
     * @return array<int, array{id: int, email: string, name: string, signature: ?string, timezone: ?string}>
     */
    public function getActiveStaffInGroups(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT DISTINCT a.id, a.email, a.name, a.signature, a.timezone
             FROM admin a
             INNER JOIN admin_group_member m ON m.admin_id = a.id
             WHERE m.admin_group_id IN (:group_ids)
             AND a.status = :status
             AND (a.system_name IS NULL OR a.system_name != :system_name)
             ORDER BY a.id ASC',
            [
                'group_ids' => $groupIds,
                'status' => \Model_Admin::STATUS_ACTIVE,
                'system_name' => \Model_Admin::SYSTEM_CRON,
            ],
            [
                'group_ids' => ArrayParameterType::INTEGER,
            ],
        );
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
             WHERE a.status = :status
             AND g.system_name = :system_name
             AND (a.system_name IS NULL OR a.system_name != :cron_system_name)',
            [
                'status' => \Model_Admin::STATUS_ACTIVE,
                'system_name' => $systemName,
                'cron_system_name' => \Model_Admin::SYSTEM_CRON,
            ],
        );
    }

    public function countMembersInGroup(int $groupId): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT admin_id)
             FROM admin_group_member
             WHERE admin_group_id = :group_id',
            ['group_id' => $groupId],
        );
    }
}
