<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff;

use Box\Mod\Client\Event\AfterClientSignUpEvent;
use Box\Mod\Order\Event\AfterClientOrderCreateEvent;
use Box\Mod\Staff\Event\AdminLoginFailedEvent;
use Box\Mod\Staff\Event\AfterAdminLoginEvent;
use Box\Mod\Staff\Event\AfterAdminStaffCreateEvent;
use Box\Mod\Staff\Event\AfterAdminStaffDeleteEvent;
use Box\Mod\Staff\Event\AfterAdminStaffPasswordChangeEvent;
use Box\Mod\Staff\Event\AfterAdminStaffUpdateEvent;
use Box\Mod\Staff\Event\BeforeAdminLoginEvent;
use Box\Mod\Staff\Event\BeforeAdminStaffCreateEvent;
use Box\Mod\Staff\Event\BeforeAdminStaffDeleteEvent;
use Box\Mod\Staff\Event\BeforeAdminStaffPasswordChangeEvent;
use Box\Mod\Staff\Event\BeforeAdminStaffUpdateEvent;
use Box\Mod\Support\Event\AfterClientCloseTicketEvent;
use Box\Mod\Support\Event\AfterClientOpenTicketEvent;
use Box\Mod\Support\Event\AfterClientReplyTicketEvent;
use Box\Mod\Support\Event\AfterGuestPublicTicketCloseEvent;
use Box\Mod\Support\Event\AfterGuestPublicTicketOpenEvent;
use Box\Mod\Support\Event\AfterGuestPublicTicketReplyEvent;
use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
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
            'manage_settings' => [],
        ];
    }

    public function login($email, $password, $ip): array
    {
        $this->di['events_manager']->dispatch(new BeforeAdminLoginEvent(
            email: $email,
            ip: $ip,
        ));

        $model = $this->authorizeAdmin($email, $password);
        if (!$model instanceof \Model_Admin) {
            $this->di['events_manager']->dispatch(new AdminLoginFailedEvent(
                email: $email,
                ip: $ip,
            ));

            throw new \FOSSBilling\InformationException('Check your login details', null, 403);
        }

        $this->di['events_manager']->dispatch(new AfterAdminLoginEvent(
            adminId: $model->id,
            ip: $ip,
        ));

        $result = [
            'id' => $model->id,
            'email' => $model->email,
            'name' => $model->name,
            'role' => $model->role,
        ];

        session_regenerate_id();
        $this->di['session']->set('admin', $result);

        $this->di['logger']->info(sprintf('Staff member %s logged in', $model->id));

        return $result;
    }

    public function getAdminsCount()
    {
        $sql = 'SELECT COUNT(*) FROM admin WHERE 1';

        return $this->di['db']->getCell($sql);
    }

    public function getPairs(array $data = [])
    {
        $limit = $data['per_page'] ?? 30;

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

    public function setPermissions($member_id, $array): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $array = array_filter($array);

        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->update('admin')
            ->set('permissions', ':p')
            ->where('id = :id')
            ->setParameter('p', json_encode($array))
            ->setParameter('id', $member_id)
            ->executeStatement();

        return true;
    }

    public function getPermissions($member_id)
    {
        $query = $this->di['dbal']->createQueryBuilder();
        $query
            ->select('permissions')
            ->from('admin')
            ->where('id = :id')
            ->setParameter('id', $member_id);
        $result = $query->executeQuery()->fetchOne() ?? '';

        $permissions = json_decode($result, true);
        if (!$permissions) {
            return [];
        }

        return $permissions;
    }

    /**
     * Determines  if a staff member has the required permissions.
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
            $member = $this->di['loggedin_admin'];
        }

        if ($member->role == \Model_Admin::ROLE_CRON || $member->role == \Model_Admin::ROLE_ADMIN || in_array($module, $alwaysAllowed)) {
            return true;
        }

        $extensionService = $this->di['mod_service']('Extension');
        $modulePermissions = $extensionService->getSpecificModulePermissions($module);
        $permissions = $this->getPermissions($member->id);

        if ($modulePermissions['hide_permissions'] ?? false) {
            $canAlwaysAccess = true;
        } else {
            $canAlwaysAccess = $modulePermissions['can_always_access'] ?? false;
        }

        if (!$canAlwaysAccess) {
            // They have no permissions or don't have any access to that module
            if (empty($permissions) || !array_key_exists($module, $permissions) || !is_array($permissions[$module]) || !($permissions[$module]['access'] ?? false)) {
                return false;
            }
        }

        if (!is_null($key)) {
            // If this passes, the permission key isn't assigned to them and they therefore don't have permission
            if (!is_array($permissions[$module]) || !array_key_exists($key, $permissions[$module])) {
                return false;
            }

            if (!is_null($constraint)) {
                return $permissions[$module][$key] === $constraint;
            }

            return (bool) $permissions[$module][$key];
        }

        return true;
    }

    /**
     * Acts as an alias to `hasPermission`, but it'll also throw an exception stating the staff member doesn't have permission if they don't.
     *
     * @param string      $module     what module to check permission for
     * @param string|null $key        the permission key for the associated module
     * @param mixed       $constraint if the permission key allows for multiple options, specify the one you want to use as a constraint here
     */
    public function checkPermissionsAndThrowException(string $module, ?string $key = null, mixed $constraint = null): void
    {
        if (!$this->hasPermission(null, $module, $key, $constraint)) {
            throw new \FOSSBilling\InformationException('You do not have permission to perform this action', [], 403);
        }
    }

    #[AsEventListener(event: AfterClientOrderCreateEvent::class)]
    public function notifyStaffOnClientOrderCreate(AfterClientOrderCreateEvent $event): void
    {
        try {
            $orderModel = $this->di['db']->load('ClientOrder', $event->orderId);
            $orderTicketService = $this->di['mod_service']('order');
            $order = $orderTicketService->toApiArray($orderModel, true);

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_client_order';
            $email['order'] = $order;
            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterClientOpenTicketEvent::class)]
    public function notifyStaffOnClientOpenTicket(AfterClientOpenTicketEvent $event): void
    {
        try {
            $supportTicketService = $this->di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($event->ticketId);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $helpdeskModel = $this->di['db']->load('SupportHelpdesk', $ticketModel->support_helpdesk_id);
            $emailService = $this->di['mod_service']('email');
            if (!empty($helpdeskModel->email)) {
                $email = [];
                $email['to'] = $helpdeskModel->email;
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
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterClientReplyTicketEvent::class)]
    public function notifyStaffOnClientReplyTicket(AfterClientReplyTicketEvent $event): void
    {
        try {
            $supportTicketService = $this->di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($event->ticketId);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_reply';
            $email['ticket'] = $ticket;

            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterClientCloseTicketEvent::class)]
    public function notifyStaffOnClientCloseTicket(AfterClientCloseTicketEvent $event): void
    {
        try {
            $supportTicketService = $this->di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($event->ticketId);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_close';
            $email['ticket'] = $ticket;

            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterGuestPublicTicketOpenEvent::class)]
    public function notifyStaffOnGuestPublicTicketOpen(AfterGuestPublicTicketOpenEvent $event): void
    {
        try {
            $supportTicketService = $this->di['mod_service']('support');
            $ticketModel = $supportTicketService->getPublicTicketById($event->ticketId);
            $ticket = $supportTicketService->publicToApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_open';
            $email['ticket'] = $ticket;
            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterClientSignUpEvent::class)]
    public function notifyStaffOnClientSignUp(AfterClientSignUpEvent $event): void
    {
        try {
            $clientService = $this->di['mod_service']('client');

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_client_signup';
            $email['c'] = $clientService->get(['id' => $event->clientId]);
            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterGuestPublicTicketReplyEvent::class)]
    public function notifyStaffOnGuestPublicTicketReply(AfterGuestPublicTicketReplyEvent $event): void
    {
        try {
            $supportTicketService = $this->di['mod_service']('support');
            $ticketModel = $supportTicketService->getPublicTicketById($event->ticketId);
            $ticket = $supportTicketService->publicToApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_reply';
            $email['ticket'] = $ticket;
            $emailService = $this->di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    #[AsEventListener(event: AfterGuestPublicTicketCloseEvent::class)]
    public function notifyStaffOnGuestPublicTicketClose(AfterGuestPublicTicketCloseEvent $event): void
    {
        try {
            $supportService = $this->di['mod_service']('Support');
            $publicTicket = $this->di['db']->load('SupportPTicket', $event->ticketId);
            $ticket = $supportService->publicToApiArray($publicTicket);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_close';
            $email['ticket'] = $ticket;
            $emailService = $this->di['mod_service']('Email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function getList($data)
    {
        $data['no_cron'] = true;

        [$query, $params] = $this->getSearchQuery($data);

        $di = $this->getDi();
        $pager = $di['pager'];
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();

        return $pager->getPaginatedResultSet($query, $params, $per_page);
    }

    public function getSearchQuery($data): array
    {
        $query = 'SELECT * FROM admin';

        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;
        $no_cron = (bool) ($data['no_cron'] ?? false);

        $where = [];
        $bindings = [];

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

        $CronPass = $this->di['tools']->generatePassword(256, 4);

        $cron = $this->di['db']->dispense('Admin');
        $cron->role = \Model_Admin::ROLE_CRON;
        $cron->admin_group_id = 1;
        $cron->email = $cronEmail;
        $cron->pass = $this->di['password']->hashIt($CronPass);
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
        $this->di['events_manager']->dispatch(new BeforeAdminStaffUpdateEvent(staffId: $model->id));

        if ($model->role === 'admin') {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_admin');
        } else {
            $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');
        }

        $model->email = $data['email'] ?? $model->email;
        $model->admin_group_id = $data['admin_group_id'] ?? $model->admin_group_id;
        $model->name = $data['name'] ?? $model->name;
        $model->status = $data['status'] ?? $model->status;
        $model->signature = $data['signature'] ?? $model->signature;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['events_manager']->dispatch(new AfterAdminStaffUpdateEvent(staffId: $model->id));

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

        $this->di['events_manager']->dispatch(new BeforeAdminStaffDeleteEvent(staffId: $model->id));

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->dispatch(new AfterAdminStaffDeleteEvent(staffId: $id));

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

        $this->di['events_manager']->dispatch(new BeforeAdminStaffPasswordChangeEvent(staffId: $model->id));

        $model->pass = $this->di['password']->hashIt($password);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $profileService = $this->di['mod_service']('profile');
        $profileService->invalidateSessions('admin', $model->id);

        $this->di['events_manager']->dispatch(new AfterAdminStaffPasswordChangeEvent(staffId: $model->id));

        $this->di['logger']->info('Changed staff member %s password', $model->id);

        return true;
    }

    public function create(array $data): int
    {
        // TODO: When it becomes possible to create other super admins, add a check for that here,
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Admin', 3);

        $signature = $data['signature'] ?? null;

        $this->di['events_manager']->dispatch(new BeforeAdminStaffCreateEvent(
            email: $data['email'],
            name: $data['name'],
            adminGroupId: $data['admin_group_id'],
            data: $data,
        ));

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

        $this->di['events_manager']->dispatch(new AfterAdminStaffCreateEvent(staffId: $newId));

        $this->di['logger']->info('Created new staff member %s', $newId);

        return (int) $newId;
    }

    /**
     * Used to create the initial admin account and then goes unused.
     */
    public function createAdmin(array $data)
    {
        $admin = $this->di['db']->dispense('Admin');
        $admin->role = 'admin';
        $admin->admin_group_id = 1;
        $admin->name = 'Administrator';
        $admin->email = $data['email'];
        $admin->pass = $this->di['password']->hashIt($data['password']);
        $admin->protected = 1;
        $admin->status = 'active';
        $admin->created_at = date('Y-m-d H:i:s');
        $admin->updated_at = date('Y-m-d H:i:s');

        $newId = $this->di['db']->store($admin);

        $this->di['logger']->info('Main administrator %s account created', $admin->email);

        return $newId;
    }

    /**
     * @return mixed[]
     */
    public function getAdminGroupPair(): array
    {
        $sql = 'SELECT id, name
                FROM  admin_group';
        $rows = $this->di['db']->getAll($sql);
        $result = [];

        foreach ($rows as $row) {
            $result[$row['id']] = $row['name'];
        }

        return $result;
    }

    public function getAdminGroupSearchQuery($data): array
    {
        $sql = 'SELECT *
                FROM admin_group
                order by id asc';

        return [$sql, []];
    }

    public function createGroup($name): int
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_AdminGroup', 2);

        $model = $this->di['db']->dispense('AdminGroup');
        $model->name = $name;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $groupId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new staff group %s', $groupId);

        return (int) $groupId;
    }

    public function toAdminGroupApiArray(\Model_AdminGroup $model, $deep = false, $identity = null): array
    {
        $data = [];
        $data['id'] = $model->id;
        $data['name'] = $model->name;
        $data['created_at'] = $model->created_at;
        $data['updated_at'] = $model->updated_at;

        return $data;
    }

    public function deleteGroup(\Model_AdminGroup $model): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        $id = $model->id;
        if ($model->id == 1) {
            throw new \FOSSBilling\InformationException('Administrators group cannot be removed');
        }

        $sql = 'SELECT count(1)
                FROM admin
                WHERE admin_group_id = :id';
        $staffMembersInGroup = $this->di['db']->getCell($sql, ['id' => $model->id]);
        if ($staffMembersInGroup > 0) {
            throw new \FOSSBilling\InformationException('Cannot remove group which has staff members');
        }

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted staff group %s', $id);

        return true;
    }

    public function updateGroup(\Model_AdminGroup $model, $data): bool
    {
        $this->checkPermissionsAndThrowException('staff', 'manage_groups');

        if (isset($data['name'])) {
            $model->name = $data['name'];
        }
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated staff group %s', $model->id);

        return true;
    }

    public function getActivityAdminHistorySearchQuery($data): array
    {
        $sql = 'SELECT m.*, a.email, a.name
                FROM activity_admin_history as m
                LEFT JOIN admin as a on m.admin_id = a.id
                ';

        $search = $data['search'] ?? null;
        $admin_id = $data['admin_id'] ?? null;

        $where = [];
        $params = [];
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
}
