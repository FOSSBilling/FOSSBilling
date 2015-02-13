<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Client;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di = null;

    /**
     * @param Box_Di|null $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di|null
     */
    public function getDi()
    {
        return $this->di;
    }

    public function approveClientEmailByHash($hash)
    {
        $db = $this->di['db'];
        $result = $db->getRow('SELECT id, client_id FROM extension_meta WHERE extension = "mod_client" AND meta_key = "confirm_email" AND meta_value = :hash', array(':hash'=>$hash));
        if(!$result) {
            throw new \Box_Exception('Invalid email confirmation link');
        }
        $db->exec('UPDATE client SET email_approved = 1 WHERE id = :id', array('id'=>$result['client_id']));
        $db->exec('DELETE FROM extension_meta WHERE id = :id', array('id'=>$result['id']));
        return true;
    }
    
    public function generateEmailConfirmationLink($client_id)
    {
        $hash = strtolower($this->di['tools']->generatePassword(50));
        $db = $this->di['db'];

        $meta = $db->dispense('ExtensionMeta');
        $meta->extension    = 'mod_client';
        $meta->client_id    = $client_id;
        $meta->meta_key     = 'confirm_email';
        $meta->meta_value   = $hash;
        $meta->created_at   = date('c');
        $meta->updated_at   = date('c');
        $db->store($meta);

        return $this->di['tools']->url('/client/confirm-email/'.$hash);
    }
    
    public static function onAfterClientSignUp(\Box_Event $event)
    {
        $di = $event->getDi();
        $params = $event->getParameters();
        $config = $di['mod_config']('client');
        $emailService = $di['mod_service']('email');
        try {
            $email = array();
            $email['to_client'] = $params['id'];
            $email['code']      = 'mod_client_signup';
            $email['password']  = $params['password'];
            $email['require_email_confirmation']  = false;
            if(isset($config['require_email_confirmation']) && $config['require_email_confirmation']) {
                $clientService = $di['mod_service']('client');
                $email['require_email_confirmation']  = true;
                $email['email_confirmation_link'] = $clientService->generateEmailConfirmationLink($params['id']);
            }

            $emailService->sendTemplate($email);
        } catch(\Exception $exc) {
            error_log($exc->getMessage());
        }
        
        return true;
    }

    public function getSearchQuery($data, $selectStmt = 'SELECT c.*')
    {
        $sql = $selectStmt;
        $sql .= ' FROM client as c left join client_group as cg on c.client_group_id = cg.id';

        $search     = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : NULL;
        $client_id  = (isset($data['client_id']) && !empty($data['client_id'])) ? $data['client_id'] : NULL;
        $group_id   = (isset($data['group_id']) && !empty($data['group_id'])) ? $data['group_id'] : NULL;
        $id         = (isset($data['id']) && !empty($data['id'])) ? $data['id'] : NULL;
        $status     = (isset($data['status']) && !empty($data['status'])) ? $data['status'] : NULL;
        $name       = (isset($data['name']) && !empty($data['name'])) ? $data['name'] : NULL;
        $company    = (isset($data['company']) && !empty($data['company'])) ? $data['company'] : NULL;
        $email      = (isset($data['email']) && !empty($data['email'])) ? $data['email'] : NULL;
        $created_at = (isset($data['created_at']) && !empty($data['created_at'])) ? $data['created_at'] : NULL;
        $date_from  = (isset($data['date_from']) && !empty($data['date_from'])) ? $data['date_from'] : NULL;
        $date_to    = (isset($data['date_to']) && !empty($data['date_to'])) ? $data['date_to'] : NULL;

        $where = array();
        $params = array();
        if($id) {
            $where[] = 'c.id = :client_id or c.aid = :alt_client_id';
            $params[':client_id'] = $id;
            $params[':alt_client_id'] = $id;
        }

        if($name) {
            $where[] = '(c.first_name LIKE :first_name or c.last_name LIKE :last_name )';
            $name = "%" . $name . "%";
            $params[':first_name'] = $name;
            $params[':last_name'] = $name;
        }

        if($email) {
            $where[] = 'c.email LIKE :email';
            $params[':email'] = "%" . $email . "%";
        }

        if($company) {
            $where[] = 'c.company LIKE :company';
            $params[':company'] = "%" . $company . "%";
        }

        if($status) {
            $where[] = 'c.status = :status';
            $params[':status'] = $status;
        }

        if($group_id) {
            $where[] = 'c.client_group_id = :group_id';
            $params[':group_id'] = $group_id;
        }

        if($created_at) {
            $where[] = "DATE_FORMAT(c.created_at, '%Y-%m-%d') = :created_at";
            $params[':created_at'] = date('Y-m-d', strtotime($created_at)) ;
        }

        if($date_from) {
            $where[] = 'UNIX_TIMESTAMP(c.created_at) >= :date_from';
            $params[':date_from'] = strtotime($date_from);
        }

        if($date_to) {
            $where[] = 'UNIX_TIMESTAMP(c.created_at) <= :date_from';
            $params[':date_to'] = strtotime($date_to);
        }

        //smartSearch
        if($search) {
            if(is_numeric($search)) {
                $where[] = 'c.id = :cid or c.aid = :caid';
                $params[':cid'] = $search;
                $params[':caid'] = $search;
            } else {
                $where[] = "c.company LIKE :s_company OR c.first_name LIKE :s_first_time OR c.last_name LIKE :s_last_name OR c.email LIKE :s_email OR CONCAT(c.first_name,  ' ', c.last_name ) LIKE  :full_name";
                $search = "%" . $search . "%";
                $params[':s_company'] = $search;
                $params[':s_first_time'] = $search;
                $params[':s_last_name'] = $search;
                $params[':s_email'] = $search;
                $params[':full_name'] = $search;
            }
        }

        if (!empty($where)){
            $sql .= ' WHERE '.implode(' AND ', $where);
        }
        $sql = $sql.' ORDER BY c.created_at desc';

        return array($sql, $params);
    }

    public function getPairs($data)
    {
        $limit = isset($data['per_page']) ? $data['per_page'] : 30;
        list($sql, $params) = $this->getSearchQuery($data, "SELECT c.id, CONCAT(c.first_name,  ' ', c.last_name) as full_name");
        $sql = $sql.' LIMIT '.$limit;
        return $this->di['db']->getAssoc($sql, $params);
    }

    public function toSessionArray(\Model_Client $model)
    {
        return array(
            'id'        =>  $model->id,
            'email'     =>  $model->email,
            'name'      =>  $model->getFullName(),
            'role'      =>  $model->role,
        );
    }

    public function emailAreadyRegistered($new_email, \Model_Client $model = null)
    {
        if($model && $model->email == $new_email) {
            return false;
        }

        $result = $this->di['db']->findOne('Client', 'email = ?', array($new_email));

        return ($result) ? true : false;
    }

    public function canChangeCurrency(\Model_Client $model, $currency = null)
    {
        if (!$model->currency) {
            return true;
        }

        if ($model->currency == $currency) {
            return false;
        }

        $invoice = $this->di['db']->findOne('Invoice', 'client_id = :client_id', array(':client_id' => $model->id));
        if ($invoice instanceof \Model_Invoice) {
            throw new \Box_Exception('Currency can not be changed. Client already have invoices issued.');
        }

        $order = $this->di['db']->findOne('ClientOrder', 'client_id = :client_id', array(':client_id' => $model->id));
        if ($order instanceof \Model_ClientOrder) {
            throw new \Box_Exception('Currency can not be changed. Client already have orders.');
        }

        /*
        if($model->ClientBalance->count() > 0) {
            throw new \Box_Exception('Currency can not be changed. Client have money in balance.');
        }
        */
        return true;
    }

    public function addFunds(\Model_Client $client, $amount, $description, array $data = null)
    {
        if(!$client->currency) {
            throw new \Box_Exception('Define clients currency before adding funds.');
        }

        if(!is_numeric($amount)) {
            throw new \Box_Exception('Funds amount is not valid');
        }

        if(empty($description)) {
            throw new \Box_Exception('Funds description is not valid');
        }

        $credit = $this->di['db']->dispense('ClientBalance');

        $credit->client_id = $client->id;
        $credit->type = isset($data['type']) ? $data['type'] : 'gift';
        $credit->rel_id = isset($data['rel_id']) ? $data['rel_id'] : null;
        $credit->description = $description;
        $credit->amount = $amount;
        $credit->created_at = date('c');
        $credit->updated_at = date('c');

        $this->di['db']->store($credit);
        return true;
    }

    public function getExpiredPasswordReminders()
    {
        $expire_after_hours = 2;
        $expired = $this->di['db']->find('ClientPasswordReset', 'UNIX_TIMESTAMP() - ? > created_at', array($expire_after_hours * 60 * 60));
        return $expired;
    }

    public function getHistorySearchQuery($data)
    {
        $q = 'SELECT ach.*, c.first_name, c.last_name, c.email
              FROM activity_client_history as ach
                LEFT JOIN client as c on ach.client_id = c.id ';

        $search = isset($data['search']) ? $data['search'] : NULL;
        $client_id = isset($data['client_id']) ? $data['client_id'] : NULL;

        $where = array();
        $params = array();
        if($search) {
            $where[] = 'c.first_name LIKE %:first_name% OR c.last_name LIKE %:last_name% OR c.id LIKE :id';
            $params[':first_name'] = $search;
            $params[':last_name'] = $search;
            $params[':id'] = $search;
        }

        if($client_id) {
            $where[] = 'ach.client_id = :client_id';
            $params[':client_id'] = $client_id;
        }

        if (!empty($where)){
            $q .= ' WHERE '.implode(' AND ', $where);
        }

        $q .= ' ORDER BY ach.id desc';

        return array($q, $params);
    }

    public function counter()
    {
        $sql = 'SELECT status, COUNT(id) as counter
                FROM client
                group by status';
        $data = $this->di['db']->getAssoc($sql);
        return array(
            'total' =>  array_sum($data),
            \Model_Client::ACTIVE =>  isset($data[\Model_Client::ACTIVE]) ? $data[\Model_Client::ACTIVE] : 0,
            \Model_Client::SUSPENDED =>  isset($data[\Model_Client::SUSPENDED]) ? $data[\Model_Client::SUSPENDED] : 0,
            \Model_Client::CANCELED =>  isset($data[\Model_Client::CANCELED]) ? $data[\Model_Client::CANCELED] : 0,
        );
    }

    public function getGroupPairs()
    {
        $sql = 'SELECT id, title
                FROM client_group';
        return $this->di['db']->getAssoc($sql);
    }

    public function clientAlreadyExists($email)
    {
        $client = $this->di['db']->findOne('Client', 'email = :email ', array(':email' => $email));

        return ($client instanceof \Model_Client);
    }

    public function getByLoginDetails($email, $password)
    {
        $client = $this->di['db']->findOne('Client', 'email = ? and pass = ? and status = ?', array($email, $password, \Model_Client::ACTIVE));
        return $client;
    }

    public function toApiArray(\Model_Client $model, $deep = false, $identity = null)
    {
        $details = array(
            'id'    =>  $model->id,
            'aid'    =>  $model->aid,
            'email'    =>  $model->email,
            'type'    =>  $model->type,
            'group_id' => $model->client_group_id,
            'company'    =>  $model->company,
            'company_vat'  =>  $model->company_vat,
            'company_number'  =>  $model->company_number,
            'first_name'    =>  $model->first_name,
            'last_name'    =>  $model->last_name,
            'gender'    =>  $model->gender,
            'birthday'    =>  $model->birthday,
            'phone_cc'    =>  $model->phone_cc,
            'phone'    =>  $model->phone,
            'address_1'    =>  $model->address_1,
            'address_2'    =>  $model->address_2,
            'city'    =>  $model->city,
            'state'    =>  $model->state,
            'postcode'    =>  $model->postcode,
            'country'    =>  $model->country,
            'currency'    =>  $model->currency,
            'notes'    =>  $model->notes,
            'created_at'    =>  $model->created_at,
            'document_nr' => $model->document_nr,
        );

        if($deep) {
            $details['balance'] = $this->getClientBalance($model);
        }

        $m = $this->di['db']->toArray($model);
        for ($i = 1; $i < 11; $i++) {
            $k = 'custom_'.$i;
            if(isset($m[$k]) && !empty($m[$k])) {
                $details[$k] = $m[$k];
            }
        }

        $clientGroup = $this->di['db']->load('ClientGroup', $model->client_group_id);

        if($identity instanceof \Model_Admin) {
            $details['auth_type'] = $model->auth_type;
            $details['api_token'] = $model->api_token;
            $details['ip'] = $model->ip;
            $details['status'] = $model->status;
            $details['tax_exempt'] = $model->tax_exempt;
            $details['group'] = ($clientGroup) ? $clientGroup->title : NULL;
            $details['updated_at'] = $model->updated_at;
            $details['email_approved'] = $model->email_approved;
        }

        return $details;
    }

    public function getClientBalance(\Model_Client $c)
    {
        $sql = 'SELECT SUM(amount) as client_total
                FROM client_balance
                WHERE client_id = ?
                GROUP BY client_id';

        $balance = $this->di['db']->getCell($sql, array($c->id));

        return $balance;
    }

    public function get($data)
    {
        if(!isset($data['id']) && !isset($data['email'])) {
            throw new \Box_Exception('Client ID or email is required');
        }

        $db = $this->di['db'];
        $client = null;
        if(isset($data['id'])) {
            $client = $db->findOne('Client', 'id = ?', array($data['id']));
        }

        if(!$client && isset($data['email'])) {
            $client = $db->findOne('Client', 'email = ?', array($data['email']));
        }

        if(!$client instanceof \Model_Client ) {
            throw new \Box_Exception('Client not found');
        }
        return $client;
    }

    public function isClientTaxable(\Model_Client $model)
    {
        $systemService = $this->di['mod_service']('system');

        if (!$systemService->getParamValue('tax_enabled', false)) {
            return false;
        }

        if ($model->tax_exempt) {
            return false;
        }

        return true;
    }

    public function createGroup(array $data)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_ClientGroup', 2);

        $model = $this->di['db']->dispense('ClientGroup');

        $model->title = $data['title'];
        $model->updated_at = date('c');
        $model->created_at = date('c');

        $group_id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new client group #%s', $model->id);
        return $group_id;
    }

    public function deleteGroup(\Model_ClientGroup $model)
    {
        $client = $this->di['db']->findOne('Client', 'client_group_id = ?', array($model->id));
        if($client) {
            throw new \Box_Exception('Can not remove group with clients');
        }

        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed client group #%s', $model->id);
        return true;
    }

    private function createClient(array $data)
    {
        $password = isset($data['password']) ? $data['password'] : uniqid();

        $client = $this->di['db']->dispense('Client');
        $client->auth_type = isset($data['auth_type']) ? $data['auth_type'] : NULL;
        $client->email = strtolower(trim($data['email']));
        $client->first_name = ucwords($data['first_name']);
        $client->pass =  $this->di['password']->hashIt($password);

        $client->aid = isset($data['aid']) ? $data['aid'] : NULL ;
        $client->last_name = isset($data['last_name']) ? $data['last_name'] : NULL ;
        $client->client_group_id = isset($data['group_id']) ? $data['group_id'] : NULL ;
        $client->status = isset($data['status']) ? $data['status'] : NULL ;
        $client->gender = isset($data['gender']) ? $data['gender'] : NULL ;
        $client->birthday = isset($data['birthday']) ? $data['birthday'] : NULL ;
        $client->phone_cc = isset($data['phone_cc']) ? $data['phone_cc'] : NULL ;
        $client->phone = isset($data['phone']) ? $data['phone'] : NULL ;
        $client->company = isset($data['company']) ? $data['company'] : NULL ;
        $client->company_vat = isset($data['company_vat']) ? $data['company_vat'] : NULL ;
        $client->company_number = isset($data['company_number']) ? $data['company_number'] : NULL ;
        $client->type = isset($data['type']) ? $data['type'] : NULL ;
        $client->address_1 = isset($data['address_1']) ? $data['address_1'] : NULL ;
        $client->address_2 = isset($data['address_2']) ? $data['address_2'] : NULL ;
        $client->city = isset($data['city']) ? $data['city'] : NULL ;
        $client->state = isset($data['state']) ? $data['state'] : NULL ;
        $client->postcode = isset($data['postcode']) ? $data['postcode'] : NULL ;
        $client->country = isset($data['country']) ? $data['country'] : NULL ;
        $client->document_type = isset($data['document_type']) ? $data['document_type'] : NULL ;
        $client->document_nr = isset($data['document_nr']) ? $data['document_nr'] : NULL ;
        $client->notes = isset($data['notes']) ? $data['notes'] : NULL ;
        $client->lang = isset($data['lang']) ? $data['lang'] : NULL ;
        $client->currency = isset($data['currency']) ? $data['currency'] : NULL ;

        $client->custom_1 = isset($data['custom_1']) ? $data['custom_1'] : NULL ;
        $client->custom_2 = isset($data['custom_2']) ? $data['custom_2'] : NULL ;
        $client->custom_3 = isset($data['custom_3']) ? $data['custom_3'] : NULL ;
        $client->custom_4 = isset($data['custom_4']) ? $data['custom_4'] : NULL ;
        $client->custom_5 = isset($data['custom_5']) ? $data['custom_5'] : NULL ;
        $client->custom_6 = isset($data['custom_6']) ? $data['custom_6'] : NULL ;
        $client->custom_7 = isset($data['custom_7']) ? $data['custom_7'] : NULL ;
        $client->custom_8 = isset($data['custom_8']) ? $data['custom_8'] : NULL ;
        $client->custom_9 = isset($data['custom_9']) ? $data['custom_9'] : NULL ;
        $client->custom_10 = isset($data['custom_10']) ? $data['custom_10'] : NULL ;

        $client->ip = isset($data['ip']) ? $data['ip'] : NULL;
        $client->created_at = isset($data['created_at']) ? date('c', strtotime($data['created_at'])) : date('c');
        $client->updated_at = date('c');
        $this->di['db']->store($client);
        return $client;
    }

    public function adminCreateClient(array $data)
    {
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminCreateClient', 'params'=>$data));
        $client = $this->createClient($data);
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminCreateClient', 'params'=>array('id'=>$client->id, 'password'=>$data['password'])));
        $this->di['logger']->info('Created new client #%s', $client->id);

        return $client->id;
    }

    public function guestCreateClient(array $data)
    {
        $event_params = $data;
        $event_params['ip'] = $this->di['request']->getClientAddress();
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientSignUp', 'params'=>$event_params));

        $data['ip'] = $this->di['request']->getClientAddress();
        $data['status'] = \Model_Client::ACTIVE;
        $client = $this->createClient($data);

        $this->di['events_manager']->fire(array('event'=>'onAfterClientSignUp', 'params'=>array('id'=>$client->id, 'password'=>$data['password'])));
        $this->di['logger']->info('Client #%s signed up', $client->id);

        return $client;
    }

    public function remove(\Model_Client $model)
    {
        $service = $this->di['mod_service']('Order');
        $service->rmByClient($model);
        $service = $this->di['mod_service']('Invoice');
        $service->rmByClient($model);
        $service = $this->di['mod_service']('Support');
        $service->rmByClient($model);
        $service = $this->di['mod_service']('Client', 'Balance');

        $table = $this->di['table']('ActivityClientHistory');
        $table->rmByClient($model);

        $service->rmByClient($model);
        $service = $this->di['mod_service']('Email');
        $service->rmByClient($model);
        $service = $this->di['mod_service']('Activity');
        $service->rmByClient($model);

        $table = $this->di['table']('ForumTopicMessage');
        $table->rmByClient($model);

        $table = $this->di['table']('ClientPasswordReset');
        $table->rmByClient($model);


        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare('DELETE FROM extension_meta WHERE client_id = :id');
        $stmt->execute(array('id'=>$model->id));

        $this->di['db']->trash($model);
    }

    public function authorizeClient($email, $plainTextPassword)
    {
        $model = $this->di['db']->findOne('Client', 'email = ? AND status = ?', array($email, \Model_Client::ACTIVE));
        if ($model == null){
            return null;
        }

        $config = $this->di['mod_config']('client');
        if (isset($config['require_email_confirmation']) && (int)$config['require_email_confirmation']) {
            if (!$model->email_approved) {
                throw new \Box_Exception('Please check your mailbox and confirm email address.');
            }
        }

        return $this->di['auth']->authorizeUser($model, $plainTextPassword);
    }
}