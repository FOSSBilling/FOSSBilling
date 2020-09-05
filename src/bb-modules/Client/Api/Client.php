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
 *Client management 
 */

namespace Box\Mod\Client\Api;

class Client extends \Api_Abstract
{
    /**
     * Get currently logged in client details
     * @deprecated moved to profile module
     */
    public function get()
    {
        return $this->getService()->toApiArray($this->getIdentity(), true, $this->getIdentity());
    }

    /**
     * Update currencty logged in client details
     * 
     * @optional string $email - new client email. Must not exist on system
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
     * @return boolean
     * @throws Exception 
     * @deprecated moved to profile module
     */
    public function update($data = array())
    {
        $client = $this->getIdentity();

        $event_params =  $data;
        $event_params['id'] = $client->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientProfileUpdate', 'params'=>$event_params));

        $email = $this->di['array_get']($data, 'email', '');
        if(!empty($email)) {
            $this->di['validator']->isEmailValid($email);

            $this->getService()->canChangeEmail($client, $email);

            if($this->getService()->emailAreadyRegistered($email, $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }
            $client->email = strtolower(trim($email));
        }

        $phoneCC = $this->di['array_get']($data, 'phone_cc', $client->phone_cc);
        if(!empty($phoneCC)){
            $client->phone_cc = intval($phoneCC);
        }

        $client->first_name     = $this->di['array_get']($data, 'first_name', $client->first_name);
        $client->last_name      = $this->di['array_get']($data, 'last_name', $client->last_name);
        $client->gender         = $this->di['array_get']($data, 'gender', $client->gender);
        $client->birthday       = $this->di['array_get']($data, 'birthday', $client->birthday);
        $client->company        = $this->di['array_get']($data, 'company', $client->company);
        $client->company_vat    = $this->di['array_get']($data, 'company_vat', $client->company_vat);
        $client->company_number = $this->di['array_get']($data, 'company_number', $client->company_number);
        $client->type           = $this->di['array_get']($data, 'type', $client->type);
        $client->address_1      = $this->di['array_get']($data, 'address_1', $client->address_1);
        $client->address_2      = $this->di['array_get']($data, 'address_2', $client->address_2);
        $client->phone          = $this->di['array_get']($data, 'phone', $client->phone);
        $client->country        = $this->di['array_get']($data, 'country', $client->country);
        $client->postcode       = $this->di['array_get']($data, 'postcode', $client->postcode);
        $client->city           = $this->di['array_get']($data, 'city', $client->city);
        $client->state          = $this->di['array_get']($data, 'state', $client->state);
        $client->document_type  = $this->di['array_get']($data, 'document_type', 'passport');
        $client->document_nr    = $this->di['array_get']($data, 'document_nr', '');
        $client->notes     = $this->di['array_get']($data, 'notes', $client->notes);

        $client->custom_1  = $this->di['array_get']($data, 'custom_1', $client->custom_1);
        $client->custom_2  = $this->di['array_get']($data, 'custom_2', $client->custom_2);
        $client->custom_3  = $this->di['array_get']($data, 'custom_3', $client->custom_3);
        $client->custom_4  = $this->di['array_get']($data, 'custom_4', $client->custom_4);
        $client->custom_5  = $this->di['array_get']($data, 'custom_5', $client->custom_5);
        $client->custom_6  = $this->di['array_get']($data, 'custom_6', $client->custom_6);
        $client->custom_7  = $this->di['array_get']($data, 'custom_7', $client->custom_7);
        $client->custom_8  = $this->di['array_get']($data, 'custom_8', $client->custom_8);
        $client->custom_9  = $this->di['array_get']($data, 'custom_9', $client->custom_9);
        $client->custom_10 = $this->di['array_get']($data, 'custom_10', $client->custom_10);

        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);

        $this->di['events_manager']->fire(array('event'=>'onAfterClientProfileUpdate', 'params'=>array('id'=>$client->id)));

        $this->di['logger']->info('Updated profile');
        return true;
    }
    
    /**
     * Retrieve current API key
     * @deprecated moved to profile module
     */
    public function api_key_get($data)
    {
        $client = $this->getIdentity();
        return $client->api_token;
    }
    
    /**
     * Generate new API key
     * @deprecated moved to profile module
     */
    public function api_key_reset($data)
    {
        $client = $this->getIdentity();
        
        $client->api_token = $this->di['tools']->generatePassword(32);
        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);

        $this->di['logger']->info('Generated new API key');
        return $client->api_token;
    }
    
    /**
     * Change client area password
     * @deprecated moved to profile module
     */
    public function change_password($data)
    {
        $required = array(
            'password'         => 'Password required',
            'password_confirm' => 'Password confirmation required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match.');
        }

        $event_params = $data;
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientProfilePasswordChange', 'params'=>$event_params));
        
        $this->di['validator']->isPasswordStrong($data['password']);

        $client = $this->getIdentity();
        $client->pass = $this->di['password']->hashIt($data['password']);
        $this->di['db']->store($client);

        $this->di['events_manager']->fire(array('event'=>'onAfterClientProfilePasswordChange', 'params'=>array('id'=>$client->id)));
        
        $this->di['logger']->info('Changed profile password');
        
        return true;
    }

    /**
     * Clear session and logout
     * @return boolean 
     * @deprecated moved to profile module
     */
    public function logout()
    {
        if($_COOKIE) { // testing env fix
            $this->di['cookie']->set('BOXCLR', "", time() - 3600, '/');
        }
        $this->di['session']->delete('client');
        $this->di['session']->delete('client_id');
        $this->di['logger']->info('Logged out');
        return true;
    }
    
    /**
     * Get payments information
     * @return array 
     */
    public function balance_get_list($data)
    {
        $service           = $this->di['mod_service']('Client', 'Balance');
        $data['client_id'] = $this->identity->id;

        list($q, $params) = $service->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $balance             = $this->di['db']->getExistingModelById('ClientBalance', $item['id'], 'Balance not found');
            $pager['list'][$key] = $service->toApiArray($balance);
        }

        return $pager;
    }

    /**
     * Get client balance
     * @return float
     */
    public function balance_get_total()
    {
        $service = $this->di['mod_service']('Client', 'Balance');
        return $service->getClientBalance($this->identity);
    }

    public function is_taxable()
    {
        return $this->getService()->isClientTaxable($this->identity);
    }

}