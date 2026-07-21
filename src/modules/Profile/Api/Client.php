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
 *Client profile management.
 */

namespace Box\Mod\Profile\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get currently logged in client details.
     */
    public function get()
    {
        $clientService = $this->getDi()['mod_service']('client');

        return $clientService->toApiArray($this->getIdentity(), true, $this->getIdentity());
    }

    /**
     * Update currently logged in client details.
     *
     * @optional string $email - new client email. Must not exist on system
     * @optional string $billing_email - optional address for invoice notifications
     * @optional string $last_name - last name
     * @optional string $aid - Alternative id. Usually used by import tools.
     * @optional string $gender - Gender - values: male|female|nonbinary|other
     * @optional string $country - Country
     * @optional string $city - city
     * @optional string $birthday - Birthday
     * @optional string $company - Company
     * @optional string $company_vat - Company VAT number
     * @optional string $company_number - Company number
     * @optional string $type - Identifies client type: company or individual
     * @optional string $address_1 - Address line 1
     * @optional string $address_2 - Address line 2
     * @optional string $postcode - zip or postcode
     * @optional string $state - country state
     * @optional string $phone - Phone number
     * @optional string $phone_cc - Phone country code
     * @optional string $notes - Notes about client. Visible for admin only
     * @optional string $lang - language option
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the client.
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
     * @optional string $custom_11 - Custom field 11
     * @optional string $custom_12 - Custom field 12
     * @optional string $custom_13 - Custom field 13
     * @optional string $custom_14 - Custom field 14
     * @optional string $custom_15 - Custom field 15
     * @optional string $custom_16 - Custom field 16
     * @optional string $custom_17 - Custom field 17
     * @optional string $custom_18 - Custom field 18
     * @optional string $custom_19 - Custom field 19
     * @optional string $custom_20 - Custom field 20
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

        if (array_key_exists('billing_email', $data)) {
            $data['billing_email'] = empty($data['billing_email'])
                ? null
                : $this->getDi()['tools']->validateAndSanitizeEmail($data['billing_email']);
        }

        return $this->getService()->updateClient($this->getIdentity(), $data);
    }

    /**
     * Retrieve current API key.
     */
    public function api_key_get($data)
    {
        $client = $this->getIdentity();

        return $client->getApiToken();
    }

    /**
     * Generate new API key.
     */
    public function api_key_reset($data)
    {
        return $this->getService()->resetApiKey($this->getIdentity());
    }

    /**
     * Change password for currently logged in client.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams([
        'current_password' => 'Current password required',
        'new_password' => 'New password required',
        'confirm_password' => 'New password confirmation required',
    ])]
    public function change_password($data)
    {
        $this->getDi()['validator']->isPasswordStrong($data['new_password']);
        $this->getDi()['validator']->passwordsMatch($data, 'new_password', 'confirm_password');

        $client = $this->getIdentity();

        $this->getDi()['rate_limiter']->consumeOrThrow('profile_password_change_ip', (string) $this->getIp());
        $this->getDi()['rate_limiter']->consumeOrThrow('profile_password_change_account', 'client:' . $client->getId());

        if (!$this->getDi()['password']->verify($data['current_password'], $client->getPass())) {
            throw new \FOSSBilling\InformationException('Current password incorrect');
        }

        $this->getService()->invalidateSessions();

        return $this->getService()->changeClientPassword($client, $data['new_password']);
    }

    /**
     * Clear session and logout.
     *
     * @return bool
     */
    public function logout()
    {
        return $this->getService()->logoutClient();
    }

    /**
     * Used to destroy / invalidate all existing sessions for the current client.
     */
    public function destroy_sessions(array $data): bool
    {
        return $this->getService()->invalidateSessions();
    }
}
