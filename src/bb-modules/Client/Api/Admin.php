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

/**
 * Client management
 */

namespace Box\Mod\Client\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get list of clients
     *
     * @optional string $status - Filters client by status. Available options: active, suspended, canceled
     *
     * @return array - list of clients in paginated manner
     */
    public function get_list($data)
    {
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        list($sql, $params) = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

        foreach($pager['list'] as $key => $clientArr){
            $client = $this->di['db']->getExistingModelById('Client', $clientArr['id'], 'Client not found');
            $pager['list'][$key] = $this->getService()->toApiArray($client, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get clients index
     *
     * @return array - list of clients in id => full name pair
     */
    public function get_pairs($data)
    {
        $service = $this->di['mod_service']('client');
        return $service->getPairs($data);
    }

    /**
     * Get client by id or email. Email is also unique in database
     *
     * @param int $id - client ID
     * 
     * @optional string $email - client email
     * 
     * @return array - client details
     */
    public function get($data)
    {
        $service = $this->getService();
        $client = $service->get($data);
        return $service->toApiArray($client, true, $this->getIdentity());
    }

    /**
     * Login to clients area with client id
     *
     * @param int $id - client ID
     *
     * @return array - client details
     */
    public function login($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('ID required');
        }

        $db = $this->di['db'];
        $client = $db->load('Client', $data['id']);
        if(!$client instanceof \Model_Client ) {
            throw new \Box_Exception('Client not found');
        }

        $service = $this->di['mod_service']('client');
        $result = $service->toSessionArray($client);

        $session = $this->di['session'];
        $session->set('client_id', $client->id);
        $this->di['logger']->info('Logged in as client #%s', $client->id);
        return $result;
    }

    /**
     * Creates new client
     *
     * @param string $email - client email, must not be registered on system
     * @param string $first_name - client first name
     * 
     * @optional string $password - client password
     * @optional string $auth_type - client authorization type. Default null
     * @optional string $last_name - client last name
     * @optional string $aid - alternative ID. If you import cients from other systems you can use this field to store foreign system ID
     * @optional string $group_id - client group id
     * @optional string $status - client status: "active, suspended, canceled"
     * @optional string $created_at - ISO 8601 date for client creation date
     * @optional string $last_name - last name
     * @optional string $aid - Alternative id. Usually used by import tools.
     * @optional string $gender - Gender - values: male|female
     * @optional string $country - Country
     * @optional string $city - city
     * @optional string $birthday - Birthday
     * @optional string $company - Company
     * @optional string $company_vat - Company VAT number
     * @optional string $company_number - Company number
     * @optional string $type - Identifies client type: company or individual
     * @optional string $address_1 - Address line 1
     * @optional string $address_2 - Address line 2
     * @optional string $postcode - zip or postcode
     * @optional string $state - country state
     * @optional string $phone - Phone number
     * @optional string $phone_cc - Phone country code
     * @optional string $document_type - Related document type, ie: passpord, driving license
     * @optional string $document_nr - Related document number, ie: passport number: LC45698122
     * @optional string $notes - Notes about client. Visible for admin only
     * @optional string $lang - Client language
     * @optional string $custom_1 - Custom field 1
     * @optional string $custom_2 - Custom field 2
     * @optional string $custom_3 - Custom field 3
     * @optional string $custom_4 - Custom field 4
     * @optional string $custom_5 - Custom field 5
     * @optional string $custom_6 - Custom field 6
     * @optional string $custom_7 - Custom field 7
     * @optional string $custom_8 - Custom field 8
     * @optional string $custom_9 - Custom field 9
     * @optional string $custom_10 - Custom field 10
     * 
     * @return int - client id
     */
    public function create($data)
    {
        if(!isset($data['email']) || empty($data['email'])) {
            throw new \Box_Exception('Email required');
        }

        if(!isset($data['first_name'])) {
            throw new \Box_Exception('First name is required');
        }

        $validator = $this->di['validator'];
        $validator->isEmailValid($data['email']);

        $service = $this->getService();
        if($service->emailAreadyRegistered($data['email'])) {
            throw new \Box_Exception('Email is already registered.');
        }

        return $service->adminCreateClient($data);
    }

    /**
     * Deletes client from system
     *
     * @param string $id - client ID
     *
     * @return bool
     */
    public function delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Client id is missing');
        }

        $model = $this->di['db']->load('Client', $data['id']);
        if(!$model instanceof \Model_Client) {
            throw new \Box_Exception('Client not found');
        }
        
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminClientDelete', 'params'=>array('id'=>$model->id)));

        $id = $model->id;
        $this->getService()->remove($model);
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminClientDelete', 'params'=>array('id'=>$id)));
        
        $this->di['logger']->info('Removed client #%s', $id);
        return true;
    }

    /**
     * Update client profile
     *
     * @param string $id - client ID
     * 
     * @optional string $email - client email
     * @optional string $first_name - client first_name
     * @optional string $last_name - client last_name
     * @optional string $status - client status
     * @optional string $last_name - last name
     * @optional string $aid - Alternative id. Usually used by import tools.
     * @optional string $gender - Gender - values: male|female
     * @optional string $country - Country
     * @optional string $city - city
     * @optional string $birthday - Birthday
     * @optional string $company - Company
     * @optional string $company_vat - Company VAT number
     * @optional string $company_number - Company number
     * @optional string $type - Identifies client type: company or individual
     * @optional string $address_1 - Address line 1
     * @optional string $address_2 - Address line 2
     * @optional string $postcode - zip or postcode
     * @optional string $state - country state
     * @optional string $phone - Phone number
     * @optional string $phone_cc - Phone country code
     * @optional string $document_type - Related document type, ie: passpord, driving license
     * @optional string $document_nr - Related document number, ie: passport number: LC45698122
     * @optional string $lang - Client language
     * @optional string $notes - Notes about client. Visible for admin only
     * @optional string $custom_1 - Custom field 1
     * @optional string $custom_2 - Custom field 2
     * @optional string $custom_3 - Custom field 3
     * @optional string $custom_4 - Custom field 4
     * @optional string $custom_5 - Custom field 5
     * @optional string $custom_6 - Custom field 6
     * @optional string $custom_7 - Custom field 7
     * @optional string $custom_8 - Custom field 8
     * @optional string $custom_9 - Custom field 9
     * @optional string $custom_10 - Custom field 10
     * 
     * @return bool
     */
    public function update($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Id required');
        }

        $client = $this->di['db']->load('Client', $data['id']);
        if(!$client instanceof \Model_Client ) {
            throw new \Box_Exception('Client not found');
        }
        
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminClientUpdate', 'params'=>$data));
        $service = $this->di['mod_service']('client');
        
        $email        = isset($data['email']) ? $data['email'] : NULL ;
        $first_name        = isset($data['first_name']) ? $data['first_name'] : NULL ;
        $last_name        = isset($data['last_name']) ? $data['last_name'] : NULL ;
        $aid        = isset($data['aid']) ? $data['aid'] : NULL ;
        $gender     = isset($data['gender']) ? $data['gender'] : NULL ;
        $birthday   = isset($data['birthday']) ? $data['birthday'] : NULL ;
        $company   = isset($data['company']) ? $data['company'] : NULL ;
        $company_vat   = isset($data['company_vat']) ? $data['company_vat'] : NULL ;
        $address_1   = isset($data['address_1']) ? $data['address_1'] : NULL ;
        $address_2   = isset($data['address_2']) ? $data['address_2'] : NULL ;
        $phone_cc   = isset($data['phone_cc']) ? $data['phone_cc'] : NULL ;
        $phone   = isset($data['phone']) ? $data['phone'] : NULL ;
        $document_type      = isset($data['document_type']) ? $data['document_type'] : NULL ;
        $document_nr      = isset($data['document_nr']) ? $data['document_nr'] : NULL ;
        $notes      = isset($data['notes']) ? $data['notes'] : NULL ;
        $country      = isset($data['country']) ? $data['country'] : NULL ;
        $postcode      = isset($data['postcode']) ? $data['postcode'] : NULL ;
        $state      = isset($data['state']) ? $data['state'] : NULL ;
        $city      = isset($data['city']) ? $data['city'] : NULL ;
        $currency      = isset($data['currency']) ? $data['currency'] : NULL ;
        $status      = isset($data['status']) ? $data['status'] : NULL ;
        $tax_exempt  = isset($data['tax_exempt']) ? (bool)$data['tax_exempt'] : NULL;
        $created_at  = isset($data['created_at']) ? $data['created_at'] : NULL;

        $c1      = isset($data['custom_1']) ? $data['custom_1'] : NULL ;
        $c2      = isset($data['custom_2']) ? $data['custom_2'] : NULL ;
        $c3      = isset($data['custom_3']) ? $data['custom_3'] : NULL ;
        $c4      = isset($data['custom_4']) ? $data['custom_4'] : NULL ;
        $c5      = isset($data['custom_5']) ? $data['custom_5'] : NULL ;
        $c6      = isset($data['custom_6']) ? $data['custom_6'] : NULL ;
        $c7      = isset($data['custom_7']) ? $data['custom_7'] : NULL ;
        $c8      = isset($data['custom_8']) ? $data['custom_8'] : NULL ;
        $c9      = isset($data['custom_9']) ? $data['custom_9'] : NULL ;
        $c10     = isset($data['custom_10']) ? $data['custom_10'] : NULL ;

        if($currency && $service->canChangeCurrency($client, $currency)) {
            $client->currency = $currency;
        }

        if(isset($data['group_id'])) {
            $client->client_group_id = $data['group_id'];
        }

        if(!is_null($email)) {
            $this->di['validator']->isEmailValid($email);
            if($service->emailAreadyRegistered($email, $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }
            $client->email = $email;
        }

        if(!is_null($aid)) {
            $client->aid = $aid;
        }
        if(!is_null($first_name)) {
            $client->first_name = $first_name;
        }
        if(!is_null($last_name)) {
            $client->last_name = $last_name;
        }
        if(!is_null($status)) {
            $client->status = $status;
        }
        if(!is_null($gender)) {
            $client->gender = $gender;
        }
        if(!is_null($birthday)) {
            if (strlen(trim($data['birthday'])) > 0 && strtotime($data['birthday']) == false) {
                throw new \Box_Exception('Invalid birth date value');
            }
            $client->birthday = $birthday;
        }
        if(!is_null($phone_cc)) {
            $client->phone_cc = $phone_cc;
        }
        if(!is_null($phone)) {
            $client->phone = $phone;
        }
        if(!is_null($company)) {
            $client->company = $company;
        }
        if(!is_null($company_vat)) {
            $client->company_vat = $company_vat;
        }
        if(isset($data['company_number'])) {
            $client->company_number = $data['company_number'];
        }
        if(isset($data['type'])) {
            $client->type = $data['type'];
        }
        if(isset($data['lang'])) {
            $client->lang = $data['lang'];
        }
        if(!is_null($address_1)) {
            $client->address_1 = $address_1;
        }
        if(!is_null($address_2)) {
            $client->address_2 = $address_2;
        }
        if(!is_null($city)) {
            $client->city = $city;
        }
        if(!is_null($state)) {
            $client->state = $state;
        }
        if(!is_null($postcode)) {
            $client->postcode = $postcode;
        }
        if(!is_null($country)) {
            $client->country = $country;
        }
        if(!is_null($document_type)) {
            $client->document_type = $document_type;
        }
        if(!is_null($document_nr)) {
            $client->document_nr = $document_nr;
        }
        if(!is_null($notes)) {
            $client->notes = $notes;
        }
        if(!is_null($tax_exempt)) {
            $client->tax_exempt = $tax_exempt;
        }
        if(!is_null($created_at)) {
            $client->created_at = date('Y-m-d H:i:s', strtotime($created_at));
        }

        if(!is_null($c1)) {
            $client->custom_1 = $c1;
        }
        if(!is_null($c2)) {
            $client->custom_2 = $c2;
        }
        if(!is_null($c3)) {
            $client->custom_3 = $c3;
        }
        if(!is_null($c4)) {
            $client->custom_4 = $c4;
        }
        if(!is_null($c5)) {
            $client->custom_5 = $c5;
        }
        if(!is_null($c6)) {
            $client->custom_6 = $c6;
        }
        if(!is_null($c7)) {
            $client->custom_7 = $c7;
        }
        if(!is_null($c8)) {
            $client->custom_8 = $c8;
        }
        if(!is_null($c9)) {
            $client->custom_9 = $c9;
        }
        if(!is_null($c10)) {
            $client->custom_10 = $c10;
        }

        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminClientUpdate', 'params'=>array('id'=>$client->id)));
        
        $this->di['logger']->info('Updated client #%s profile', $client->id);
        return true;
    }

    /**
     * Change client password
     *
     * @param int $id - Client ID
     * @param string $password - new client password
     * @param string $password_confirm - repeast same new client password
     *
     * @return bool
     */
    public function change_password($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Client ID is required');
        }

        if(!isset($data['password'])) {
            throw new \Box_Exception('Password required');
        }
        if(!isset($data['password_confirm'])) {
            throw new \Box_Exception('Password confirmation required');
        }

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match');
        }

        $client = $this->di['db']->load('Client', $data['id']);
        if(!$client instanceof \Model_Client ) {
            throw new \Box_Exception('Client not found');
        }

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminClientPasswordChange', 'params'=>$data));

        $client->pass = $this->di['password']->hashIt($data['password']);
        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);
        
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminClientPasswordChange', 'params'=>array('id'=>$client->id, 'password'=>$data['password'])));
        
        $this->di['logger']->info('Changed client #%s password', $client->id);
        return true;
    }

    /**
     * Returns list of client payments
     *
     * @return array
     */
    public function balance_get_list($data)
    {
        $service = $this->di['mod_service']('Client', 'Balance');
        list($q, $params) = $service->getSearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager =  $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach($pager['list'] as $key => $item){
            $pager['list'][$key] = array(
                'id'            =>  isset($item['id']) ? $item['id'] : '',
                'description'   =>  isset($item['description']) ? $item['description'] : '',
                'amount'        =>  isset($item['amount']) ? $item['amount'] : '',
                'currency'      =>  isset($item['currency']) ? $item['currency'] : '',
                'created_at'    =>  isset($item['created_at']) ? $item['created_at'] : '',
            );
        }

        return $pager;
    }

    /**
     * Remove row from clients balance
     *
     * @param int $id - Balance line id
     *
     * @return bool
     */
    public function balance_delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Client ID is required');
        }
        $model = $this->di['db']->load('ClientBalance', $data['id']);
        if(!$model instanceof \Model_ClientBalance) {
            throw new \Box_Exception('Balance line not found');
        }
        
        $id = $model->id;
        $client_id = $model->client_id;
        $amount = $model->amount;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed line %s from client #%s balance for %s', $id, $client_id, $amount);
        return true;
    }
    
    /**
     * Adds funds to clients balance
     *
     * @param int $id - Client ID
     * @param int $amount - Amount of clients currency to added to balance
     * @param int $description - Descrition of this transaction
     * 
     * @optional string $type - Related item type
     * @optional string $rel_id - Related item id
     *
     * @return bool
     */
    public function balance_add_funds($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Client ID is required');
        }

        if(!isset($data['amount'])) {
            throw new \Box_Exception('Amount is required');
        }
        
        if(!isset($data['description'])) {
            throw new \Box_Exception('Description is required');
        }

        $client = $this->di['db']->load('Client', $data['id']);
        if(!$client instanceof \Model_Client ) {
            throw new \Box_Exception('Client not found');
        }

        $service = $this->di['mod_service']('client');
        $service->addFunds($client, $data['amount'], $data['description'], $data);
        return true;
    }

    /**
     * Remove password reminders which were not confirmed in 2 hours
     *
     * @return bool
     */
    public function batch_expire_password_reminders()
    {
        $service = $this->di['mod_service']('client');
        $expired = $service->getExpiredPasswordReminders();
        foreach($expired as $model) {
            $this->di['db']->trash($model);
        }
        
        $this->di['logger']->info('Executed action to delete expired clients password reminders');
        return TRUE;
    }

    /**
     * Get list of clients logins history
     *
     * @optional int $client_id - filter by client
     * 
     * @return array
     */
    public function login_history_get_list($data)
    {
        list($q, $params) = $this->getService()->getHistorySearchQuery($data);
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager =  $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach($pager['list'] as $key => $item){
            $pager['list'][$key] = array(
                'id'            =>  isset($item['id']) ? $item['id'] : '',
                'ip'            =>  isset($item['ip']) ? $item['ip'] : '',
                'created_at'    =>  isset($item['created_at']) ? $item['created_at'] : '',
                'client'        =>  array(
                    'id'            =>  isset($item['client_id']) ? $item['client_id'] : '',
                    'first_name'    => isset($item['first_name']) ? $item['first_name'] : '',
                    'last_name'     =>  isset($item['last_name']) ? $item['last_name'] : '',
                    'email'         =>  isset($item['email']) ? $item['email'] : '',
                )
            );
        }

        return $pager;
    }

    /**
     * Remove log entry form clients logins history
     * 
     * @param int $id - Log entry ID
     * 
     * @return bool
     */
    public function login_history_delete($data)
    {

        $required = array(
            'id' => 'Id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('ActivityClientHistory', $data['id']);

        if(!$model instanceof \Model_ActivityClientHistory) {
            throw new \Box_Exception('Event not found');
        }
        $this->di['db']->trash($model);
        return true;
    }

    /**
     * Return client statuses with counter.
     * 
     * @return array
     */
    public function get_statuses($data)
    {
        $service = $this->di['mod_service']('client');
        return $service->counter();
    }

    /**
     * Return client groups. Id and title pairs
     * 
     * @return array
     */
    public function group_get_pairs($data)
    {
        $service = $this->di['mod_service']('client');
        return $service->getGroupPairs();
    }

    /**
     * Create new clients group
     * 
     * @param string $title - New group title
     * 
     * @return int $id - newly created group id
     */
    public function group_create($data)
    {
        if(!isset($data['title']) || empty($data['title'])) {
            throw new \Box_Exception('Group title is missing');
        }

        return $this->getService()->createGroup($data);
    }

    /**
     * Update client group
     *  
     * @param int $id - client group ID
     * @optional string $title - new group title
     * 
     * @return bool
     * @throws ErrorException 
     */
    public function group_update($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Group id is missing');
        }

        $model = $this->di['db']->load('ClientGroup', $data['id']);
        if(!$model instanceof \Model_ClientGroup) {
            throw new \Box_Exception('Group not found');
        }
        
        if(isset($data['title'])) {
            $model->title = $data['title'];
        }
        
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    /**
     * Delete client group
     *  
     * @param int $id - client group ID
     * 
     * @return bool
     * @throws ErrorException 
     */
    public function group_delete($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Group id is missing');
        }

        $model = $this->di['db']->load('ClientGroup', $data['id']);
        if(!$model instanceof \Model_ClientGroup) {
            throw new \Box_Exception('Group not found');
        }
        return $this->getService()->deleteGroup($model);
    }

    /**
     * Get client group details
     *  
     * @param int $id - client group ID
     * 
     * @return array
     * @throws ErrorException 
     */
    public function group_get($data)
    {
        if(!isset($data['id'])) {
            throw new \Box_Exception('Group id is missing');
        }

        $model = $this->di['db']->load('ClientGroup', $data['id']);
        if(!$model instanceof \Model_ClientGroup) {
            throw new \Box_Exception('Group not found');
        }
        
        return $this->di['db']->toArray($model);
    }

    /**
     * Deletes clients with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->delete(array('id' => $id));
        }

        return true;
    }


    /**
     * Deletes client login logs with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_log($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->login_history_delete(array('id' => $id));
        }

        return true;
    }
}