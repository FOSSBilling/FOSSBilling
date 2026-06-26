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

/**
 *Staff management.
 */

namespace Box\Mod\Staff\Api;

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

        $data['no_cron'] = true;

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
     * Update staff member.
     *
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $status - new status
     * @optional string $signature - new signature
     * @optional int $admin_group_id - new group id
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'ID was not passed'])]
    public function update($data)
    {
        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');
        $role = $model->role === 'admin' ? 'admin' : 'staff';
        $this->checkPermissions('staff', $role === 'admin' ? 'create_and_edit_admin' : 'create_and_edit_staff');

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
        $role = $model->role === 'admin' ? 'admin' : 'staff';
        $this->checkPermissions('staff', $role === 'admin' ? 'delete_admin' : 'delete_staff');

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
        $role = $model->role === 'admin' ? 'admin' : 'staff';
        $this->checkPermissions('staff', $role === 'admin' ? 'reset_admin_password' : 'reset_staff_password');

        return $this->getService()->changePassword($model, $data['password']);
    }

    /**
     * Create new staff member.
     *
     * @optional string $signature - signature of new staff member
     *
     * @return int - ID of newly created staff member
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams([
        'email' => 'Email address was not passed',
        'password' => 'Password was not passed',
        'name' => 'Name was not passed',
        'admin_group_id' => 'Group ID was not passed',
    ])]
    public function create($data)
    {
        $role = $data['role'] ?? 'staff';
        $this->checkPermissions('staff', $role === 'admin' ? 'create_and_edit_admin' : 'create_and_edit_staff');

        $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);

        $this->getDi()['validator']->isPasswordStrong($data['password']);

        return $this->getService()->create($data);
    }

    /**
     * Return staff member permissions.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'ID was not passed'])]
    public function permissions_get($data)
    {
        $this->checkPermissions('staff', 'create_and_edit_staff');

        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        return $this->getService()->getPermissions($model->id);
    }

    /**
     * Update staff member permissions.
     */
    #[RequiredParams(['id' => 'ID was not passed', 'permissions' => 'Missing "permissions" parameter'])]
    public function permissions_update($data): bool
    {
        $this->checkPermissions('staff', 'manage_settings');

        $model = $this->getDi()['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        if (!is_array($data['permissions'])) {
            throw new \FOSSBilling\InformationException('Parameter "permissions" must be an array');
        }

        $this->getService()->setPermissions($model, $data['permissions']);

        $this->getDi()['logger']->info('Changed staff member %s permissions', $model->id);

        return true;
    }

    private function getAdminGroupById(int $id): AdminGroup
    {
        $group = $this->getService()->getAdminGroupRepository()->findById($id);
        if (!$group instanceof AdminGroup) {
            throw new \FOSSBilling\Exception('Group not found');
        }

        return $group;
    }

    /**
     * Return pairs of staff member groups.
     *
     * @return array
     */
    public function group_get_pairs($data)
    {
        $this->checkPermissions('staff', 'view');

        return $this->getService()->getAdminGroupRepository()->getPairs();
    }

    /**
     * Return paginate list of staff members groups.
     *
     * @return array
     */
    public function group_get_list($data)
    {
        $this->checkPermissions('staff', 'manage_groups');

        $qb = $this->getService()->getAdminGroupRepository()->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
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

        if (isset($data['permissions']) && !is_array($data['permissions'])) {
            throw new \FOSSBilling\InformationException('Parameter "permissions" must be an array');
        }

        return $this->getService()->createGroup($data['name'], $data['permissions'] ?? []);
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
            $activity = $this->getDi()['db']->getExistingModelById('ActivityAdminHistory', $item['id'] ?? 0, sprintf('Staff activity item #%s not found', $item['id'] ?? 'unknown'));
            if ($activity) {
                $pager['list'][$key] = $this->getService()->toActivityAdminHistoryApiArray($activity);
            }
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

        $model = $this->getDi()['db']->getExistingModelById('ActivityAdminHistory', $data['id'], 'Event not found');

        return $this->getService()->toActivityAdminHistoryApiArray($model);
    }
}
