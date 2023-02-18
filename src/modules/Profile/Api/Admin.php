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
 * Admin profile management.
 */

namespace Box\Mod\Profile\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns currently logged in staff member profile information.
     *
     * @return array
     *
     * @example
     * <code class="response">
     * Array
     * (
     * 		[id] => 1
     *		[role] => staff
     *		[admin_group_id] => 1
     *		[email] => demo@fossbilling.org
     *		[pass] => 89e495e7941cf9e40e6980d14a16bf023ccd4c91
     *		[name] => Demo Administrator
     *		[signature] => Sincerely Yours, Demo Administrator
     * 		[status] => active
     *		[api_token] => 29baba87f1c120f1b7fc6b0139167003
     *		[created_at] => 1310024416
     *		[updated_at] => 1310024416
     * )
     * </code>
     */
    public function get()
    {
        return $this->getService()->getAdminIdentityArray($this->getIdentity());
    }

    /**
     * Clear session data and logout from system.
     *
     * @return bool
     */
    public function logout()
    {
        $this->di['cookie']->delete('BOXADMR');
        $this->di['session']->delete('admin');
        $this->di['logger']->info('Admin logged out');

        return true;
    }

    /**
     * Update currently logged in staff member details.
     *
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $signature - new signature
     *
     * @return bool
     *
     * @throws Exception
     */
    public function update($data)
    {
        if (!is_null($data['email'])) {
            $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);
        }

        return $this->getService()->updateAdmin($this->getIdentity(), $data);
    }

    /**
     * Generates new API token for currently logged in staff member.
     *
     * @return bool
     */
    public function generate_api_key($data)
    {
        return $this->getService()->generateNewApiKey($this->getIdentity());
    }

    /**
     * Change password for currently logged in staff member.
     *
     * @param string $current_password - staff member current password
     * @param string $new_password     - staff member new password
     * @param string $confirm_password - staff member new password confirmation
     *
     * @return bool
     *
     * @throws Exception
     */
    public function change_password($data)
    {
        $required = [
            'current_password' => 'Current password required',
            'new_password' => 'New password required',
            'confirm_password' => 'New password confirmation required',
        ];
        $validator = $this->di['validator'];
        $validator->checkRequiredParamsForArray($required, $data);
        $validator->isPasswordStrong($data['new_password']);

        if ($data['new_password'] != $data['confirm_password']) {
            throw new \Exception('Passwords do not match');
        }

        $staff = $this->getIdentity();

        if(!$this->di['password']->verify($data['current_password'], $staff->pass)) {
            throw new \Exception('Current password incorrect');
        }

        return $this->getService()->changeAdminPassword($staff, $data['new_password']);
    }
}
