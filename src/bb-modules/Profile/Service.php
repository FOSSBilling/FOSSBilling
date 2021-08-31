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


namespace Box\Mod\Profile;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function changeAdminPassword(\Model_Admin $admin, $new_password)
    {
        $event_params = array();
        $event_params['password'] = $new_password;
        $event_params['id']       = $admin->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminStaffProfilePasswordChange', 'params' => $event_params));

        $admin->pass       = $this->di['password']->hashIt($new_password);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params       = array();
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(array('event' => 'onAfterAdminStaffProfilePasswordChange', 'params' => $event_params));

        $this->di['logger']->info('Changed profile password');

        return true;
    }

    public function generateNewApiKey(\Model_Admin $admin)
    {
        $event_params       = array();
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminStaffApiKeyChange', 'params' => $event_params));

        $admin->api_token  = $this->di['tools']->generatePassword(32);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $this->di['events_manager']->fire(array('event' => 'onAfterAdminStaffApiKeyChange', 'params' => $event_params));

        $this->di['logger']->info('Generated new API key');

        return true;
    }

    public function updateAdmin(\Model_Admin $admin, array $data)
    {
        $event_params       = $data;
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeAdminStaffProfileUpdate', 'params' => $event_params));

        $admin->email = $this->di['array_get']($data, 'email', $admin->email);
        $admin->name = $this->di['array_get']($data, 'name', $admin->name);
        $admin->signature = $this->di['array_get']($data, 'signature', $admin->signature);
        $admin->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($admin);

        $event_params       = array();
        $event_params['id'] = $admin->id;
        $this->di['events_manager']->fire(array('event' => 'onAfterAdminStaffProfileUpdate', 'params' => $event_params));

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function getAdminIdentityArray(\Model_Admin $identity)
    {
        return array(
            'id'             => $identity->id,
            'role'           => $identity->role,
            'admin_group_id' => $identity->admin_group_id,
            'email'          => $identity->email,
            'name'           => $identity->name,
            'signature'      => $identity->signature,
            'status'         => $identity->status,
            'api_token'      => $identity->api_token,
            'created_at'     => $identity->created_at,
            'updated_at'     => $identity->updated_at,
        );
    }

    public function updateClient(\Model_Client $client, array $data = array())
    {
        $event_params       = $data;
        $event_params['id'] = $client->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientProfileUpdate', 'params' => $event_params));

        $mod    = $this->di['mod']('client');
        $config = $mod->getConfig();
        $email = $this->di['array_get']($data, 'email', '');
        if ($client->email != $email
            && isset($config['allow_change_email'])
            && !$config['allow_change_email']
        ) {
            throw new \Box_Exception('Email can not be changed');
        }

        if (!empty($email)) {
            $validator = $this->di['validator'];
            $validator->isEmailValid($email);

            $clientService = $this->di['mod_service']('client');
            if ($clientService->emailAreadyRegistered($email, $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }

            $client->email = $email;
        }

        $client->first_name = $this->di['array_get']($data, 'first_name', $client->first_name);
        $client->last_name  = $this->di['array_get']($data, 'last_name', $client->last_name);
        $client->gender     = $this->di['array_get']($data, 'gender', $client->gender);

        $birthday = $this->di['array_get']($data, 'birthday');
        if (strlen(trim($birthday)) > 0 && strtotime($birthday) === false) {
            throw new \Box_Exception('Invalid birth date value');
        }
        $client->birthday = $birthday;

        $client->company        = $this->di['array_get']($data, 'company', $client->company);
        $client->company_vat    = $this->di['array_get']($data, 'company_vat', $client->company_vat);
        $client->company_number = $this->di['array_get']($data, 'company_number', $client->company_number);
        $client->type           = $this->di['array_get']($data, 'type', $client->type);
        $client->address_1      = $this->di['array_get']($data, 'address_1', $client->address_1);
        $client->address_2      = $this->di['array_get']($data, 'address_2', $client->address_2);
        $client->phone_cc       = $this->di['array_get']($data, 'phone_cc', $client->phone_cc);
        $client->phone          = $this->di['array_get']($data, 'phone', $client->phone);
        $client->country        = $this->di['array_get']($data, 'country', $client->country);
        $client->postcode       = $this->di['array_get']($data, 'postcode', $client->postcode);
        $client->city           = $this->di['array_get']($data, 'city', $client->city);
        $client->state          = $this->di['array_get']($data, 'state', $client->state);
        $client->document_type  = $this->di['array_get']($data, 'document_type', $client->document_type);
        $client->document_nr    = $this->di['array_get']($data, 'document_nr', $client->document_nr);

        if (isset($client->document_nr)) {
            $client->document_type = $this->di['array_get']($data, 'document_type ', 'passport');
        }
        $client->lang      = $this->di['array_get']($data, 'lang', $client->lang);
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

        $this->di['events_manager']->fire(array('event' => 'onAfterClientProfileUpdate', 'params' => array('id' => $client->id)));

        $this->di['logger']->info('Updated profile');

        return true;
    }

    public function resetApiKey(\Model_Client $client)
    {
        $client->api_token  = $this->di['tools']->generatePassword(32);
        $client->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($client);

        $this->di['logger']->info('Generated new API key');

        return $client->api_token;
    }

    public function changeClientPassword(\Model_Client $client, $password)
    {
        $event_params = array();
        $event_params['password'] = $password;
        $event_params['id']       = $client->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientProfilePasswordChange', 'params' => $event_params));

        $this->di['validator']->isPasswordStrong($password);

        $client->pass = $this->di['password']->hashIt($password);
        $this->di['db']->store($client);

        $this->di['events_manager']->fire(array('event' => 'onAfterClientProfilePasswordChange', 'params' => array('id' => $client->id)));

        $this->di['logger']->info('Changed profile password');

        return true;
    }

    public function logoutClient(){
        if($_COOKIE) { // testing env fix
            $this->di['cookie']->set('BOXCLR', "", time() - 3600, '/');
        }
        $this->di['session']->delete('client');
        $this->di['session']->delete('client_id');
        $this->di['logger']->info('Logged out');
        return true;
    }
}