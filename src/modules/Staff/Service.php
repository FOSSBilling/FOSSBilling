<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Staff;

use FOSSBilling\InjectionAwareInterface;

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

    public function setPermissions($member_id, $array)
    {
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $array = array_filter($array);
        $sql = 'UPDATE admin SET permissions = :p WHERE id = :id';
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('p', json_encode($array));
        $stmt->bindValue('id', $member_id);
        $stmt->execute();

        return true;
    }

    public function getPermissions($member_id)
    {
        $sql = 'SELECT permissions FROM admin WHERE id = :id';
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $member_id]);
        $json = $stmt->fetchColumn() ?? '';
        $permissions = json_decode($json, 1);
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
    public function hasPermission(?\Model_Admin $member, string $module, string $key = null, mixed $constraint = null): bool
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

        $canAlwaysAccess = $modulePermissions['can_always_access'] ?? false;
        if (!$canAlwaysAccess) {
            // They have no permissions or don't have any access to that module
            if (empty($permissions) || !array_key_exists($module, $permissions) || !is_array($permissions[$module]) || !($permissions[$module]['access'] ?? false)) {
                return false;
            }
        }

        // If this passes, the permission key isn't assigned to them and they therefore don't have permission
        if ((!is_null($key) && !is_array($permissions[$module])) || (!is_null($key) && !array_key_exists($key, $permissions[$module]))) {
            return false;
        }

        if (!is_null($key) && !is_null($constraint)) {
            return $permissions[$module][$key] === $constraint;
        } elseif (!is_null($key)) {
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
    public function checkPermissionsAndThrowException(string $module, string $key = null, mixed $constraint = null): void
    {
        if (!$this->hasPermission(null, $module, $key, $constraint)) {
            throw new \FOSSBilling\InformationException('You do not have permission to perform this action', [], 403);
        }
    }

    public static function onAfterClientOrderCreate(\Box_Event $event)
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
            error_log($exc->getMessage());
        }
    }

    public static function onAfterClientOpenTicket(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $helpdeskModel = $di['db']->load('SupportHelpdesk', $ticketModel->support_helpdesk_id);
            $emailService = $di['mod_service']('email');
            if (!empty($helpdeskModel->email)) {
                $email = [];
                $email['to'] = $helpdeskModel->email;
                $email['code'] = 'mod_support_helpdesk_ticket_open';
                $email['ticket'] = $ticket;
                $emailService->sendTemplate($email);

                return true;
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

    public static function onAfterClientReplyTicket(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);

            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_reply';
            $email['ticket'] = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterClientCloseTicket(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getTicketById($params['id']);
            $ticket = $supportTicketService->toApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_ticket_close';
            $email['ticket'] = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterGuestPublicTicketOpen(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getPublicTicketById($params['id']);
            $ticket = $supportTicketService->publicToApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_open';
            $email['ticket'] = $ticket;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterClientSignUp(\Box_Event $event)
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
            error_log($exc->getMessage());
        }

        return true;
    }

    public static function onAfterGuestPublicTicketReply(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportTicketService = $di['mod_service']('support');
            $ticketModel = $supportTicketService->getPublicTicketById($params['id']);
            $ticket = $supportTicketService->publicToApiArray($ticketModel, true);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_reply';
            $email['ticket'] = $ticket;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch (\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterGuestPublicTicketClose(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();

        try {
            $supportService = $di['mod_service']('Support');
            $publicTicket = $di['db']->load('SupportPTicket', $params['id']);
            $ticket = $supportService->publicToApiArray($publicTicket);
            $email = [];
            $email['to_staff'] = true;
            $email['code'] = 'mod_staff_pticket_close';
            $email['ticket'] = $ticket;
            $emailService = $di['mod_service']('Email');
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
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $pager->getSimpleResultSet($query, $params, $per_page);
    }

    public function getSearchQuery($data)
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

    public function update(\Model_Admin $model, $data)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforeAdminStaffUpdate', 'params' => ['id' => $model->id]]);

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

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffUpdate', 'params' => ['id' => $model->id]]);

        $this->di['logger']->info('Updated staff member %s details', $model->id);

        return true;
    }

    public function delete(\Model_Admin $model)
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

    public function changePassword(\Model_Admin $model, $password)
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
        $profileService->invalidateSessions('admin', $model->id);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffPasswordChange', 'params' => ['id' => $model->id]]);

        $this->di['logger']->info('Changed staff member %s password', $model->id);

        return true;
    }

    public function create(array $data)
    {
        // TODO: When it becomes possible to create other super admins, add a check for that here,
        $this->checkPermissionsAndThrowException('staff', 'create_and_edit_staff');

        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Admin', 3);

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
            throw new \FOSSBilling\InformationException('Staff member with email :email is already registered', [':email' => $data['email']], 788954);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminStaffCreate', 'params' => ['id' => $newId]]);

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

    public function getAdminGroupSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM admin_group
                order by id asc';

        return [$sql, []];
    }

    public function createGroup($name)
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

    public function deleteGroup(\Model_AdminGroup $model)
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

    public function updateGroup(\Model_AdminGroup $model, $data)
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

    public function getActivityAdminHistorySearchQuery($data)
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
            $where[] = ' a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email ';
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

    public function deleteLoginHistory(\Model_ActivityAdminHistory $model)
    {
        $this->di['db']->trash($model);

        return true;
    }

    public function authorizeAdmin($email, $plainTextPassword)
    {
        $model = $this->di['db']->findOne('Admin', 'email = ? AND status = ?', [$email, \Model_Admin::STATUS_ACTIVE]);
        if ($model == null) {
            return null;
        }

        return $this->di['auth']->authorizeUser($model, $plainTextPassword);
    }
}
