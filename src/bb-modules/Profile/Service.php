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

        if (isset($data['email'])) {
            $admin->email = $data['email'];
        }

        if (isset($data['name'])) {
            $admin->name = $data['name'];
        }

        if (isset($data['signature'])) {
            $admin->signature = $data['signature'];
        }

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
            'created_at'     => $identity->created_at,
            'updated_at'     => $identity->updated_at,
        );
    }

    public function updateClient(\Model_Client $client, array $data)
    {
        $event_params       = $data;
        $event_params['id'] = $client->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientProfileUpdate', 'params' => $event_params));

        $mod    = $this->di['mod']('client');
        $config = $mod->getConfig();
        if (isset($data['email'])
            && $client->email != $data['email']
            && isset($config['allow_change_email'])
            && !$config['allow_change_email']
        ) {
            throw new \Box_Exception('Email can not be changed');
        }

        if (isset($data['email'])) {
            $validator = $this->di['validator'];
            $validator->isEmailValid($data['email']);

            $clientService = $this->di['mod_service']('client');
            if ($clientService->emailAreadyRegistered($data['email'], $client)) {
                throw new \Box_Exception('Can not change email. It is already registered.');
            }

            $client->email = $data['email'];
        }

        if (isset($data['first_name'])) {
            $client->first_name = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $client->last_name = $data['last_name'];
        }

        if (isset($data['gender'])) {
            $client->gender = $data['gender'];
        }

        if (isset($data['birthday'])) {
            if (strlen(trim($data['birthday'])) > 0 && strtotime($data['birthday']) === false) {
                throw new \Box_Exception('Invalid birth date value');
            }
            $client->birthday = $data['birthday'];
        }

        if (isset($data['company'])) {
            $client->company = $data['company'];
        }

        if (isset($data['company_vat'])) {
            $client->company_vat = $data['company_vat'];
        }

        if (isset($data['company_number'])) {
            $client->company_number = $data['company_number'];
        }

        if (isset($data['type'])) {
            $client->type = $data['type'];
        }

        if (isset($data['address_1'])) {
            $client->address_1 = $data['address_1'];
        }

        if (isset($data['address_2'])) {
            $client->address_2 = $data['address_2'];
        }

        if (isset($data['phone_cc'])) {
            $client->phone_cc = $data['phone_cc'];
        }

        if (isset($data['phone'])) {
            $client->phone = $data['phone'];
        }

        if (isset($data['country'])) {
            $client->country = $data['country'];
        }

        if (isset($data['postcode'])) {
            $client->postcode = $data['postcode'];
        }

        if (isset($data['city'])) {
            $client->city = $data['city'];
        }

        if (isset($data['state'])) {
            $client->state = $data['state'];
        }

        if (isset($data['document_type'])) {
            $client->document_type = $data['document_type'];
        }

        if (isset($data['document_nr'])) {
            $client->document_nr = $data['document_nr'];
            if (!isset($data['document_type'])){
                $client->document_type = 'passport';
            }
        }

        if (isset($data['lang'])) {
            $client->lang = $data['lang'];
        }

        if (isset($data['notes'])) {
            $client->notes = $data['notes'];
        }

        if (isset($data['custom_1'])) {
            $client->custom_1 = $data['custom_1'];
        }
        if (isset($data['custom_2'])) {
            $client->custom_2 = $data['custom_2'];
        }
        if (isset($data['custom_3'])) {
            $client->custom_3 = $data['custom_3'];
        }
        if (isset($data['custom_4'])) {
            $client->custom_4 = $data['custom_4'];
        }
        if (isset($data['custom_5'])) {
            $client->custom_5 = $data['custom_5'];
        }
        if (isset($data['custom_6'])) {
            $client->custom_6 = $data['custom_6'];
        }
        if (isset($data['custom_7'])) {
            $client->custom_7 = $data['custom_7'];
        }
        if (isset($data['custom_8'])) {
            $client->custom_8 = $data['custom_8'];
        }
        if (isset($data['custom_9'])) {
            $client->custom_9 = $data['custom_9'];
        }
        if (isset($data['custom_10'])) {
            $client->custom_10 = $data['custom_10'];
        }

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