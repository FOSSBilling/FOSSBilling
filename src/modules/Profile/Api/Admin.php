<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Admin profile management.
 */

namespace Box\Mod\Profile\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Staff\Entity\Admin as AdminEntity;
use FOSSBilling\InformationException;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
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
        return $this->getService()->getAdminIdentityArray($this->getAdminEntity());
    }

    /**
     * Clear session data and logout from system.
     */
    public function logout(): bool
    {
        $this->getDi()['session']->destroy('admin');
        $this->getDi()['logger']->info('Admin logged out');

        return true;
    }

    /**
     * Update currently logged in staff member details.
     *
     * @optional string $email - new email
     * @optional string $name - new name
     * @optional string $signature - new signature
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the staff member.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function update($data)
    {
        if (!is_null($data['email'] ?? null)) {
            $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);
        }

        return $this->getService()->updateAdmin($this->getAdminEntity(), $data);
    }

    /**
     * Generates new API token for currently logged in staff member.
     *
     * @return bool
     */
    public function generate_api_key($data)
    {
        return $this->getService()->generateNewApiKey($this->getAdminEntity());
    }

    /**
     * Change password for currently logged in staff member.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams([
        'current_password' => 'Current password is required',
        'new_password' => 'New password is required',
        'confirm_password' => 'New password confirmation is required',
    ])]
    public function change_password($data)
    {
        $newPassword = $data['new_password'] ?? null;
        $this->getDi()['validator']->isPasswordStrong($newPassword);

        if ($newPassword != $data['confirm_password']) {
            throw new InformationException('Passwords do not match');
        }

        $staff = $this->getAdminEntity();

        $this->getDi()['rate_limiter']->consumeOrThrow('profile_password_change_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('profile_password_change_account', 'admin:' . $staff->getId());

        if (!$this->getDi()['password']->verify($data['current_password'], $staff->getPass())) {
            throw new InformationException('Current password incorrect');
        }

        $this->getService()->invalidateSessions();

        return $this->getService()->changeAdminPassword($staff, $data['new_password']);
    }

    /**
     * Destroy / invalidate all existing sessions for the currently logged in user.
     */
    public function destroy_sessions(): bool
    {
        return $this->getService()->invalidateSessions();
    }

    /**
     * Generate new API key for a given client.
     *
     * @return string the new API key for the client
     */
    #[RequiredParams(['id' => 'Client ID was not passed'])]
    public function api_key_reset($data): string
    {
        $this->checkPermissions('client', 'manage_api_keys');

        $client = $this->getDi()['em']->getRepository(Client::class)->find($data['id'])
            ?? throw new InformationException('Client not found');

        return $this->getService()->resetApiKey($client);
    }

    private function getAdminEntity(): AdminEntity
    {
        return $this->resolveAdminEntity($this->getIdentity());
    }

    private function resolveAdminEntity(AdminEntity|\Model_Admin|\Model_Client|\Model_Guest $identity): AdminEntity
    {
        if ($identity instanceof AdminEntity) {
            return $identity;
        }

        $adminId = (int) ($identity->id ?? 0);
        $admin = $this->getDi()['em']->getRepository(AdminEntity::class)->find($adminId);
        if (!$admin instanceof AdminEntity) {
            throw new InformationException('Admin not found');
        }

        return $admin;
    }
}
