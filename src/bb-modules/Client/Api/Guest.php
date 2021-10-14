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
 * Clients API methods
 */

namespace Box\Mod\Client\Api;

class Guest extends \Api_Abstract
{
    /**
     * Client signup action. 
     * 
     * @param string $email - Email
     * @param string $first_name - First name
     * @param string $password - password
     * @param string $password_confirm - must be same as password
     * 
     * @optional bool $auto_login - Auto login client after signup
     * 
     * @optional string $last_name - last name
     * @optional string $aid - Alternative id. Usually used by import tools.
     * @optional string $gender - Gender - values: male|female
     * @optional string $country - Country
     * @optional string $city - city
     * @optional string $birthday - Birthday
     * @optional string $type - Identifies client type: company or individual
     * @optional string $company - Company
     * @optional string $company_vat - Company VAT number
     * @optional string $company_number - Company number
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
     */
    public function create($data = array())
    {
        $config = $this->di['mod_config']('client');

        if(isset($config['allow_signup']) && !$config['allow_signup']) {
            throw new \Box_Exception('New registrations are temporary disabled');
        }

        $required = array(
            'email' => 'Email required',
            'first_name' => 'First name required',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if($data['password'] != $data['password_confirm']) {
            throw new \Box_Exception('Passwords do not match.');
        }

        $this->getService()->checkExtraRequiredFields($data);
        $this->getService()->checkCustomFields($data);

        $this->di['validator']->isPasswordStrong($this->di['array_get']($data, 'password'));
        $service = $this->getService();

        $email = $this->di['array_get']($data, 'email');
        $this->di['validator']->isEmailValid($email);
        $email = strtolower(trim($email));
        if($service->clientAlreadyExists($email)) {
            throw new \Box_Exception('Email is already registered. You may want to login instead of registering.');
        }

        $client = $service->guestCreateClient($data);

        if (isset($config['require_email_confirmation']) && (int)$config['require_email_confirmation'] && !$client->email_approved) {
            throw new \Box_Exception('Account has been created. Please check your mailbox and confirm email address.', null,  7777);
        }

        if($this->di['array_get']($data, 'auto_login', 0)) {
            try {
                $this->login(array('email'=>$client->email, 'password' => $this->di['array_get']($data, 'password')));
            } catch(\Exception $e) {
                error_log($e->getMessage());
            }
        }

        return (int) $client->id;
    }

    /**
     * Client login action
     * 
     * @param string $email - client email
     * @param string $password - client password
     * 
     * @return array - session data
     * @throws Box_Exception 
     */
    public function login($data)
    {
        $required = array(
            'email'         => 'Email required',
            'password' => 'Password required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $event_params = $data;
        $event_params['ip'] = $this->ip;
        $this->di['events_manager']->fire(array('event'=>'onBeforeClientLogin', 'params'=>$event_params));

        $service = $this->getService();
        $client = $service->authorizeClient($data['email'], $data['password']);
        if(!$client instanceof \Model_Client ) {
            
            $this->di['events_manager']->fire(array('event'=>'onEventClientLoginFailed', 'params'=>$event_params));
            
            throw new \Box_Exception('Please check your login details', array(), 403);
        }
        
        if(isset($data['remember'])) {
            $email = $data['email'];
            $cookie_time = (3600 * 24 * 30); // 30 days
            $this->di['cookie']->set('BOXCLR', 'e='.base64_encode($email).'&p='.base64_encode($client->pass), time() + $cookie_time, '/');
        }
        
        $this->di['events_manager']->fire(array('event'=>'onAfterClientLogin', 'params'=>array('id'=>$client->id, 'ip'=>$this->ip)));
        
        $result = $service->toSessionArray($client);
        $this->di['session']->set('client_id', $client->id);

        $this->di['logger']->info('Client #%s logged in', $client->id);
        return $result;
    }

    /**
     * Password reset confirmation email will be sent to email.
     * 
     * @param string $email - client email
     * @return boolean
     * @throws Box_Exception 
     */
    public function reset_password($data)
    {
        $required = array(
            'email'         => 'Email required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $this->di['events_manager']->fire(array('event'=>'onBeforeGuestPasswordResetRequest', 'params'=>$data));

        $c = $this->di['db']->findOne('Client', 'email = ?', array($data['email']));
        if(!$c instanceof \Model_Client ) {
            throw new \Box_Exception('Email not found in our database');
        }

        $hash = sha1(time() . uniqid());
        
        $reset = $this->di['db']->dispense('ClientPasswordReset');
        $reset->client_id   = $c->id;
        $reset->ip          = $this->ip;
        $reset->hash        = $hash;
        $reset->created_at  = date('Y-m-d H:i:s');
        $reset->updated_at  = date('Y-m-d H:i:s');
        $this->di['db']->store($reset);

        //send email
        $email = array();
        $email['to_client'] = $c->id;
        $email['code']      = 'mod_client_password_reset_request';
        $email['hash']      = $hash;
        $emailSerivce = $this->di['mod_service']('email');
        $emailSerivce->sendTemplate($email);
        
        $this->di['logger']->info('Client requested password reset. Sent to email %s', $c->email);
        return true;
    }

    /**
     * Confirm password reset action
     * 
     * @param string $hash - hash received in email
     * @return boolean
     * @throws Box_Exception 
     */
    public function confirm_reset($data)
    {
        $required = array(
            'hash'         => 'Hash required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $reset = $this->di['db']->findOne('ClientPasswordReset', 'hash = ?', array($data['hash']));
        if(!$reset instanceof \Model_ClientPasswordReset) {
            throw new \Box_Exception('The link have expired or you have already confirmed password reset.');
        }

        $new_pass = substr(md5(time() . uniqid()), 0, 10);
        
        $c = $this->di['db']->getExistingModelById('Client', $reset->client_id, 'Client not found');
        $c->pass = $this->di['password']->hashIt($new_pass);
        $this->di['db']->store($c);
        
        //send email
        $email = array();
        $email['to_client'] = $reset->client_id;
        $email['code']      = 'mod_client_password_reset_approve';
        $email['password']  = $new_pass;
        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);

        $this->di['db']->trash($reset);
        $this->di['logger']->info('Client password reset request was approved');
        return true;
    }
    
    /**
     * Check if given vat number is valid EU country VAT number
     * This method uses http://isvat.appspot.com/ method to validate VAT
     * 
     * @param string $country - Country CODE: FR - France etc.
     * @param string $vat - VAT number
     * 
     * @return bool- true if VAT is valid, false if not
     */
    public function is_vat($data)
    {
        $required = array(
            'country' => 'Country code',
            'vat'     => 'Country VAT is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $cc     = $data['country'];
        $vatnum = $data['vat'];

        //@todo add new service provider https://vatlayer.com/ check
//         $url    = 'http://isvat.appspot.com/' . rawurlencode($cc) . '/' . rawurlencode($vatnum) . '/';
//         $result = $this->di['guzzle_client']->get($url);
        return true;
    }
    
    /**
     * List of required fields for client registration
     */
    public function required()
    {
        $config = $this->di['mod_config']('client');
        return isset($config['required']) ? $config['required'] : array();
    }

    /**
     * Array of custom fields for client registration
     */
    public function custom_fields()
    {
        $config = $this->di['mod_config']('client');
        return isset($config['custom_fields']) ? $config['custom_fields'] : array();
    }
}