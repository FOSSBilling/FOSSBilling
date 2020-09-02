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
 * Admin profile management
 */

namespace Box\Mod\Profile\Api;

class Admin extends \Api_Abstract
{
    /**
     * Returns currently logged in staff member profile information
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
     *		[email] => demo@boxbilling.com
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
     * Clear session data and logout from system
     * 
     * @return boolean 
     */
    public function logout()
    {
        $this->di['cookie']->delete('BOXADMR');
        $this->di['session']->delete('admin');
        $this->di['logger']->info('Admin logged out');
        return true;
    }

    /**
     * Update currently logged in staff member details
     * 
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $signature - new signature
     * 
     * @return boolean
     * @throws Exception 
     */
    public function update($data)
    {
        return $this->getService()->updateAdmin($this->getIdentity(), $data);
    }

    /**
     * Generates new API token for currently logged in staff member
     * 
     * @return boolean 
     */
    public function generate_api_key($data)
    {
        return $this->getService()->generateNewApiKey($this->getIdentity());
    }

    /**
     * Change password for currently logged in staff member
     * 
     * @param string $password - new password
     * @param string $password_confirm - repeat new password
     * @return boolean
     * @throws Exception 
     */
    public function change_password($data)
    {
        if(!isset($data['password'])) {
            throw new \Exception('Password required');
        }

        if(!isset($data['password_confirm'])) {
            throw new \Exception('Password confirmation required');
        }

        if($data['password'] != $data['password_confirm']) {
            throw new \Exception('Passwords do not match');
        }

        $this->di['validator']->isPasswordStrong($data['password']);

        return $this->getService()->changeAdminPassword($this->getIdentity(), $data['password']);
    }
}