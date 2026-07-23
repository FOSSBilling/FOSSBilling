<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 *Staff management.
 */

namespace Box\Mod\Staff\Api;

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Box\Mod\Activity\Repository\ActivityAdminHistoryRepository;
use Box\Mod\Staff\Entity\AdminGroup;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of staff members.
     *
     * @return array
     */
    public function get_list($data)
    {
        $this->checkPermissions('staff', 'view');

        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $staff = $this->getDi()['db']->getExistingModelById('Admin', $item['id'] ?? 0, 'Admin is not found');
            $pager['list'][$key] = $this->getService()->toModel_AdminApiArray($staff);
        }

        return $pager;
    }

    /**
     * Get ID-name pairs of admins.
     *
     * @param array $data Filtering options
     *
     * @return array List of admins
     */
    public function get_pairs(array $data): array
    {
        $this->checkPermissions('staff', 'view');

        return $this->getService()->getPairs($data);
    }

    /**
     * Get staff member by id.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'ID was not passed'])]
    public function get($data)
    {
        $this->checkPermissions('staff', 'view');

        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        return $this->getService()->toModel_AdminApiArray($model);
    }

    /**
     * Returns whether the logged-in staff member is a Super Administrator.
     */
    public function is_super_administrator($data): bool
    {
        return $this->getService()->isSuperAdministrator();
    }

    /**
     * Update staff member.
     *
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $status - new status
     * @optional string $signature - new signature
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the staff member.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'ID was not passed'])]
    public function update($data)
    {
        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        $this->checkPermissions('staff', 'create_and_edit_staff');

        if (isset($data['email'])) {
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);
        }

        return $this->getService()->update($model, $data);
    }

    /**
     * Completely delete staff member. Removes all related activity from logs.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'ID was not passed'])]
    public function delete($data)
    {
        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        $this->checkPermissions('staff', 'delete_staff');

        return $this->getService()->delete($model);
    }

    /**
     * Change staff member password.
     *
     * @return bool
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams([
        'id' => 'ID was not passed',
        'password' => 'Password required',
        'password_confirm' => 'Password confirmation required',
    ])]
    public function change_password($data)
    {
        $this->getDi()['validator']->passwordsMatch($data);

        $this->getDi()['validator']->isPasswordStrong($data['password']);

        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        $this->checkPermissions('staff', 'reset_staff_password');

        return $this->getService()->changePassword($model, $data['password']);
    }

    /**
     * Create new staff member.
     *
     * @optional string $signature - signature of new staff member
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the staff member.
     *
     * @return int - ID of newly created staff member
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams([
        'email' => 'Email address was not passed',
        'password' => 'Password was not passed',
        'name' => 'Name was not passed',
        'group_id' => 'Group ID was not passed',
    ])]
    public function create($data)
    {
        $this->checkPermissions('staff', 'create_and_edit_staff');

        $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);

        $this->getDi()['validator']->isPasswordStrong($data['password']);

        return $this->getService()->create($data);
    }

    private function getAdminGroupById(int $id): AdminGroup
    {
        $group = $this->getService()->getAdminGroupRepository()->findById($id);
        if (!$group instanceof AdminGroup) {
            throw new \FOSSBilling\Exception('Group not found');
        }

        return $group;
    }

    private function getAdminById(int $id): \Model_Admin
    {
        return $this->getDi()['db']->getExistingModelById('Admin', $id, 'Staff member not found');
    }

    /**
     * Return pairs of staff member groups.
     *
     * @return array
     */
    public function group_get_pairs($data)
    {
        $this->checkPermissions('staff', 'view');

        return $this->getService()->getAdminGroupRepository()->getParentPairs();
    }

    /**
     * Return list of staff members groups.
     */
    public function group_get_list($data): array
    {
        $this->checkPermissions('staff', 'manage_groups');

        $groups = $this->getService()->getAdminGroupRepository()->findTreeSorted($data);

        return [
            'list' => array_map(
                static fn (AdminGroup $group): array => $group->toApiArray(),
                $groups,
            ),
        ];
    }

    /**
     * Create new staff members group.
     *
     * @return int - new staff group ID
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['name' => 'Group name was not passed'])]
    public function group_create($data)
    {
        $this->checkPermissions('staff', 'manage_groups');

        $parent = empty($data['parent_id']) ? null : $this->getAdminGroupById((int) $data['parent_id']);

        return $this->getService()->createGroup($data['name'], $parent);
    }

    /**
     * Return staff group details.
     *
     * @return array - group details
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Group ID was not passed'])]
    public function group_get($data)
    {
        $this->checkPermissions('staff', 'manage_groups');

        $model = $this->getAdminGroupById((int) $data['id']);

        return $model->toApiArray();
    }

    /**
     * Remove staff group.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Group ID was not passed'])]
    public function group_delete($data)
    {
        $this->checkPermissions('staff', 'manage_groups');

        $model = $this->getAdminGroupById((int) $data['id']);

        return $this->getService()->deleteGroup($model);
    }

    /**
     * Update staff group.
     *
     * @optional int $name - new group name
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Group ID was not passed'])]
    public function group_update($data)
    {
        $this->checkPermissions('staff', 'manage_groups');

        $model = $this->getAdminGroupById((int) $data['id']);

        return $this->getService()->updateGroup($model, $data);
    }

    /**
     * Add a staff member to a group.
     */
    #[RequiredParams(['admin_id' => 'Staff member ID was not passed', 'group_id' => 'Group ID was not passed'])]
    public function group_member_add($data): bool
    {
        $this->checkPermissions('staff', 'manage_groups');

        return $this->getService()->addAdminToGroup(
            $this->getAdminById((int) $data['admin_id']),
            $this->getAdminGroupById((int) $data['group_id']),
        );
    }

    /**
     * Remove a staff member from a group.
     */
    #[RequiredParams(['admin_id' => 'Staff member ID was not passed', 'group_id' => 'Group ID was not passed'])]
    public function group_member_remove($data): bool
    {
        $this->checkPermissions('staff', 'manage_groups');

        return $this->getService()->removeAdminFromGroup(
            $this->getAdminById((int) $data['admin_id']),
            $this->getAdminGroupById((int) $data['group_id']),
        );
    }

    /**
     * List staff members in a group.
     */
    #[RequiredParams(['group_id' => 'Group ID was not passed'])]
    public function group_member_get_list($data): array
    {
        $this->checkPermissions('staff', 'manage_groups');

        $group = $this->getAdminGroupById((int) $data['group_id']);

        return array_map(
            fn (int $adminId): array => $this->getService()->toModel_AdminApiArray($this->getAdminById($adminId)),
            $this->getService()->getAdminGroupMemberRepository()->getMemberIdsInGroup((int) $group->getId()),
        );
    }

    /**
     * List groups assigned to a staff member.
     */
    #[RequiredParams(['admin_id' => 'Staff member ID was not passed'])]
    public function admin_group_get_list($data): array
    {
        $this->checkPermissions('staff', 'manage_groups');

        $admin = $this->getAdminById((int) $data['admin_id']);

        return array_map(
            static fn (AdminGroup $group): array => $group->toApiArray(),
            $this->getService()->getAdminGroupMemberRepository()->findGroupsForAdmin((int) $admin->id),
        );
    }

    /**
     * Get paginated list of staff logins history.
     *
     * @return array
     */
    public function login_history_get_list($data)
    {
        $this->checkPermissions('staff', 'manage_settings');

        [$sql, $params] = $this->getService()->getActivityAdminHistorySearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $this->getService()->toActivityAdminHistoryRowApiArray($item);
        }

        return $pager;
    }

    /**
     * Get details of login history event.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Event ID was not passed'])]
    public function login_history_get($data)
    {
        $this->checkPermissions('staff', 'manage_settings');

        /** @var ActivityAdminHistoryRepository $repository */
        $repository = $this->getDi()['em']->getRepository(ActivityAdminHistory::class);
        $model = $repository->findOneByIdOrFail((int) $data['id']);

        return $this->getService()->toActivityAdminHistoryApiArray($model);
    }
}
