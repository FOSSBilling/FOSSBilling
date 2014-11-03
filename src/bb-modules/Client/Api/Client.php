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
     * Get currencly logged in client details
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
     * @optional string $document_type - Related document type, ie: passpord, driving license
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
    public function update($data)
    {
        $client = $this->getIdentity();

        $event_params = $data;
        $event_params['id'] = $client->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientProfileUpdate', 'params'=>$event_params));
        
        $mod = $this->di['mod']('client');
        $config = $mod->getConfig();
        if(isset($data['email']) 
                && $client->email != $data['email'] 
                && isset($config['allow_change_email']) 
                && !$config['allow_change_email']) {
            throw new \Box_Exception('Email can not be changed');
        }
        
        if(isset($data['email'])) {
            $this->di['validator']->isEmailValid($data['email']);

            if($this->getService()->emailAreadyRegistered($data['email'], $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }
            
            $client->email = strtolower(trim($data['email']));
        }

        if(isset($data['first_name'])) {
            $client->first_name = $data['first_name'];
        }

        if(isset($data['last_name'])) {
            $client->last_name = $data['last_name'];
        }

        if(isset($data['gender'])) {
            $client->gender = $data['gender'];
        }

        if(isset($data['birthday'])) {
            $client->birthday = $data['birthday'];
        }

        if(isset($data['company'])) {
            $client->company = $data['company'];
        }
        
        if(isset($data['company_vat'])) {
            $client->company_vat = $data['company_vat'];
        }
        
        if(isset($data['company_number'])) {
            $client->company_number = $data['company_number'];
        }
        
        if(isset($data['type'])) {
            $client->type = $data['type'];
        }

        if(isset($data['address_1'])) {
            $client->address_1 = $data['address_1'];
        }

        if(isset($data['address_2'])) {
            $client->address_2 = $data['address_2'];
        }

        if(isset($data['phone_cc'])) {
            $client->phone_cc = $data['phone_cc'];
        }

        if(isset($data['phone'])) {
            $client->phone = $data['phone'];
        }

        if(isset($data['country'])) {
            $client->country = $data['country'];
        }

        if(isset($data['postcode'])) {
            $client->postcode = $data['postcode'];
        }

        if(isset($data['city'])) {
            $client->city = $data['city'];
        }

        if(isset($data['state'])) {
            $client->state = $data['state'];
        }

        if(isset($data['document_type'])) {
            $client->document_type = $data['document_type'];
        }

        if(isset($data['document_nr'])) {
            $client->document_nr = $data['document_nr'];
        }

        if(isset($data['notes'])) {
            $client->notes = $data['notes'];
        }

        if(isset($data['custom_1'])) {
            $client->custom_1 = $data['custom_1'];
        }
        if(isset($data['custom_2'])) {
            $client->custom_2 = $data['custom_2'];
        }
        if(isset($data['custom_3'])) {
            $client->custom_3 = $data['custom_3'];
        }
        if(isset($data['custom_4'])) {
            $client->custom_4 = $data['custom_4'];
        }
        if(isset($data['custom_5'])) {
            $client->custom_5 = $data['custom_5'];
        }
        if(isset($data['custom_6'])) {
            $client->custom_6 = $data['custom_6'];
        }
        if(isset($data['custom_7'])) {
            $client->custom_7 = $data['custom_7'];
        }
        if(isset($data['custom_8'])) {
            $client->custom_8 = $data['custom_8'];
        }
        if(isset($data['custom_9'])) {
            $client->custom_9 = $data['custom_9'];
        }
        if(isset($data['custom_10'])) {
            $client->custom_10 = $data['custom_10'];
        }

        $client->updated_at = date('c');
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
        $client->updated_at = date('c');
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
        if(!isset($data['password'])) {
            throw new \Box_Exception('Password required');
        }

        if(!isset($data['password_confirm'])) {
            throw new \Box_Exception('Password confirmation required');
        }

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match.');
        }

        $event_params = $data;
        $event_params['id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientProfilePasswordChange', 'params'=>$event_params));
        
        $this->di['validator']->isPasswordStrong($data['password']);

        $client = $this->getIdentity();
        $client->pass = sha1($data['password']);
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
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $balance             = $this->di['db']->getExistingModelById('ClientBalance', $item['id'], 'Balance not found');
            $pager['list'][$key] = $service->toApiArray($balance);
        }

        return $pager;
    }

}