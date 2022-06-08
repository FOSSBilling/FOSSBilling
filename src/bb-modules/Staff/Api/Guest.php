<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
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
     * bb-config.php file must already be present and configured.
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
        $validator->isEmailValid($data['email']);
        $validator->isPasswordStrong($data['password']);

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

        $config = $this->getMod()->getConfig();

        // check ip
        if (isset($config['allowed_ips']) && isset($config['check_ip']) && $config['check_ip']) {
            $allowed_ips = explode(PHP_EOL, $config['allowed_ips']);
            if (!empty($allowed_ips)) {
                $allowed_ips = array_map('trim', $allowed_ips);
                if (!in_array($this->getIp(), $allowed_ips)) {
                    throw new \Box_Exception('You are not allowed to login to admin area from :ip address', [':ip' => $this->getIp()], 403);
                }
            }
        }

        return $this->getService()->login($data['email'], $data['password'], $this->getIp());
    }
}
