<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
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
     * @return bool
     */
    public function create($data)
    {
        $allow = (!is_countable($this->di['db']->findOne('Admin', '1=1')) || count($this->di['db']->findOne('Admin', '1=1')) == 0);
        if (!$allow) {
            throw new \FOSSBilling\InformationException('Administrator account already exists', null, 55);
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
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function login($data)
    {
        $required = [
            'email' => 'Email required',
            'password' => 'Password required',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email'], true, false);

        $config = $this->getMod()->getConfig();

        // check ip
        if (isset($config['allowed_ips']) && isset($config['check_ip']) && $config['check_ip']) {
            $allowed_ips = explode(PHP_EOL, $config['allowed_ips']);
            if ($allowed_ips) {
                $allowed_ips = array_map('trim', $allowed_ips);
                if (!in_array($this->getIp(), $allowed_ips)) {
                    throw new \FOSSBilling\InformationException('You are not allowed to login to admin area from :ip address', [':ip' => $this->getIp()], 403);
                }
            }
        }

        $result = $this->getService()->login($data['email'], $data['password'], $this->getIp());
        $this->di['session']->delete('redirect_uri');

        return $result;
    }

    public function update_password($data)
    {
        $config = $this->getMod()->getConfig();
        if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
            throw new \FOSSBilling\InformationException('Password reset has been disabled');
        }
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
        $required = [
            'code' => 'Code required',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        ];

        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);

        if ($data['password'] != $data['password_confirm']) {
            throw new \FOSSBilling\InformationException('Passwords do not match');
        }

        $reset = $this->di['db']->findOne('AdminPasswordReset', 'hash = ?', [$data['code']]);
        if (!$reset instanceof \Model_AdminPasswordReset) {
            throw new \FOSSBilling\InformationException('The link have expired or you have already confirmed password reset.');
        }

        if (strtotime($reset->created_at) - time() + 900 < 0) {
            throw new \FOSSBilling\InformationException('The link have expired or you have already confirmed password reset.');
        }

        $c = $this->di['db']->getExistingModelById('Admin', $reset->admin_id, 'User not found');
        $c->pass = $this->di['password']->hashIt($data['password']);
        $this->di['db']->store($c);

        $this->di['logger']->info('Admin user requested password reset. Sent to email %s', $c->email);

        // send email
        $email = [];
        $email['to_admin'] = $c->id;
        $email['code'] = 'mod_staff_password_reset_approve';
        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);

        $this->di['db']->trash($reset);
    }

    public function passwordreset($data)
    {
        $config = $this->getMod()->getConfig();
        if (isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0') {
            throw new \FOSSBilling\InformationException('Password reset has been disabled');
        }
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
        $required = [
            'email' => 'Email required',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        $c = $this->di['db']->findOne('Admin', 'email = ?', [$data['email']]);

        if (!$c instanceof \Model_Admin) {
            return true;
        }
        $hash = hash('sha256', time() . random_bytes(13));

        $c->pass = $hash;

        $reset = $this->di['db']->dispense('AdminPasswordReset');
        $reset->admin_id = $c->id;
        $reset->ip = $this->ip;
        $reset->hash = $hash;
        $reset->created_at = date('Y-m-d H:i:s');
        $reset->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($reset);

        // send email
        $email = [];
        $email['to_admin'] = $c->id;
        $email['code'] = 'mod_staff_password_reset_request';
        $email['hash'] = $hash;
        $emailService = $this->di['mod_service']('email');
        $emailService->sendTemplate($email);

        $this->di['logger']->info('Admin user requested password reset. Sent to email %s', $c->email);
    }
}
