<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 *Staff methods.
 */

namespace Box\Mod\Staff\Api;

class Guest extends \Api_Abstract
{
    /**
     * Gives ability to create administrator account if no admins exists on
     * the system.
     * Database structure must be installed before calling this action.
     * config.php file must already be present and configured.
     * Used by automated FOSSBilling installer.
     *
     * @param string $email    - admin email
     * @param string $password - admin password
     *
     * @return bool
     */
    public function create($data)
    {
        $allow = (!is_countable($this->di['db']->findOne('Admin', '1=1')) || 0 == count($this->di['db']->findOne('Admin', '1=1')));
        if (!$allow) {
            throw new \Box_Exception('Administrator account already exists', null, 55);
        }
        $required = [
            'email' => 'Administrator email is missing.',
            'password' => 'Administrator password is missing.',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $validator->isPasswordStrong($data['password']);

        if (!is_null($data['email'])) {
            $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        }

        $result = $this->getService()->createAdmin($data);
        if ($result) {
            $this->login($data);
        }

        return true;
    }

    /**
     * Login to admin area and save information to session.
     *
     * @param string $email    - admin email
     * @param string $password - admin password
     *
     * @optional string $remember  - pass value "1" to create remember me cookie
     *
     * @return array
     *
     * @throws Exception
     */
    public function login($data)
    {
        $required = [
            'email' => 'Email required',
            'password' => 'Password required',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $config = $this->getMod()->getConfig();

        // check ip
        if (isset($config['allowed_ips']) && isset($config['check_ip']) && $config['check_ip']) {
            $allowed_ips = explode(PHP_EOL, $config['allowed_ips']);
            if ($allowed_ips) {
                $allowed_ips = array_map('trim', $allowed_ips);
                if (!in_array($this->getIp(), $allowed_ips)) {
                    throw new \Box_Exception('You are not allowed to login to admin area from :ip address', [':ip' => $this->getIp()], 403);
                }
            }
        }

        return $this->getService()->login($data['email'], $data['password'], $this->getIp());
    }

    public function passwordreset($data){
        $required = [
            'email' => 'Email required',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        $c = $this->di['db']->findOne('Admin', 'email = ?', [$data['email']]);

        if (!$c instanceof \Model_Admin) {
            throw new \Box_Exception('Email not found in our database');
        }
        $hash = hash('sha256', time().random_bytes(13));

        $c->pass = $hash;

        $reset = $this->di['db']->dispense('ClientPasswordReset');
        $reset->client_id = $c->id;
        $reset->ip = $this->ip;
        $reset->hash = $hash;
        $reset->created_at = date('Y-m-d H:i:s');
        $reset->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($reset);

        // send email
        $email = [];
        $email['to_client'] = $reset->client_id;
        $email['code'] = 'mod_staff_password_reset_request';
        $email['hash'] = $hash;
        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);

        $this->di['logger']->info('Admin user requested password reset. Sent to email %s', $c->email);
    }
}
