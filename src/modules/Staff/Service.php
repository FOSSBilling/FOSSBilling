<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff;

use Box\Mod\Activity\Entity\ActivityAdminHistory;
use Box\Mod\Order\Entity\Order as OrderEntity;
use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Staff\Entity\AdminGroup;
use Box\Mod\Staff\Entity\AdminGroupMember;
use Box\Mod\Staff\Repository\AdminGroupMemberRepository;
use Box\Mod\Staff\Repository\AdminGroupRepository;
use Box\Mod\Staff\Repository\AdminRepository;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use FOSSBilling\i18n;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Tools;

class Service implements InjectionAwareInterface
{
    private array $permissionCache = [];

    private AdminRepository $adminRepository;
    private AdminGroupRepository $adminGroupRepository;
    private AdminGroupMemberRepository $adminGroupMemberRepository;

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        $this->adminRepository = $di['em']->getRepository(Admin::class);
        $this->adminGroupRepository = $di['em']->getRepository(AdminGroup::class);
        $this->adminGroupMemberRepository = $di['em']->getRepository(AdminGroupMember::class);
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getAdminRepository(): AdminRepository
    {
        return $this->adminRepository;
    }

    public function getAdminGroupRepository(): AdminGroupRepository
    {
        return $this->adminGroupRepository;
    }

