<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Staff;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function login($email, $password, $ip)
    {
        $event_params = array();
        $event_params['email'] = $email;
        $event_params['password'] = $password;
        $event_params['ip'] = $ip;

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminLogin', 'params'=>$event_params));

        $model = $this->authorizeAdmin($email, $password);
        if(!$model instanceof \Model_Admin ) {
            $this->di['events_manager']->fire(array('event'=>'onEventAdminLoginFailed', 'params'=>$event_params));
            throw new \Box_Exception('Check your login details', null, 403);
        }

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminLogin', 'params'=>array('id'=>$model->id, 'ip'=>$ip)));

        $result = array(
            'id'        =>  $model->id,
            'email'     =>  $model->email,
            'name'      =>  $model->name,
            'role'      =>  $model->role,
        );

        $this->di['session']->set('admin', $result);

        $this->di['logger']->info(sprintf('Staff member %s logged in', $model->id));

        return $result;
    }

    public function getAdminsCount()
    {
        $sql = "SELECT COUNT(*) FROM admin WHERE 1";
        return $this->di['db']->getCell($sql);
    }

    public function setPermissions($member_id, $array)
    {
        $sql="UPDATE admin SET permissions = :p WHERE id = :id";
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('p', json_encode($array));
        $stmt->bindValue('id', $member_id);
        $stmt->execute();
        return true;
    }
    
    public function getPermissions($member_id)
    {
        $sql="SELECT permissions FROM admin WHERE id = :id";
        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array('id'=>$member_id));
        $json = $stmt->fetchColumn();
        $permissions = json_decode($json, 1);
        if(!$permissions) {
            return array();
        }
        return $permissions;
    }
    
    public function hasPermission($member, $mod, $method = null)
    {
        if($member->role == \Model_Admin::ROLE_CRON || $member->role == \Model_Admin::ROLE_ADMIN) {
            return true;
        }
        
        $permissions = null;
        if(is_null($permissions)) {
            $permissions = $this->getPermissions($member->id);
        }
        
        if(empty($permissions)) {
            return false;
        }
        
        if(!array_key_exists($mod, $permissions)) {
            return false;
        }
        
        if(!is_null($method) && is_array($permissions[$mod]) && !in_array($method, $permissions[$mod])) {
            return false;
        }
        
        return true;
    }

    /**
     * @param \Box_Event $event
     */
    public static function onAfterClientOrderCreate(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();

        try {
            $orderModel = $di['db']->load('ClientOrder', $params['id']);
            $orderTicketService = $di['mod_service']('order');
            $order = $orderTicketService->toApiArray($orderModel, true);

            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_client_order';
            $email['order']    = $order;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    /**
     * @param \Box_Event $event
     */
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
                $email           = array();
                $email['to']     = $helpdeskModel->email;
                $email['code']   = 'mod_support_helpdesk_ticket_open';
                $email['ticket'] = $ticket;
                $emailService->sendTemplate($email);
                return true;
            }

            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_ticket_open';
            $email['ticket']    = $ticket;
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
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

            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_ticket_reply';
            $email['ticket']    = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
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
            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_ticket_close';
            $email['ticket']    = $ticket;

            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
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
            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_pticket_open';
            $email['ticket']    = $ticket;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public static function onAfterClientSignUp(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        
        try {
            $clientService = $di['mod_service']('client');

            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_client_signup';
            $email['c']         = $clientService ->get(array('id' => $params['id']));
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
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
            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_pticket_reply';
            $email['ticket']    = $ticket;
            $emailService = $di['mod_service']('email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }
    /**
     * @param \Box_Event $event
     */
    public static function onAfterGuestPublicTicketClose(\Box_Event $event)
    {
        $params = $event->getParameters();
        $di = $event->getDi();
        
        try {
            $supportService = $di['mod_service']('Support');
            $publicTicket = $di['db']->load('SupportPTicket', $params['id']);
            $ticket = $supportService->publicToApiArray($publicTicket);
            $email = array();
            $email['to_staff']  = true;
            $email['code']      = 'mod_staff_pticket_close';
            $email['ticket']    = $ticket;
            $emailService = $di['mod_service']('Email');
            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
    }

    public function getList($data){
        $data['no_cron'] = true;

        list ($query, $params) = $this->getSearchQuery($data);

        $di = $this->getDi();
        $pager = $di['pager'];
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $pager->getSimpleResultSet($query, $params, $per_page);
    }

    public function getSearchQuery($data)
    {
        $query = "SELECT * FROM admin";

        $search = $this->di['array_get']($data, 'search', NULL);
        $status = $this->di['array_get']($data, 'status', NULL);
        $no_cron = (bool) $this->di['array_get']($data, 'no_cron', false);

        $where = array();
        $bindings = array();

        if($search) {
            $search = "%$search%";
            $where[] = "(name LIKE :name OR email LIKE :email )";
            $bindings[':name'] = $search;
            $bindings[':email'] = $search;
        }

        if($status) {
            $where[] = "status = :status";
            $bindings[':status'] = $status;
        }

        if($no_cron) {
            $where[] = "role != :role";
            $bindings[':role'] =  \Model_Admin::ROLE_CRON;
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= " ORDER BY `admin_group_id` ASC, id ASC";

        return array($query, $bindings);
    }


    /**
     * @return \Model_Admin
     */
    public function getCronAdmin()
    {
        $cron = $this->di['db']->findOne('Admin', 'role = :role', array(':role'=>\Model_Admin::ROLE_CRON));
        if($cron instanceof \Model_Admin) {
            return $cron;
        }

        $cron = $this->di['db']->dispense('Admin');
        $cron->role = \Model_Admin::ROLE_CRON;
        $cron->admin_group_id = 1;
        $cron->email = $this->di['tools']->generatePassword().'@'.$this->di['tools']->generatePassword().'.com';
        $cron->pass = $this->di['password']->hashIt(uniqid() . microtime());
        $cron->name = "System Cron Job";
        $cron->signature = "";
        $cron->protected = 1;
        $cron->status = 'active';
        $cron->created_at = date('Y-m-d H:i:s');
        $cron->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($cron);
        return $cron;
    }

    public function toModel_AdminApiiArray(\Model_Admin $model, $deep = false)
    {
        $data = array(
            'id'                =>  $model->id,
            'role'              =>  $model->role,
            'admin_group_id'    =>  $model->admin_group_id,
            'email'             =>  $model->email,
            'name'              =>  $model->name,
            'status'            =>  $model->status,
            'signature'         =>  $model->signature,
            'created_at'        =>  $model->created_at,
            'updated_at'        =>  $model->updated_at,
        );

        $data['protected'] = $model->protected;

        $adminGroupModel = $this->di['db']->load('AdminGroup', $model->admin_group_id);
        $data['group']['id']    = $adminGroupModel->id;
        $data['group']['name']  = $adminGroupModel->name;

        return $data;
    }

    public function update(\Model_Admin $model, $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffUpdate', 'params'=>array('id'=>$model->id)));

        $model->email = $this->di['array_get']($data, 'email', $model->email);
        $model->admin_group_id = $this->di['array_get']($data, 'admin_group_id', $model->admin_group_id);
        $model->name = $this->di['array_get']($data, 'name', $model->name);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->signature = $this->di['array_get']($data, 'signature', $model->signature);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffUpdate', 'params'=>array('id'=>$model->id)));

        $this->di['logger']->info('Updated staff member %s details', $model->id);
        return true;
    }

    public function delete(\Model_Admin $model)
    {
        if($model->protected) {
            throw new \Box_Exception('This administrator account is protected and can not be removed');
        }
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffDelete', 'params'=>array('id'=>$model->id)));

        $id = $model->id;
        $this->di['db']->trash($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffDelete', 'params'=>array('id'=>$id)));

        $this->di['logger']->info('Deleted staff member %s', $id);
        return true;
    }

    public function changePassword(\Model_Admin $model, $password)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffPasswordChange', 'params'=>array('id'=>$model->id)));

        $model->pass = $this->di['password']->hashIt($password);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffPasswordChange', 'params'=>array('id'=>$model->id)));

        $this->di['logger']->info('Changed staff member %s password', $model->id);
        return true;
    }

    public function create(array $data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Admin', 3);

        $signature = $this->di['array_get']($data, 'signature', NULL);

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminStaffCreate', 'params'=>$data));

        $model = $this->di['db']->dispense('Admin');
        $model->role                = \Model_Admin::ROLE_STAFF;
        $model->admin_group_id      = $data['admin_group_id'];
        $model->email               = $data['email'];
        $model->pass                = $this->di['password']->hashIt($data['password']);
        $model->name                = $data['name'];
        $model->status              = $model->getStatus($data['status']);
        $model->signature           = $signature;
        $model->created_at          = date('Y-m-d H:i:s');
        $model->updated_at          = date('Y-m-d H:i:s');

        try {
            $newId = $this->di['db']->store($model);
        } catch(\RedBeanPHP\RedException $e) {
            throw new \Box_Exception('Staff member with email :email is already registered', array(':email'=>$data['email']), 788954);
        }

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminStaffCreate', 'params'=>array('id'=>$newId)));

        $this->di['logger']->info('Created new  staff member %s', $newId);

        return (int)$newId;
    }

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
        $this->_sendMail($admin, $data['password']);

        $data['remember'] = true;
        return $newId;
    }

    public function getAdminGroupPair()
    {
        $sql = 'SELECT id, name
                FROM  admin_group';
        $rows = $this->di['db']->getAll($sql);
        $result = array();

        foreach($rows as $row){
            $result[ $row['id'] ] = $row['name'];
        }

        return $result;
    }

    public function getAdminGroupSearchQuery($data)
    {
        $sql = 'SELECT *
                FROM admin_group
                order by id asc';
        return array($sql, array());
    }

    public function createGroup($name)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService ->checkLimits('Model_AdminGroup', 2);

        $model = $this->di['db']->dispense('AdminGroup');
        $model->name = $name;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $groupId = $this->di['db']->store($model);

        $this->di['logger']->info('Created new staff group %s', $groupId);
        return (int) $groupId;

    }

    public function toAdminGroupApiArray(\Model_AdminGroup $model, $deep = false, $identity = null)
    {
        $data = array();
        $data['id'] = $model->id;
        $data['name'] = $model->name;
        $data['created_at'] = $model->created_at;
        $data['updated_at'] = $model->updated_at;
        return $data;
    }

    public function deleteGroup(\Model_AdminGroup $model)
    {
        $id = $model->id;
        if($model->id == 1) {
            throw new \Box_Exception('Administrators group can not be removed');
        }

        $sql = 'SELECT count(1)
                FROM admin
                WHERE admin_group_id = :id';
        $staffMembersInGroup = $this->di['db']->getCell($sql, array('id' => $model->id));
        if($staffMembersInGroup > 0) {
            throw new \Box_Exception('Can not remove group which has staff members');
        }

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted staff group %s', $id);
        return true;
    }

    public function updateGroup(\Model_AdminGroup $model, $data)
    {
        if (isset($data['name'])){
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

        $search = $this->di['array_get']($data, 'search', NULL);
        $admin_id = $this->di['array_get']($data, 'admin_id', NULL);

        $where = array();
        $params = array();
        if($search) {
            $where[] = ' a.name LIKE :name OR a.id LIKE :id OR a.email LIKE :email ';
            $params['name']     = "%$search%";
            $params['id']       = "%$search%";
            $params['email']    = "%$search%";
        }

        if($admin_id) {
            $where[] = 'm.admin_id = :admin_id';
            $params['admin_id'] = $admin_id;
        }

        if (!empty($where)){
            $sql .= ' WHERE '.implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.id DESC';

        return array($sql, $params);
    }

    public function toActivityAdminHistoryApiArray(\Model_ActivityAdminHistory $model, $deep = false)
    {
        $result = array(
            'id'         => $model->id,
            'ip'         => $model->ip,
            'created_at' => $model->created_at,
        );
        if ($model->admin_id) {
            $adminModel = $this->di['db']->load('Admin', $model->admin_id);
            if ($adminModel instanceof \Model_Admin && $adminModel->id) {
                $result['staff']['id']    = $adminModel->id;
                $result['staff']['name']  = $adminModel->name;
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

    protected function _sendMail($admin, $admin_pass)
    {
        $admin_name = $admin->name;
        $admin_email = $admin->email;

        $client_url = $this->di['url']->link('/');
        $admin_url = $this->di['url']->adminLink('/');

        $content = "Hi $admin_name, ".PHP_EOL;
        $content .= "You have successfully setup BoxBilling at ".BB_URL.PHP_EOL;
        $content .= "Access client area at: ".$client_url.PHP_EOL;
        $content .= "Access admin area at: ".$admin_url." with login details:".PHP_EOL;
        $content .= "Email: ".$admin_email.PHP_EOL;
        $content .= "Password: ".$admin_pass.PHP_EOL.PHP_EOL;

        $content .= "Read BoxBilling documentation to get started http://docs.boxbilling.com/".PHP_EOL;
        $content .= "Thank You for using BoxBilling.".PHP_EOL;

        $subject = sprintf('BoxBilling is ready at "%s"', BB_URL);

        $systemService =  $this->di['mod_service']('system');
        $from = $systemService->getParamValue('company_email');
        $emailService = $this->di['mod_service']('Email');
        $emailService->sendMail($admin_email, $from, $subject, $content);
    }

    public function authorizeAdmin($email, $plainTextPassword)
    {
        $model = $this->di['db']->findOne('Admin', 'email = ? AND status = ?', array($email, \Model_Admin::STATUS_ACTIVE));
        if ($model == null){
            return null;
        }

        return $this->di['auth']->authorizeUser($model, $plainTextPassword);
    }

}
