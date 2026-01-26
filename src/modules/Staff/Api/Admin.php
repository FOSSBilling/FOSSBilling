<?php

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

use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of staff members.
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['no_cron'] = true;
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $staff = $this->di['db']->getExistingModelById('Admin', $item['id'], 'Admin is not found');
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
        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

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
        if (!is_null($data['email'])) {
            $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        }

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

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
        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

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
        if ($data['password'] != $data['password_confirm']) {
            throw new \FOSSBilling\InformationException('Passwords do not match');
        }

        $this->di['validator']->isPasswordStrong($data['password']);

        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

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
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $this->di['validator']->isPasswordStrong($data['password']);

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
        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        return $this->getService()->getPermissions($model->id);
    }

    /**
     * Update staff member permissions.
     */
    #[RequiredParams(['id' => 'ID was not passed', 'permissions' => 'Missing "permissions" parameter'])]
    public function permissions_update($data): bool
    {
        $model = $this->di['db']->getExistingModelById('Admin', $data['id'], 'Staff member not found');

        $this->getService()->setPermissions($model->id, $data['permissions']);

        $this->di['logger']->info('Changed staff member %s permissions', $model->id);

        return true;
    }

    /**
     * Return pairs of staff member groups.
     *
     * @return array
     */
    public function group_get_pairs($data)
    {
        return $this->getService()->getAdminGroupPair();
    }

    /**
     * Return paginate list of staff members groups.
     *
     * @return array
     */
    public function group_get_list($data)
    {
        [$sql, $params] = $this->getService()->getAdminGroupSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('AdminGroup', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toAdminGroupApiArray($model, false, $this->getIdentity());
        }

        return $pager;
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
        return $this->getService()->createGroup($data['name']);
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
        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');

        return $this->getService()->toAdminGroupApiArray($model, true, $this->getIdentity());
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
        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');

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
        $model = $this->di['db']->getExistingModelById('AdminGroup', $data['id'], 'Group not found');

        return $this->getService()->updateGroup($model, $data);
    }

    /**
     * Get paginated list of staff logins history.
     *
     * @return array
     */
    public function login_history_get_list($data)
    {
        [$sql, $params] = $this->getService()->getActivityAdminHistorySearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $activity = $this->di['db']->getExistingModelById('ActivityAdminHistory', $item['id'], sprintf('Staff activity item #%s not found', $item['id']));
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
        $model = $this->di['db']->getExistingModelById('ActivityAdminHistory', $data['id'], 'Event not found');

        return $this->getService()->toActivityAdminHistoryApiArray($model);
    }
}
