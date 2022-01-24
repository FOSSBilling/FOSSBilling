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
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
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
        $required = array(
            'id' => 'ID required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

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
     * @optional string $aid - alternative ID. If you import clients from other systems you can use this field to store foreign system ID
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
     * @optional string $document_type - Related document type, ie: passport, driving license
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
        $required = array(
            'email'      => 'Email required',
            'first_name' => 'First name is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
        $required = array(
            'id' => 'Client id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');
        
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
     * @optional string $document_type - Related document type, ie: passport, driving license
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
    public function update($data = array())
    {
        $required = array('id' => 'Id required');
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $this->di['array_get']($data, 'id'), 'Client not found');

        $service = $this->di['mod_service']('client');

        if(!is_null($this->di['array_get']($data, 'email'))) {
            $email =  $this->di['array_get']($data, 'email');
            $this->di['validator']->isEmailValid($email);
            if($service->emailAreadyRegistered($email, $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }
        }
        $this->di['validator']->isBirthdayValid($this->di['array_get']($data, 'birthday'));

        if($this->di['array_get']($data, 'currency') && $service->canChangeCurrency($client, $this->di['array_get']($data, 'currency'))) {
            $client->currency = $this->di['array_get']($data, 'currency', $client->currency);
        }

        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminClientUpdate', 'params'=>$data));

        $phoneCC = $this->di['array_get']($data, 'phone_cc', $client->phone_cc);
        if(!empty($phoneCC)){
            $client->phone_cc = intval($phoneCC);
        }

        $client->email          = $this->di['array_get']($data, 'email', $client->email);
        $client->first_name     = $this->di['array_get']($data, 'first_name', $client->first_name);
        $client->last_name      = $this->di['array_get']($data, 'last_name', $client->last_name);
        $client->aid            = $this->di['array_get']($data, 'aid', $client->aid);
        $client->gender         = $this->di['array_get']($data, 'gender', $client->gender);
        $client->birthday       = $this->di['array_get']($data, 'birthday', $client->birthday);
        $client->company        = $this->di['array_get']($data, 'company', $client->company);
        $client->company_vat    = $this->di['array_get']($data, 'company_vat', $client->company_vat);
        $client->address_1      = $this->di['array_get']($data, 'address_1', $client->address_1);
        $client->address_2      = $this->di['array_get']($data, 'address_2', $client->address_2);
        $client->phone          = $this->di['array_get']($data, 'phone', $client->phone);
        $client->document_type  = $this->di['array_get']($data, 'document_type', $client->document_type);
        $client->document_nr    = $this->di['array_get']($data, 'document_nr', $client->document_nr);
        $client->notes          = $this->di['array_get']($data, 'notes', $client->notes);
        $client->country        = $this->di['array_get']($data, 'country', $client->country);
        $client->postcode       = $this->di['array_get']($data, 'postcode', $client->postcode);
        $client->state          = $this->di['array_get']($data, 'state', $client->state);
        $client->city           = $this->di['array_get']($data, 'city', $client->city);

        $client->status         = $this->di['array_get']($data, 'status', $client->status);
        $client->email_approved = $this->di['array_get']($data, 'email_approved', $client->email_approved);
        $client->tax_exempt     = $this->di['array_get']($data, 'tax_exempt', $client->tax_exempt);
        $client->created_at     = $this->di['array_get']($data, 'created_at', $client->created_at);

        $client->custom_1      = $this->di['array_get']($data, 'custom_1', $client->custom_1);
        $client->custom_2      = $this->di['array_get']($data, 'custom_2', $client->custom_2);
        $client->custom_3      = $this->di['array_get']($data, 'custom_3', $client->custom_3);
        $client->custom_4      = $this->di['array_get']($data, 'custom_4', $client->custom_4);
        $client->custom_5      = $this->di['array_get']($data, 'custom_5', $client->custom_5);
        $client->custom_6      = $this->di['array_get']($data, 'custom_6', $client->custom_6);
        $client->custom_7      = $this->di['array_get']($data, 'custom_7', $client->custom_7);
        $client->custom_8      = $this->di['array_get']($data, 'custom_8', $client->custom_8);
        $client->custom_9      = $this->di['array_get']($data, 'custom_9', $client->custom_9);
        $client->custom_10     = $this->di['array_get']($data, 'custom_10', $client->custom_10);

        $client->client_group_id = $this->di['array_get']($data, 'group_id', $client->client_group_id);
        $client->company_number = $this->di['array_get']($data, 'company_number', $client->company_number);
        $client->type = $this->di['array_get']($data, 'type', $client->type);
        $client->lang = $this->di['array_get']($data, 'lang', $client->lang);

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
     * @param string $password_confirm - repeat same new client password
     *
     * @return bool
     */
    public function change_password($data)
    {
        $required = array(
            'id'               => 'ID required',
            'password'         => 'Password required',
            'password_confirm' => 'Password confirmation required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match');
        }

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

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
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager =  $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach($pager['list'] as $key => $item){
            $pager['list'][$key] = array(
                'id'            =>  $item['id'],
                'description'   =>  $item['description'],
                'amount'        =>  $item['amount'],
                'currency'      =>  $item['currency'],
                'created_at'    =>  $item['created_at'],
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
        $required = array(
            'id' => 'Client ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientBalance', $data['id'], 'Balance line not found');
        
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
     * @param int $description - Description of this transaction
     * 
     * @optional string $type - Related item type
     * @optional string $rel_id - Related item id
     *
     * @return bool
     */
    public function balance_add_funds($data)
    {
        $required = array(
            'id'          => 'Client ID required',
            'amount'      => 'Amount is required',
            'description' => 'Description is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

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
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager =  $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach($pager['list'] as $key => $item){
            $pager['list'][$key] = array(
                'id'            =>  $item['id'],
                'ip'            =>  $item['ip'],
                'created_at'    =>  $item['created_at'],
                'client'        =>  array(
                    'id'            => $item['client_id'],
                    'first_name'    => $item['first_name'],
                    'last_name'     => $item['last_name'],
                    'email'         => $item['email'],
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
        $required = array(
            'title' => 'Group title is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');
        
        $model->title = $this->di['array_get']($data, 'title', $model->title);
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
        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');
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
        $required = array(
            'id' => 'Group id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');
        
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