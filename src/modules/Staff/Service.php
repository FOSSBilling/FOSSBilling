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

namespace Box\Mod\Staff;

use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Entity\AdminGroupMember;
use Box\Mod\Staff\Repository\AdminGroupMemberRepository;
use Box\Mod\Staff\Repository\AdminGroupRepository;
use Box\Mod\Support\Entity\Helpdesk;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\PaginationOptions;

class Service implements InjectionAwareInterface
{
    private array $permissionCache = [];
    private array $superAdministratorCache = [];

    private AdminGroupRepository $adminGroupRepository;
    private AdminGroupMemberRepository $adminGroupMemberRepository;

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->adminGroupRepository = $di['em']->getRepository(AdminGroup::class);
        $this->adminGroupMemberRepository = $di['em']->getRepository(AdminGroupMember::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getAdminGroupRepository(): AdminGroupRepository
    {
        return $this->adminGroupRepository;
    }

    public function getAdminGroupMemberRepository(): AdminGroupMemberRepository
    {
        return $this->adminGroupMemberRepository;
    }

    private function clearPermissionCache(): void
    {
        $this->permissionCache = [];
        $this->superAdministratorCache = [];
    }

    private function getPermissionsFromCache(int|string $memberId): ?array
    {
        $cacheKey = (string) $memberId;

        if (!array_key_exists($cacheKey, $this->permissionCache)) {
            return null;
        }

        return is_array($this->permissionCache[$cacheKey]) ? $this->permissionCache[$cacheKey] : [];
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View staff details'),
                'description' => __trans('Allows the staff member to view staff account details and listings.'),
            ],
            'create_and_edit_admin' => [
                'type' => 'bool',
                'display_name' => __trans('Create and edit administrators'),
                'description' => __trans('Allows the staff member to create and edit :type: accounts.', [':type:' => __trans('administrator')]),
            ],
            'delete_admin' => [
                'type' => 'bool',
                'display_name' => __trans('Delete administrators'),
                'description' => __trans('Allows the staff member to delete :type: accounts.', [':type:' => __trans('administrator')]),
            ],
            'reset_admin_password' => [
                'type' => 'bool',
                'display_name' => __trans('Reset administrator passwords'),
                'description' => __trans('Allows the staff member to perform password resets on :type: accounts.', [':type:' => __trans('administrator')]),
            ],
            'create_and_edit_staff' => [
                'type' => 'bool',
                'display_name' => __trans('Create and edit staff members'),
                'description' => __trans('Allows the staff member to create and edit :type: accounts.', [':type:' => __trans('staff')]),
            ],
            'delete_staff' => [
                'type' => 'bool',
                'display_name' => __trans('Delete staff members'),
                'description' => __trans('Allows the staff member to delete :type: accounts.', [':type:' => __trans('staff')]),
            ],
            'reset_staff_password' => [
                'type' => 'bool',
                'display_name' => __trans('Reset staff passwords'),
                'description' => __trans('Allows the staff member to perform password resets on :type: accounts.', [':type:' => __trans('staff')]),
            ],
            'manage_groups' => [
                'type' => 'bool',
                'display_name' => __trans('Manage groups'),
                'description' => __trans('Allows the staff member to manage staff member groups.'),
            ],
            'manage_settings' => [
                'type' => 'bool',
                'display_name' => __trans('Manage settings'),
                'description' => __trans('Allows the staff member to manage system settings.'),
            ],
        ];
    }

    public function login($email, $password, $ip): array
    {
        $event_params = [];
        $event_params['email'] = $email;
        $event_params['ip'] = $ip;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminLogin', 'params' => $event_params]);

        $model = $this->authorizeAdmin($email, $password);
        if (!$model instanceof \Model_Admin) {
            $this->di['events_manager']->fire(['event' => 'onEventAdminLoginFailed', 'params' => $event_params]);

            throw new \FOSSBilling\InformationException('Check your login details', null, 403);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminLogin', 'params' => ['id' => $model->id, 'ip' => $ip]]);

        $result = [
            'id' => $model->id,
            'email' => $model->email,
            'name' => $model->name,
            'role' => $model->role,
        ];

        $this->di['session']->regenerateId();
        $this->di['session']->set('admin', $result);

        $this->di['logger']->info(sprintf('Staff member %s logged in', $model->id));

        return $result;
    }

    public function getPairs(array $data = [])
    {
        $limit = (int) ($data['per_page'] ?? 30);

        $sql = 'SELECT id, name FROM admin WHERE 1';
        $params = [];

        if (!empty($data['search'])) {
            $sql .= ' AND (name LIKE :search OR email LIKE :search)';
            $params['search'] = '%' . $data['search'] . '%';
        }

        // Limit results for performance
        $sql .= sprintf(' ORDER BY name ASC LIMIT %u', $limit);

        return $this->di['db']->getAssoc($sql, $params);
    }

    public function setPermissions(\Model_Admin $model, array $array): bool
    {
        $caller = $this->getLoggedInAdminOrCronAdmin();

        if ($caller->id == $model->id) {
            throw new \FOSSBilling\InformationException('You cannot modify your own permissions');
        }

        if ($model->role === \Model_Admin::ROLE_ADMIN) {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_admin');
        } else {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');
        }

        if ($caller->role !== \Model_Admin::ROLE_ADMIN) {
            $callerPerms = $this->getPermissions($caller->id);
            $callerHasWildcard = !empty($callerPerms['default']['all']);

            if (!$callerHasWildcard) {
                $this->enforcePermissionCeiling($array, $callerPerms);
            }
        }

        $array = array_filter($array);

        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->update('admin')
            ->set('permissions', ':p')
            ->where('id = :id')
            ->setParameter('p', json_encode($array))
            ->setParameter('id', $model->id)
            ->executeStatement();

        return true;
    }

    private function enforcePermissionCeiling(array $submitted, array $callerPerms): void
    {
        foreach ($submitted as $module => $modulePerms) {
            if ($module === 'default') {
                if (!empty($submitted['default']['all']) && empty($callerPerms['default']['all'])) {
                    throw new \FOSSBilling\InformationException('You cannot grant wildcard access that you do not have');
                }

                continue;
            }

            if (!is_array($modulePerms)) {
                continue;
            }

            $hasGrantedPermissions = false;
            foreach ($modulePerms as $value) {
                if (!empty($value)) {
                    $hasGrantedPermissions = true;

                    break;
                }
            }

            if (!$hasGrantedPermissions) {
                continue;
            }

            if (!isset($callerPerms[$module])) {
                throw new \FOSSBilling\InformationException('You cannot grant permissions for a module you do not have access to');
            }

            foreach ($modulePerms as $key => $value) {
                if (empty($value)) {
                    continue;
                }

                if (!isset($callerPerms[$module][$key]) || !$callerPerms[$module][$key]) {
                    throw new \FOSSBilling\InformationException('You cannot grant a permission that you do not have');
                }
            }
        }
    }

    public function getPermissions($member_id): array
    {
        $cachedPermissions = $this->getPermissionsFromCache($member_id);

        if (!is_null($cachedPermissions)) {
            return $cachedPermissions;
        }

        $permissions = $this->adminGroupMemberRepository->getPermissionsForAdmin((int) $member_id);

        $this->permissionCache[(string) $member_id] = $permissions;

        return $permissions;
    }

    private function isSuperAdministrator(int|string $memberId): bool
    {
        $cacheKey = (string) $memberId;

        if (!array_key_exists($cacheKey, $this->superAdministratorCache)) {
            $this->superAdministratorCache[$cacheKey] = $this->adminGroupMemberRepository->adminBelongsToSystemGroup((int) $memberId, AdminGroup::SYSTEM_SUPER_ADMIN);
        }

        return $this->superAdministratorCache[$cacheKey];
    }

    /**
     * Determines if a staff member has the required permissions.
     *
     * @param \Model_Admin|null $member     The model for the staff member to check. If you pass null, FOSSBilling will automatically get the currently authenticated staff member.
     * @param string            $module     what module to check permission for
     * @param string|null       $key        the permission key for the associated module
     * @param mixed             $constraint if the permission key allows for multiple options, specify the one you want to use as a constraint here
     */
    public function hasPermission(?\Model_Admin $member, string $module, ?string $key = null, mixed $constraint = null): bool
    {
        $alwaysAllowed = ['index', 'dashboard', 'profile'];

        if (is_null($member)) {
            $member = $this->getLoggedInAdminOrCronAdmin();
        }

        if ($member->role == \Model_Admin::ROLE_CRON || in_array($module, $alwaysAllowed)) {
            return true;
        }

        if ($this->isSuperAdministrator($member->id)) {
            return true;
        }

        $extensionService = $this->di['mod_service']('Extension');
        $modulePermissions = $extensionService->getSpecificModulePermissions($module);
        $permissions = $this->getPermissions($member->id);
        $defaultPermissions = $permissions['default'] ?? [];
        $hasWildcardAccess = is_array($defaultPermissions) && (bool) ($defaultPermissions['all'] ?? false);

        $canAlwaysAccess = $modulePermissions['can_always_access'] ?? false;

        if (!$canAlwaysAccess) {
            // They have no permissions or don't have any access to that module
            if (
                empty($permissions)
                || (
                    !array_key_exists($module, $permissions)
                    && !$hasWildcardAccess
                )
                || (
                    array_key_exists($module, $permissions)
                    && (
                        !is_array($permissions[$module])
                        || !($permissions[$module]['access'] ?? false)
                    )
                )
            ) {
                return false;
            }
        }

        if (!is_null($key)) {
            if (!array_key_exists($module, $permissions) && $hasWildcardAccess) {
                return true;
            }

            $modulePermissions = $permissions[$module] ?? [];

            if (!is_array($modulePermissions) || !array_key_exists($key, $modulePermissions)) {
                return false;
            }

            if (!is_null($constraint)) {
                return $modulePermissions[$key] === $constraint;
            }

            return (bool) $modulePermissions[$key];
        }

        return true;
    }

    /**
     * Acts as an alias to `hasPermission`, but it'll also throw an exception stating the staff member doesn't have permission if they don't.
     *
     * @param string            $module     what module to check permission for
     * @param string|null       $key        the permission key for the associated module
     * @param mixed             $constraint if the permission key allows for multiple options, specify the one you want to use as a constraint here
     * @param \Model_Admin|null $member     the staff member to check permissions for, or null to use the currently logged-in staff member
     */
    public function checkPermissionsAndThrowException(string $module, ?string $key = null, mixed $constraint = null, ?\Model_Admin $member = null): void
    {
        if (!$this->hasPermission($member, $module, $key, $constraint)) {
            $requiredPermission = is_null($key) ? $module : "{$module}.{$key}";

            throw new \FOSSBilling\InformationException("You need the \"{$requiredPermission}\" permission to perform this action", [], 403);
        }
    }

    public static function onAfterClientOrderCreate(\Box_Event $event): void
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        try {
            $orderModel = $di['db']->load('ClientOrder', $params['id']);
            $orderTicketService = $di['mod_service']('order');
            $order = $orderTicketService->toApiArray($orderModel, true);

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_client_order';
            $email['order'] = $order;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff order notification email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterClientOpenTicket(\Box_Event $event): void
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById((int) $params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $helpdeskModel = isset($ticketModel->support_helpdesk_id) && $ticketModel->support_helpdesk_id ? $di['em']->getRepository(Helpdesk::class)->find((int) $ticketModel->support_helpdesk_id) : null;
            $emailService = $di['mod_service']('email');
            if ($helpdeskModel instanceof Helpdesk && !empty($helpdeskModel->getEmail())) {
                $email = [];
                $email['to'] = $helpdeskModel->getEmail();
                $email['code'] = 'mod_support_helpdesk_ticket_open';
                $email['ticket'] = $ticket;
                $emailService->sendTemplate($email);

                return;
            }

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_open';
            $email['ticket'] = $ticket;
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff ticket notification email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterClientReplyTicket(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById((int) $params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_reply';
            $email['ticket'] = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff ticket reply notification email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterClientCloseTicket(\Box_Event $event): void
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById((int) $params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_close';
            $email['ticket'] = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff ticket close notification email', ['exception' => $exc->getMessage()]);
        }
    }

    public static function onAfterClientSignUp(\Box_Event $event): bool
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $clientService = $di['mod_service']('client');

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_client_signup';
            $email['c'] = $clientService->get(['id' => $params['id']]);
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff client signup notification email', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    public function getList($data)
    {
        $data['no_cron'] = true;

        [$query, $params] = $this->getSearchQuery($data);

        return $this->di['pager']->getPaginatedResultSet($query, $params, PaginationOptions::fromArray($data));
    }

    public function getSearchQuery($data): array
    {
        $query = 'SELECT * FROM admin';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;
        $admin_group_id = $data['admin_group_id'] ?? null;
        $no_cron = (bool) ($data['no_cron'] ?? false);

        $where = [];
        $bindings = [];

        if ($id !== null && $id !== '') {
            $where[] = 'id = :id';
            $bindings[':id'] = (int) $id;
        }

        if ($search) {
            $search = "%$search%";
            $where[] = '(name LIKE :name OR email LIKE :email )';
            $bindings[':name'] = $search;
            $bindings[':email'] = $search;
        }

        if ($status) {
            $where[] = 'status = :status';
            $bindings[':status'] = $status;
        }

        if ($admin_group_id !== null && $admin_group_id !== '') {
            $where[] = 'admin_group_id = :admin_group_id';
            $bindings[':admin_group_id'] = (int) $admin_group_id;
        }

        if ($no_cron) {
            $where[] = 'role != :role';
            $bindings[':role'] = \Model_Admin::ROLE_CRON;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY `admin_group_id` ASC, id ASC';

        return [$query, $bindings];
    }

    /**
     * @return \Model_Admin
     */
    public function getCronAdmin()
    {
        $cron = $this->di['db']->findOne('Admin', 'role = :role', [':role' => \Model_Admin::ROLE_CRON]);
        if ($cron instanceof \Model_Admin) {
            return $cron;
        }

        $cronEmail = $this->di['tools']->generatePassword() . '@' . $this->di['tools']->generatePassword() . '.com';
        $cronEmail = filter_var($cronEmail, FILTER_SANITIZE_EMAIL);

        $cronPass = $this->di['tools']->generatePassword(256, 4);

        $cron = $this->di['db']->dispense('Admin');
        $cron->role = \Model_Admin::ROLE_CRON;
        $cron->admin_group_id = 1;
        $cron->email = $cronEmail;
        $cron->pass = $this->di['password']->hashIt($cronPass);
        $cron->name = 'System Cron Job';
        $cron->signature = '';
        $cron->protected = 1;
        $cron->status = 'active';
        $cron->created_at = date('Y-m-d H:i:s');
        $cron->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cron);

        return $cron;
    }

    public function toModel_AdminApiArray(\Model_Admin $model, $deep = false): array
    {
        $data = [
            'id' => $model->id,
            'role' => $model->role,
            'admin_group_id' => $model->admin_group_id,
            'email' => $model->email,
            'name' => $model->name,
            'status' => $model->status,
            'signature' => $model->signature,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];

        $data['protected'] = $model->protected;

        $adminGroupModel = $this->di['db']->load('AdminGroup', $model->admin_group_id);
        $data['group']['id'] = $adminGroupModel->id;
        $data['group']['name'] = $adminGroupModel->name;

        return $data;
    }

    public function update(\Model_Admin $model, $data): bool
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffUpdate', 'params' => ['id' => $model->id]]);

        if ($model->role === 'admin') {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_admin');
        } else {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');
        }

        $previousStatus = $model->status;

        $model->email = $data['email'] ?? $model->email;
        $model->admin_group_id = $data['admin_group_id'] ?? $model->admin_group_id;
        $model->name = $data['name'] ?? $model->name;
        $model->status = $data['status'] ?? $model->status;
        if ($model->status === \Model_Admin::STATUS_INACTIVE) {
            $model->api_token = null;
        }
        $model->signature = $data['signature'] ?? $model->signature;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        if ($model->status !== \Model_Admin::STATUS_ACTIVE && $previousStatus === \Model_Admin::STATUS_ACTIVE) {
            $profileService = $this->di['mod_service']('profile');
            $profileService->invalidateSessions('admin', (int) $model->id);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffUpdate', 'params' => ['id' => $model->id]]);

        $this->di['logger']->info('Updated staff member %s details', $model->id);

        return true;
    }

    public function delete(\Model_Admin $model): bool
    {
        if ($model->protected) {
            throw new \FOSSBilling\InformationException('This administrator account is protected and cannot be removed');
        }

        if ($model->role === 'admin') {
            $this->checkPermissionsAndThrowException('staff', 'delete_admin');
        } else {
            $this->checkPermissionsAndThrowException('staff', 'delete_staff');
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffDelete', 'params' => ['id' => $model->id]]);

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Deleted staff member %s', $id);

        return true;
    }

    public function changePassword(\Model_Admin $model, $password): bool
    {
        if ($model->role === 'admin') {
            $this->checkPermissionsAndThrowException('staff', 'reset_admin_password');
        } else {
            $this->checkPermissionsAndThrowException('staff', 'reset_staff_password');
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffPasswordChange', 'params' => ['id' => $model->id]]);

        $model->pass = $this->di['password']->hashIt($password);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $profileService = $this->di['mod_service']('profile');
        $profileService->invalidateSessions('admin', (int) $model->id);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffPasswordChange', 'params' => ['id' => $model->id]]);

        $this->di['logger']->info('Changed staff member %s password', $model->id);

        return true;
    }

    public function create(array $data): int
    {
        // TODO: When it becomes possible to create other super admins, add a check for that here,
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $signature = $data['signature'] ?? null;

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffCreate', 'params' => $data]);

        $model = $this->di['db']->dispense('Admin');
        $model->role = \Model_Admin::ROLE_STAFF;
        $model->admin_group_id = $data['admin_group_id'];
        $model->email = $data['email'];
        $model->pass = $this->di['password']->hashIt($data['password']);
        $model->name = $data['name'];
        $model->status = $model->getStatus($data['status']);
        $model->signature = $signature;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        try {
            $newId = $this->di['db']->store($model);
        } catch (\RedBeanPHP\RedException) {
            throw new \FOSSBilling\InformationException('Staff member with email :email is already registered.', [':email' => $data['email']], 788954);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created new staff member %s', $newId);

        return (int) $newId;
    }

    public function createGroup(string $name, array $permissions = []): int
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        $group = (new AdminGroup())
            ->setName($name)
            ->setPermissions($permissions);

        $this->di['em']->persist($group);
        $this->di['em']->flush();

        $this->di['logger']->info('Created new staff group %s', $group->getId());

        return (int) $group->getId();
    }

    public function deleteGroup(AdminGroup $model): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        $id = $model->getId();
        if ($model->isProtected()) {
            throw new \FOSSBilling\InformationException('Protected staff groups cannot be removed');
        }

        if ($this->adminGroupMemberRepository->countMembersInGroup((int) $id) > 0) {
            throw new \FOSSBilling\InformationException('Cannot remove group which has staff members');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->clearPermissionCache();

        $this->di['logger']->info('Deleted staff group %s', $id);

        return true;
    }

    public function updateGroup(AdminGroup $model, array $data): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        if ($model->isProtected()) {
            throw new \FOSSBilling\InformationException('Protected staff groups cannot be modified');
        }

        if (isset($data['name'])) {
            $model->setName($data['name']);
        }

        if (array_key_exists('permissions', $data)) {
            if (!is_array($data['permissions'])) {
                throw new \FOSSBilling\InformationException('Parameter "permissions" must be an array');
            }

            $model->setPermissions($data['permissions']);
        }

        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->clearPermissionCache();

        $this->di['logger']->info('Updated staff group %s', $model->getId());

        return true;
    }

    public function getActivityAdminHistorySearchQuery($data): array
    {
        $sql = 'SELECT m.*, a.email, a.name
                FROM activity_admin_history as m
                LEFT JOIN admin as a on m.admin_id = a.id
                ';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $admin_id = $data['admin_id'] ?? null;
        $ip = $data['ip'] ?? null;
        $date_from = $data['date_from'] ?? null;
        $date_to = $data['date_to'] ?? null;

        $where = [];
        $params = [];

        if ($id !== null && $id !== '') {
            $where[] = 'm.id = :event_id';
            $params['event_id'] = (int) $id;
        }

        if ($search) {
            $where[] = '(a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email)';
            $params['name'] = "%$search%";
            $params['id'] = "%$search%";
            $params['email'] = "%$search%";
        }

        if ($admin_id) {
            $where[] = 'm.admin_id = :admin_id';
            $params['admin_id'] = $admin_id;
        }

        if ($ip !== null && $ip !== '') {
            $where[] = 'm.ip LIKE :ip';
            $params['ip'] = '%' . $ip . '%';
        }

        if ($date_from !== null && $date_from !== '') {
            $where[] = 'm.created_at >= :date_from';
            $params['date_from'] = date('Y-m-d 00:00:00', strtotime((string) $date_from));
        }

        if ($date_to !== null && $date_to !== '') {
            $where[] = 'm.created_at <= :date_to';
            $params['date_to'] = date('Y-m-d 23:59:59', strtotime((string) $date_to));
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.id DESC';

        return [$sql, $params];
    }

    public function toActivityAdminHistoryApiArray(\Model_ActivityAdminHistory $model, $deep = false): array
    {
        $result = [
            'id' => $model->id,
            'ip' => $model->ip,
            'created_at' => $model->created_at,
        ];
        if ($model->admin_id) {
            $adminModel = $this->di['db']->load('Admin', $model->admin_id);
            if ($adminModel instanceof \Model_Admin && $adminModel->id) {
                $result['staff']['id'] = $adminModel->id;
                $result['staff']['name'] = $adminModel->name;
                $result['staff']['email'] = $adminModel->email;
            }
        }

        return $result;
    }

    public function authorizeAdmin($email, $plainTextPassword)
    {
        $model = $this->di['db']->findOne('Admin', 'email = ? AND status = ? AND role != ?', [$email, \Model_Admin::STATUS_ACTIVE, \Model_Admin::ROLE_CRON]);

        return $this->di['auth']->authorizeUser($model, $plainTextPassword);
    }

    private function getLoggedInAdminOrCronAdmin(): \Model_Admin
    {
        if (isset($this->di['auth']) && !$this->di['auth']->isAdminLoggedIn()) {
            if (isset($this->di['is_cron']) && $this->di['is_cron'] === true) {
                return $this->getCronAdmin();
            }
        }

        return $this->di['loggedin_admin'];
    }
}
