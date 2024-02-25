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
        unset($_COOKIE['BOXADMR']);
        $this->di['session']->destroy('admin');
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
     * @throws \FOSSBilling\Exception
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
     * @return bool
     *
     * @throws \FOSSBilling\Exception
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
            throw new \FOSSBilling\InformationException('Passwords do not match');
        }

        $staff = $this->getIdentity();

        if (!$this->di['password']->verify($data['current_password'], $staff->pass)) {
            throw new \FOSSBilling\InformationException('Current password incorrect');
        }

        $this->getService()->invalidateSessions();

        return $this->getService()->changeAdminPassword($staff, $data['new_password']);
    }

    /**
     * Used to destroy / invalidate all existing sessions for a given user.
     *
     * @param array $data An array with the options.
     *                    The array can contain the following sub-keys:
     *                    - string|null $data['type'] The user type (admin or staff) (optional).
     *                    - id|null $data['id'] The session ID (optional).
     */
    public function destroy_sessions(array $data): bool
    {
        $data['type'] ??= null;
        $data['id'] ??= null;

        return $this->getService()->invalidateSessions($data['type'], $data['id']);
    }

    /**
     * Generate new API key for a given client.
     *
     * @return string the new API key for the client
     */
    public function api_key_reset($data): string
    {
        $required = [
            'id' => 'Client ID not passed',
        ];

        $validator = $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $client = $this->di['db']->getExistingModelById('Client', $data['di']);

        return $this->getService()->resetApiKey($client);
    }
}