    public function getAdminGroupMemberRepository(): AdminGroupMemberRepository
    {
        return $this->adminGroupMemberRepository;
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
        if (!$model instanceof Admin) {
            $this->di['events_manager']->fire(['event' => 'onEventAdminLoginFailed', 'params' => $event_params]);

            throw new \FOSSBilling\InformationException('Check your login details', null, 403);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminLogin', 'params' => ['id' => $model->getId(), 'ip' => $ip]]);

        $result = [
            'id' => $model->getId(),
            'email' => $model->getEmail(),
            'name' => $model->getName(),
        ];

        $this->di['session']->regenerateId();
        $this->di['session']->set('admin', $result);

        $this->di['logger']->info(sprintf('Staff member %s logged in', $model->getId()));

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

        $sql .= sprintf(' ORDER BY name ASC LIMIT %u', $limit);

        return $this->di['dbal']->fetchAllKeyValue($sql, $params);
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

    /**
     * Determines if a staff member has the required permissions.
     *
     * @param Admin|null $member     The model for the staff member to check. If you pass null, FOSSBilling will automatically get the currently authenticated staff member.
     * @param string            $module     what module to check permission for
     * @param string|null       $key        the permission key for the associated module
     * @param mixed             $constraint if the permission key allows for multiple options, specify the one you want to use as a constraint here
     */
    public function hasPermission(?Admin $member, string $module, ?string $key = null, mixed $constraint = null): bool
    {
        $alwaysAllowed = ['index', 'dashboard', 'profile'];

        if (is_null($member)) {
            $member = $this->getLoggedInAdminOrCronAdmin();
        }

        if ($member->isCron() || in_array($module, $alwaysAllowed)) {
            return true;
        }

        if ($this->isSuperAdministrator($member->getId())) {
            return true;
        }

        $extensionService = $this->di['mod_service']('Extension');
        $modulePermissions = $extensionService->getSpecificModulePermissions($module);
        $permissions = $this->getPermissions($member->getId());
        $canAlwaysAccess = $modulePermissions['can_always_access'] ?? false;

        if (!$canAlwaysAccess) {
            // They have no permissions or don't have any access to that module
            if (
                empty($permissions)
                || !array_key_exists($module, $permissions)
                || !is_array($permissions[$module])
                || !($permissions[$module]['access'] ?? false)
            ) {
                return false;
            }
        }

        if (!is_null($key)) {
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
     * @param Admin|null $member     the staff member to check permissions for, or null to use the currently logged-in staff member
     */
    public function checkPermissionsAndThrowException(string $module, ?string $key = null, mixed $constraint = null, ?Admin $member = null): void
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
            $orderModel = $di['em']->getRepository(OrderEntity::class)->find($params['id']);
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
            $ticket = self::getTicketEmailVars($di, $ticketModel);

            $helpdeskId = $ticketModel->getSupportHelpdeskId();
            $helpdeskModel = $helpdeskId !== null ? $di['em']->getRepository(Helpdesk::class)->find($helpdeskId) : null;
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
            $ticket = self::getTicketEmailVars($di, $ticketModel);

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
            $ticket = self::getTicketEmailVars($di, $ticketModel);
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

    private static function getTicketEmailVars(\Pimple\Container $di, SupportTicket $ticketModel): array
    {
        $ticket = $di['mod_service']('support')->toApiArray($ticketModel, true);
        $ticket['priority'] = $ticketModel->getPriority();

        $clientId = $ticketModel->getClientId();
        if ($clientId !== null) {
            $clientService = $di['mod_service']('client');

            try {
                $client = $clientService->get(['id' => $clientId]);
            } catch (\FOSSBilling\InformationException) {
                return $ticket;
            }
            $ticket['client'] = $clientService->toApiArray($client);
        }

        return $ticket;
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
            $client = $clientService->get(['id' => $params['id']]);
            $email['c'] = $clientService->toApiArray($client);
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            $di['logger']->setChannel('email')->error('Failed to send staff client signup notification email', ['exception' => $exc->getMessage()]);
        }

        return true;
    }

    public function getList($data)
    {
        $qb = $this->getSearchQueryBuilder($data);

        return $this->di['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
    }

    public function getSearchQueryBuilder($data)
    {
        return $this->adminRepository->getSearchQueryBuilder($data);
    }

    public function getSearchQuery($data): array
    {
        $query = 'SELECT * FROM admin';

        $id = $data['id'] ?? null;
        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;
        $no_cron = Tools::normalizeBoolean($data['no_cron'] ?? null, true);

        $where = [];
        $bindings = [];

        if ($id !== null && $id !== '') {
            $where[] = 'id = :id';
            $bindings['id'] = (int) $id;
        }

        if ($search) {
            $search = "%$search%";
            $where[] = '(name LIKE :name OR email LIKE :email )';
            $bindings['name'] = $search;
            $bindings['email'] = $search;
        }

        if ($status) {
            $where[] = 'status = :status';
            $bindings['status'] = $status;
        }

        if ($no_cron) {
            $where[] = '(system_name IS NULL OR system_name != :system_name)';
            $bindings['system_name'] = Admin::SYSTEM_CRON;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= ' ORDER BY id ASC';

        return [$query, $bindings];
    }

    /**
     * @return Admin|Admin
     */
    public function getCronAdmin()
    {
        $cron = $this->adminRepository->findCronAdmin();
        if ($cron instanceof Admin) {
            return $cron;
        }

        $cronEmail = $this->di['tools']->generatePassword() . '@' . $this->di['tools']->generatePassword() . '.com';
        $cronEmail = filter_var($cronEmail, FILTER_SANITIZE_EMAIL);

        $cronPass = $this->di['tools']->generatePassword(256, 4);

        $cron = new Admin();
        $cron->setSystemName(Admin::SYSTEM_CRON);
        $cron->setEmail($cronEmail);
        $cron->setPass($this->di['password']->hashIt($cronPass));
        $cron->setName('System Cron Job');
        $cron->setSignature('');
        $cron->setStatus('active');
        $this->di['em']->persist($cron);
        $this->di['em']->flush();

        return $cron;
    }

    public function toAdminApiArray(Admin $model): array
    {
        $admin = $this->adminRepository->find((int) $model->getId());

        if (!$admin instanceof Admin) {
            return [
                'id' => $model->getId(),
                'email' => $model->getEmail(),
                'name' => $model->getName(),
                'system_name' => $model->getSystemName(),
                'status' => $model->getStatus(),
                'signature' => $model->getSignature(),
                'timezone' => $model->getTimezone(),
                'created_at' => $model->getCreatedAt(),
                'updated_at' => $model->getUpdatedAt(),
                'groups' => [],
            ];
        }

        $data = $admin->toApiArray();

        $data['groups'] = array_map(
            static fn (AdminGroup $group): array => $group->toApiArray(),
            $this->adminGroupMemberRepository->findGroupsForAdmin((int) $admin->getId()),
        );

        return $data;
    }

    public function update(Admin $model, $data): bool
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffUpdate', 'params' => ['id' => $model->getId()]]);

        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $previousStatus = $model->getStatus();
        $newStatus = $data['status'] ?? $model->getStatus();

        if ((int) $this->di['loggedin_admin']->getId() === (int) $model->getId() && $previousStatus === Admin::STATUS_ACTIVE && $newStatus !== Admin::STATUS_ACTIVE) {
            throw new \FOSSBilling\InformationException('You cannot deactivate your own staff account');
        }

        $this->assertCanManageAdmin($model);

        if ($previousStatus === Admin::STATUS_ACTIVE && $newStatus !== Admin::STATUS_ACTIVE) {
            $this->assertCanRemoveActiveSuperAdministrator($model);
        }

        $admin = $this->adminRepository->find((int) $model->getId());
        if (!$admin instanceof Admin) {
            throw new \FOSSBilling\InformationException('Staff member not found');
        }

        $admin->setEmail($data['email'] ?? $admin->getEmail());
        $admin->setName($data['name'] ?? $admin->getName());
        $admin->setStatus($newStatus);
        if ($admin->getStatus() === Admin::STATUS_INACTIVE) {
            $admin->setApiToken(null);
        }
        $admin->setSignature($data['signature'] ?? $admin->getSignature());
        if (array_key_exists('timezone', $data)) {
            $admin->setTimezone(i18n::validateTimezone($data['timezone']));
        }
        $this->di['em']->persist($admin);
        $this->di['em']->flush();

        if ($admin->getStatus() !== Admin::STATUS_ACTIVE && $previousStatus === Admin::STATUS_ACTIVE) {
            $profileService = $this->di['mod_service']('profile');
            $profileService->invalidateSessions('admin', (int) $admin->getId());
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffUpdate', 'params' => ['id' => $admin->getId()]]);

        $this->di['logger']->info('Updated staff member #%s "%s" details; status is "%s"', $admin->getId(), $admin->getName(), $admin->getStatus());

        return true;
    }

    public function delete(Admin $model): bool
    {
        if ($model->isCron()) {
            throw new \FOSSBilling\InformationException('The cron administrator account cannot be removed');
        }

        $this->checkPermissionsAndThrowException('staff', 'delete_staff');
        $this->assertCanManageAdmin($model);

        $this->assertCanRemoveActiveSuperAdministrator($model);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffDelete', 'params' => ['id' => $model->getId()]]);

        $id = (int) $model->getId();
        $name = $model->getName();
        $this->adminGroupMemberRepository->deleteMembershipsForAdmin($id);

        $admin = $this->adminRepository->find($id);
        if ($admin instanceof Admin) {
            $this->di['em']->remove($admin);
            $this->di['em']->flush();
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Deleted staff member #%s "%s"', $id, $name);

        return true;
    }

    public function changePassword(Admin $model, $password): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'reset_staff_password');
        $this->assertCanManageAdmin($model);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffPasswordChange', 'params' => ['id' => $model->getId()]]);

        $admin = $this->adminRepository->find((int) $model->getId());
        if (!$admin instanceof Admin) {
            throw new \FOSSBilling\InformationException('Staff member not found');
        }

        $admin->setPass($this->di['password']->hashIt($password));
        $this->di['em']->persist($admin);
        $this->di['em']->flush();

        $profileService = $this->di['mod_service']('profile');
        $profileService->invalidateSessions('admin', (int) $model->getId());

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffPasswordChange', 'params' => ['id' => $model->getId()]]);

        $this->di['logger']->info('Changed password for staff member #%s "%s"', $model->getId(), $model->getName());

        return true;
    }

    public function create(array $data): int
    {
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $signature = $data['signature'] ?? null;
        $groupId = (int) ($data['group_id'] ?? 0);
        if ($groupId <= 0) {
            throw new \FOSSBilling\InformationException('Group ID was not passed');
        }

        $group = $this->adminGroupRepository->findById($groupId);
        if (!$group instanceof AdminGroup) {
            throw new \FOSSBilling\InformationException('Group not found');
        }

        $this->assertCanManageGroup($group);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffCreate', 'params' => $data]);

        try {
            $admin = (new Admin())
                ->setEmail($data['email'])
                ->setPass($this->di['password']->hashIt($data['password']))
                ->setName($data['name'])
                ->setStatus($data['status'] ?? Admin::STATUS_ACTIVE)
                ->setSignature($signature)
                ->setTimezone(i18n::validateTimezone($data['timezone'] ?? null));

            $this->di['em']->persist($admin);
            $this->di['em']->flush();

            $newId = (int) $admin->getId();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            throw new \FOSSBilling\InformationException('Staff member with email :email is already registered.', [':email' => $data['email']], 788954);
        }

        $this->di['em']->persist(new AdminGroupMember($newId, $group));
        $this->di['em']->flush();

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffCreate', 'params' => ['id' => $newId]]);

        $this->di['logger']->info('Created staff member #%s "%s" in group #%s "%s"', $newId, $admin->getName(), $groupId, $group->getName());

        return $newId;
    }

    public function createGroup(string $name, ?AdminGroup $parent = null): int
    {
        if (!$this->isSuperAdministrator()) {
            throw new \FOSSBilling\InformationException('Only super administrators can manage staff groups', [], 403);
        }

        $parent ??= $this->adminGroupRepository->findSuperAdministratorGroup();

        $group = (new AdminGroup())
            ->setName($name)
            ->setParent($parent);

        $this->di['em']->persist($group);
        $this->di['em']->flush();

        $this->di['logger']->info('Created staff group #%s "%s" under parent group #%s "%s"', $group->getId(), $group->getName(), $parent->getId(), $parent->getName());

        return (int) $group->getId();
    }

    public function deleteGroup(AdminGroup $model): bool
    {
        if (!$this->isSuperAdministrator()) {
            throw new \FOSSBilling\InformationException('Only super administrators can manage staff groups', [], 403);
        }

        $id = $model->getId();
        $name = $model->getName();
        if ($model->isProtected()) {
            throw new \FOSSBilling\InformationException('Protected staff groups cannot be removed');
        }

        if ($this->adminGroupMemberRepository->countMembersInGroup((int) $id) > 0) {
            throw new \FOSSBilling\InformationException('Cannot remove group which has staff members');
        }

        if ($this->di['mod_service']('email')->getTemplateGroupRepository()->countTemplatesUsingGroup((int) $id) > 0) {
            throw new \FOSSBilling\InformationException('Cannot remove group which is used to restrict email templates');
        }

        if ($this->adminGroupRepository->getDescendantIdsForGroups([(int) $id]) !== []) {
            throw new \FOSSBilling\InformationException('Cannot remove group which has child groups');
        }

        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->permissionCache = [];

        $this->di['logger']->info('Deleted staff group #%s "%s"', $id, $name);

        return true;
    }

    public function updateGroup(AdminGroup $model, array $data): bool
    {
        if (!$this->isSuperAdministrator()) {
            throw new \FOSSBilling\InformationException('Only super administrators can manage staff groups', [], 403);
        }

        if ($model->isProtected()) {
            throw new \FOSSBilling\InformationException('Protected staff groups cannot be modified');
        }

        $parentChanged = array_key_exists('parent_id', $data);
        $permissionsChanged = array_key_exists('permissions', $data);

        if (isset($data['name'])) {
            $model->setName($data['name']);
        }

        if (array_key_exists('parent_id', $data)) {
            if (empty($data['parent_id'])) {
                throw new \FOSSBilling\InformationException('Staff groups must have a parent group');
            }

            $parent = $this->adminGroupRepository->findById((int) $data['parent_id']);
            if (!$parent instanceof AdminGroup) {
                throw new \FOSSBilling\InformationException('Parent group not found');
            }

            if ((int) $parent->getId() === (int) $model->getId()) {
                throw new \FOSSBilling\InformationException('A group cannot be its own parent');
            }

            if ($this->adminGroupRepository->isDescendantOf((int) $parent->getId(), (int) $model->getId())) {
                throw new \FOSSBilling\InformationException('A group cannot use one of its subgroups as parent');
            }

            $model->setParent($parent);
        }

        if (array_key_exists('permissions', $data)) {
            if (!is_array($data['permissions'])) {
                throw new \FOSSBilling\InformationException('Parameter "permissions" must be an array');
            }

            unset($data['permissions']['_submitted']);
            $model->setPermissions($data['permissions']);
        }

        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->permissionCache = [];

        $this->di['logger']->info(
            'Updated staff group #%s "%s"; parent changed: %s; permissions changed: %s',
            $model->getId(),
            $model->getName(),
            $parentChanged ? 'yes' : 'no',
            $permissionsChanged ? 'yes' : 'no',
        );

        return true;
    }

    public function addAdminToGroup(Admin $admin, AdminGroup $group): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');
        $this->assertCanManageAdmin($admin);
        $this->assertCanManageGroup($group);

        $adminId = (int) $admin->getId();
        $groupId = (int) $group->getId();
        if ($this->adminGroupMemberRepository->findMembership($adminId, $groupId) instanceof AdminGroupMember) {
            return true;
        }

        $this->di['em']->persist(new AdminGroupMember($adminId, $group));
        $this->di['em']->flush();
        $this->permissionCache = [];

        $this->di['logger']->info('Added staff member #%s "%s" to group #%s "%s"', $adminId, $admin->getName(), $groupId, $group->getName());

        return true;
    }

    public function removeAdminFromGroup(Admin $admin, AdminGroup $group): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');
        $this->assertCanManageAdmin($admin);
        $this->assertCanManageGroup($group);

        $adminId = (int) $admin->getId();
        $groupId = (int) $group->getId();
        $membership = $this->adminGroupMemberRepository->findMembership($adminId, $groupId);
        if (!$membership instanceof AdminGroupMember) {
            return true;
        }

        if ($group->isSuperAdministrator()) {
            $this->assertCanRemoveActiveSuperAdministrator($admin);
        }

        $this->di['em']->remove($membership);
        $this->di['em']->flush();
        $this->permissionCache = [];

        $this->di['logger']->info('Removed staff member #%s "%s" from group #%s "%s"', $adminId, $admin->getName(), $groupId, $group->getName());

        return true;
    }

    public function isSuperAdministrator(int|string|null $memberId = null): bool
    {
        $memberId ??= $this->di['loggedin_admin']->getId();

        return $this->adminGroupMemberRepository->adminBelongsToSystemGroup((int) $memberId, AdminGroup::SYSTEM_SUPER_ADMIN);
    }

    private function actorBypassesHierarchy(Admin $actor): bool
    {
        return $actor->isCron() || $this->isSuperAdministrator($actor->getId());
    }

    private function assertCanManageAdmin(Admin $target): void
    {
        $actor = $this->di['loggedin_admin'];
        if ($this->actorBypassesHierarchy($actor)) {
            return;
        }

        if ((int) $actor->getId() === (int) $target->getId()) {
            throw new \FOSSBilling\InformationException('You cannot manage your own staff account here');
        }

        if ($target->isCron()) {
            throw new \FOSSBilling\InformationException('You can only manage staff accounts in lower groups');
        }

        $targetGroupIds = $this->adminGroupMemberRepository->getGroupIdsForAdmin((int) $target->getId());
        if ($targetGroupIds === []) {
            throw new \FOSSBilling\InformationException('You can only manage staff accounts in lower groups');
        }

        if (array_diff($targetGroupIds, $this->adminGroupRepository->getDescendantIdsForGroups($this->adminGroupMemberRepository->getGroupIdsForAdmin((int) $actor->getId()))) !== []) {
            throw new \FOSSBilling\InformationException('You can only manage staff accounts in lower groups');
        }
    }

    private function assertCanManageGroup(AdminGroup $group): void
    {
        $actor = $this->di['loggedin_admin'];
        if ($this->actorBypassesHierarchy($actor)) {
            return;
        }

        if (!in_array((int) $group->getId(), $this->adminGroupRepository->getDescendantIdsForGroups($this->adminGroupMemberRepository->getGroupIdsForAdmin((int) $actor->getId())), true)) {
            throw new \FOSSBilling\InformationException('You can only manage lower staff groups');
        }
    }

    private function assertCanRemoveActiveSuperAdministrator(Admin $admin): void
    {
        if ($admin->getStatus() !== Admin::STATUS_ACTIVE) {
            return;
        }

        if (!$this->adminGroupMemberRepository->adminBelongsToSystemGroup((int) $admin->getId(), AdminGroup::SYSTEM_SUPER_ADMIN)) {
            return;
        }

        if ($this->adminGroupMemberRepository->countActiveMembersInSystemGroup(AdminGroup::SYSTEM_SUPER_ADMIN) <= 1) {
            throw new \FOSSBilling\InformationException('Cannot remove the last active super administrator');
        }
    }

    public function getActivityAdminHistorySearchQuery($data): array
    {
        $sql = 'SELECT m.*, a.id AS staff_id, a.email, a.name
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

    public function toActivityAdminHistoryRowApiArray(array $row): array
    {
        $result = [
            'id' => (int) $row['id'],
            'ip' => $row['ip'],
            'created_at' => $row['created_at'],
        ];

        if ($row['staff_id'] !== null) {
            $result['staff'] = [
                'id' => (int) $row['staff_id'],
                'name' => $row['name'],
                'email' => $row['email'],
            ];
        }

        return $result;
    }

    public function toActivityAdminHistoryApiArray(ActivityAdminHistory $model): array
    {
        $result = [
            'id' => $model->getId(),
            'ip' => $model->getIp(),
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
        if ($model->getAdminId()) {
            $admin = $this->adminRepository->find((int) $model->getAdminId());
            if ($admin instanceof Admin) {
                $result['staff']['id'] = $admin->getId();
                $result['staff']['name'] = $admin->getName();
                $result['staff']['email'] = $admin->getEmail();
            }
        }

        return $result;
    }

    public function authorizeAdmin($email, $plainTextPassword)
    {
        $model = $this->di['em']->getRepository(Admin::class)->findOneBy(['email' => $email, 'status' => Admin::STATUS_ACTIVE]);
        if ($model instanceof Admin && $model->isCron()) {
            $model = null;
        }

        return $this->di['auth']->authorizeUser($model, $plainTextPassword);
    }

    private function getLoggedInAdminOrCronAdmin(): Admin
    {
        if (isset($this->di['auth']) && !$this->di['auth']->isAdminLoggedIn()) {
            if (isset($this->di['is_cron']) && $this->di['is_cron'] === true) {
                return $this->getCronAdmin();
            }
        }

        return $this->di['loggedin_admin'];
    }
}
