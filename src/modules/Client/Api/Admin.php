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
 * Client management.
 */

namespace Box\Mod\Client\Api;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Client\Entity\ClientBalance;
use Box\Mod\Client\Entity\ClientGroup;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Locales;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get a list of clients.
     *
     * @param array $data filtering options
     *
     * @return array list of clients in a paginated manner
     */
    public function get_list($data)
    {
        $this->checkPermissions('client', 'view');

        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $clientArr) {
            $client = $this->getDi()['em']->getRepository(Client::class)->find($clientArr['id']) ?? throw new InformationException('Client not found');
            $pager['list'][$key] = $this->getService()->toApiArray($client, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get client ID/name pairs.
     *
     * @param array $data Filtering options
     *
     * @return array List of client ID/name pairs
     */
    public function get_pairs($data)
    {
        $this->checkPermissions('client', 'view');

        $service = $this->getDi()['mod_service']('client');

        return $service->getPairs($data);
    }

    /**
     * Get client by id or email. Email is also unique in database.
     *
     * @optional string $email - client email
     *
     * @return array - client details
     */
    public function get($data)
    {
        $this->checkPermissions('client', 'view');

        $service = $this->getService();
        $client = $service->get($data);
        $includeSensitive = $this->getDi()['mod_service']('Staff')->hasPermission(null, 'client', 'manage_api_keys');

        return $service->toApiArray($client, true, $this->getIdentity(), $includeSensitive);
    }

    /**
     * Login to clients area with client id.
     *
     * @return array - client details
     */
    #[RequiredParams(['id' => 'ID required'])]
    public function login($data)
    {
        $this->checkPermissions('client', 'impersonate_login');

        $client = $this->getDi()['em']->getRepository(Client::class)->find($data['id']) ?? throw new InformationException('Client not found');

        $service = $this->getDi()['mod_service']('client');
        $result = $service->toSessionArray($client);

        $session = $this->getDi()['session'];
        $session->set('client_id', $client->getId());
        $this->getDi()['logger']->info('Logged in as client #%s', $client->getId());

        return $result;
    }

    /**
     * Creates new client.
     *
     * @optional string $password - client password
     * @optional string $billing_email - optional address for invoice notifications
     * @optional string $auth_type - client authorization type. Default null
     * @optional string $last_name - client last name
     * @optional string $aid - Custom client ID. If you import clients from other systems you can use this field to store the existing customer ID.
     * @optional string $group_id - client group id
     * @optional string $status - client status: "active, suspended, canceled"
     * @optional string $created_at - ISO 8601 date for client creation date
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
     * @optional string $lang - Client language
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
     * @return int - client id
     */
    #[RequiredParams(['email' => 'Email required', 'first_name' => 'First name is required'])]
    public function create($data)
    {
        $this->checkPermissions('client', 'create');

        $validator = $this->getDi()['validator'];
        $data['email'] = $this->getDi()['tools']->validateAndSanitizeEmail($data['email']);
        if (array_key_exists('billing_email', $data)) {
            $data['billing_email'] = empty($data['billing_email'])
                ? null
                : $this->getDi()['tools']->validateAndSanitizeEmail($data['billing_email']);
        }
        $data['send_welcome_email'] = Tools::normalizeBoolean($data['send_welcome_email'] ?? true, true);

        $service = $this->getService();
        if ($service->emailAlreadyRegistered($data['email'])) {
            throw new InformationException('This email address is already registered.');
        }

        $password = trim((string) ($data['password'] ?? ''));
        $status = $data['status'] ?? Client::ACTIVE;
        if (!$data['send_welcome_email'] && $password === '') {
            throw new InformationException('A password is required when the welcome email is disabled.');
        }

        if ($data['send_welcome_email'] && $status !== Client::ACTIVE) {
            throw new InformationException('Welcome email can only be sent for active clients.');
        }

        if ($password !== '') {
            $validator->isPasswordStrong($data['password']);
        }

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientCreate', 'params' => $data]);
        $id = $service->adminCreateClient($data);
        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientCreate', 'params' => $data]);

        return $id;
    }

    /**
     * Deletes client from system.
     */
    #[RequiredParams(['id' => 'Client ID is missing'])]
    public function delete($data): bool
    {
        $this->checkPermissions('client', 'delete');

        $model = $this->getDi()['em']->getRepository(Client::class)->find($data['id']) ?? throw new InformationException('Client not found');

        $clientId = $model->getId();

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientDelete', 'params' => ['id' => $clientId]]);

        $this->getService()->remove($model);
        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientDelete', 'params' => ['id' => $clientId]]);

        $this->getDi()['logger']->info('Removed client #%s', $clientId);

        return true;
    }

    /**
     * Update client profile.
     *
     * @optional string $email - client email
     * @optional string $billing_email - optional address for invoice notifications
     * @optional string $first_name - client first_name
     * @optional string $last_name - client last_name
     * @optional string $status - client status
     * @optional string $aid - Custom client ID. Usually used by import tools to store an existing customer ID.
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
     * @optional string $lang - Client language
     * @optional string $timezone - IANA timezone identifier (e.g. "America/New_York"). Used to localize dates and times shown to the client.
     * @optional string $notes - Notes about client. Visible for admin only
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
     */
    #[RequiredParams(['id' => 'Client ID was not passed'])]
    public function update($data = []): bool
    {
        $this->checkPermissions('client', 'edit_profile');

        $client = $this->getDi()['em']->getRepository(Client::class)->find($data['id']) ?? throw new InformationException('Client not found');

        $service = $this->getDi()['mod_service']('client');

        if (!is_null($data['email'] ?? null)) {
            $email = $data['email'];
            $email = $this->getDi()['tools']->validateAndSanitizeEmail($email);
            if ($service->emailAlreadyRegistered($email, $client)) {
                throw new InformationException('This email address is already registered.');
            }
        }

        if (array_key_exists('billing_email', $data)) {
            $data['billing_email'] = empty($data['billing_email'])
                ? null
                : $this->getDi()['tools']->validateAndSanitizeEmail($data['billing_email']);
        }

        if (!empty($data['birthday'])) {
            $this->getDi()['validator']->isBirthdayValid($data['birthday']);
        }

        if (($data['birthday'] ?? null) === '') {
            unset($data['birthday']);
        }

        $currency = $data['currency'] ?? null;
        if ($currency && $service->canChangeCurrency($client, $currency)) {
            $client->setCurrency($currency);
        }

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientUpdate', 'params' => $data]);

        // Special handling for the phone country codes
        $phoneCountryCode = $data['phone_cc'] ?? $client->getPhoneCc();
        if (!empty($phoneCountryCode)) {
            $client->setPhoneCc((string) Tools::validatePhoneCC($phoneCountryCode));
        }

        // Special handling for the phone number itself
        $phone = $data['phone'] ?? $client->getPhone();
        if (!empty($phone) && is_string($phone)) {
            $client->setPhone(Tools::validatePhoneNumber($phone));
        }

        $previousStatus = $client->getStatus();

        if (!empty($data['country']) && !Countries::exists($data['country'])) {
            throw new InformationException('Invalid country code: :code', [':code' => $data['country']]);
        }

        if (!empty($data['lang']) && !Locales::exists($data['lang'])) {
            throw new InformationException('Invalid locale code: :code', [':code' => $data['lang']]);
        }

        if (array_key_exists('timezone', $data) && $data['timezone'] !== null && $data['timezone'] !== '' && !in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new InformationException('Invalid timezone: :tz', [':tz' => $data['timezone']]);
        }

        $simpleFields = [
            'email' => 'setEmail',
            'first_name' => 'setFirstName',
            'last_name' => 'setLastName',
            'aid' => 'setAid',
            'gender' => 'setGender',
            'company' => 'setCompany',
            'company_vat' => 'setCompanyVat',
            'address_1' => 'setAddress1',
            'address_2' => 'setAddress2',
            'notes' => 'setNotes',
            'country' => 'setCountry',
            'postcode' => 'setPostcode',
            'state' => 'setState',
            'city' => 'setCity',
            'status' => 'setStatus',
            'client_group_id' => 'setClientGroupId',
            'company_number' => 'setCompanyNumber',
            'type' => 'setType',
            'lang' => 'setLang',
            'timezone' => 'setTimezone',
            'custom_1' => 'setCustom1',
            'custom_2' => 'setCustom2',
            'custom_3' => 'setCustom3',
            'custom_4' => 'setCustom4',
            'custom_5' => 'setCustom5',
            'custom_6' => 'setCustom6',
            'custom_7' => 'setCustom7',
            'custom_8' => 'setCustom8',
            'custom_9' => 'setCustom9',
            'custom_10' => 'setCustom10',
            'custom_11' => 'setCustom11',
            'custom_12' => 'setCustom12',
            'custom_13' => 'setCustom13',
            'custom_14' => 'setCustom14',
            'custom_15' => 'setCustom15',
            'custom_16' => 'setCustom16',
            'custom_17' => 'setCustom17',
            'custom_18' => 'setCustom18',
            'custom_19' => 'setCustom19',
            'custom_20' => 'setCustom20',
        ];

        foreach ($simpleFields as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $client->{$setter}($data[$field]);
            }
        }

        if (array_key_exists('email_approved', $data)) {
            $client->setEmailApproved((bool) $data['email_approved']);
        }

        if (array_key_exists('tax_exempt', $data)) {
            $client->setTaxExempt((bool) $data['tax_exempt']);
        }

        if (array_key_exists('birthday', $data) && $data['birthday'] !== null && $data['birthday'] !== '') {
            $client->setBirthday(new \DateTime($data['birthday']));
        } elseif (array_key_exists('birthday', $data)) {
            $client->setBirthday(null);
        }

        if (array_key_exists('created_at', $data) && $data['created_at'] !== null && $data['created_at'] !== '') {
            $client->setCreatedAt(new \DateTime($data['created_at']));
        } elseif (array_key_exists('created_at', $data)) {
            $client->setCreatedAt(null);
        }

        if (array_key_exists('billing_email', $data)) {
            $client->setBillingEmail($data['billing_email']);
        }

        if ($client->getStatus() !== Client::ACTIVE) {
            $client->setApiToken(null);
        }

        $this->getDi()['em']->persist($client);

        if ($client->getStatus() !== Client::ACTIVE && $previousStatus === Client::ACTIVE) {
            $profileService = $this->getDi()['mod_service']('profile');
            $profileService->invalidateSessions('client', (int) $client->getId());
        }

        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientUpdate', 'params' => ['id' => $client->getId()]]);

        $this->getDi()['logger']->info('Updated client #%s profile', $client->getId());

        return true;
    }

    /**
     * Change client password.
     */
    #[RequiredParams(['id' => 'ID required', 'password' => 'Password required', 'password_confirm' => 'Password confirmation required'])]
    public function change_password(array $data): bool
    {
        $this->checkPermissions('client', 'change_password');

        $this->getDi()['validator']->passwordsMatch($data);

        $this->getDi()['validator']->isPasswordStrong($data['password']);

        $client = $this->getDi()['em']->getRepository(Client::class)->find($data['id']) ?? throw new InformationException('Client not found');

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientPasswordChange', 'params' => ['id' => $client->getId()]]);

        $client->setPass($this->getDi()['password']->hashIt($data['password']));
        $this->getDi()['em']->persist($client);

        $profileService = $this->getDi()['mod_service']('profile');
        $profileService->invalidateSessions('client', (int) $data['id']);

        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientPasswordChange', 'params' => ['id' => $client->getId()]]);

        $this->getDi()['logger']->info('Changed client #%s password', $client->getId());

        return true;
    }

    /**
     * Returns list of client payments.
     *
     * @return array
     */
    public function balance_get_list($data)
    {
        $this->checkPermissions('client', 'manage_balance');

        $service = $this->getDi()['mod_service']('Client', 'Balance');
        [$q, $params] = $service->getSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($q, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'],
                'description' => $item['description'],
                'amount' => $item['amount'],
                'currency' => $item['currency'],
                'created_at' => $item['created_at'],
            ];
        }

        return $pager;
    }

    /**
     * Remove row from clients balance.
     */
    #[RequiredParams(['id' => 'Client ID was not passed'])]
    public function balance_delete($data): bool
    {
        $this->checkPermissions('client', 'manage_balance');

        $model = $this->getDi()['em']->getRepository(ClientBalance::class)->find($data['id']) ?? throw new InformationException('Balance line not found');

        $id = $model->id;
        $client_id = $model->client_id;
        $amount = $model->amount;

        $this->getDi()['em']->remove($model);

        $this->getDi()['logger']->info('Removed line %s from client #%s balance for %s', $id, $client_id, $amount);

        return true;
    }

    /**
     * Adds funds to clients balance.
     *
     * @optional string $type - Related item type
     * @optional string $rel_id - Related item id
     */
    #[RequiredParams(['id' => 'Client ID required', 'amount' => 'Amount is required', 'description' => 'Description is required'])]
    public function balance_add_funds($data): bool
    {
        $this->checkPermissions('client', 'manage_balance');

        $client = $this->getDi()['em']->getRepository(Client::class)->find($data['id']) ?? throw new InformationException('Client not found');

        $service = $this->getDi()['mod_service']('client');
        $service->addFunds($client, $data['amount'], $data['description'], $data);

        return true;
    }

    /**
     * Remove password reminders which were not confirmed in 2 hours.
     */
    public function batch_expire_password_reminders(): bool
    {
        $this->checkPermissions('client', 'delete');

        $service = $this->getDi()['mod_service']('client');
        $expired = $service->getExpiredPasswordReminders();
        foreach ($expired as $model) {
            $this->getDi()['em']->remove($model);
        }

        $this->getDi()['logger']->info('Executed action to delete expired clients password reminders');

        return true;
    }

    /**
     * Get list of clients logins history.
     *
     * @optional int $client_id - filter by client
     *
     * @return array
     */
    public function login_history_get_list($data)
    {
        $this->checkPermissions('client', 'view_login_history');

        [$q, $params] = $this->getService()->getHistorySearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($q, $params, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = [
                'id' => $item['id'],
                'ip' => $item['ip'],
                'created_at' => $item['created_at'],
                'client' => [
                    'id' => $item['client_id'],
                    'first_name' => $item['first_name'],
                    'last_name' => $item['last_name'],
                    'email' => $item['email'],
                ],
            ];
        }

        return $pager;
    }

    /**
     * Return client statuses with counter.
     *
     * @return array
     */
    public function get_statuses($data)
    {
        $this->checkPermissions('client', 'view');

        $service = $this->getDi()['mod_service']('client');

        return $service->counter();
    }

    /**
     * Return client groups. Id and title pairs.
     *
     * @return array
     */
    public function group_get_pairs($data)
    {
        $this->checkPermissions('client', 'view');

        $service = $this->getDi()['mod_service']('client');

        return $service->getGroupPairs();
    }

    /**
     * Create new clients group.
     *
     * @return int $id - newly created group id
     */
    #[RequiredParams(['title' => 'Group title is missing'])]
    public function group_create($data)
    {
        $this->checkPermissions('client', 'manage_groups');

        return $this->getService()->createGroup($data);
    }

    /**
     * Update client group.
     *
     * @optional string $title - new group title
     */
    #[RequiredParams(['id' => 'Group ID is missing'])]
    public function group_update($data): bool
    {
        $this->checkPermissions('client', 'manage_groups');

        $model = $this->getDi()['em']->getRepository(ClientGroup::class)->find($data['id']) ?? throw new InformationException('Group not found');

        $model->title = $data['title'] ?? $model->title;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->getDi()['em']->persist($model);

        return true;
    }

    /**
     * Delete client group.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Group ID is missing'])]
    public function group_delete($data)
    {
        $this->checkPermissions('client', 'manage_groups');

        $model = $this->getDi()['em']->getRepository(ClientGroup::class)->find($data['id']) ?? throw new InformationException('Group not found');

        $clients = $this->getDi()['em']->getRepository(Client::class)->findBy(['clientGroupId' => $data['id']]);

        if (Tools::safeCount($clients) > 0) {
            throw new InformationException('Group has clients assigned. Please reassign them first.');
        }

        return $this->getService()->deleteGroup($model);
    }

    /**
     * Get client group details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Group ID is missing'])]
    public function group_get($data)
    {
        $this->checkPermissions('client', 'manage_groups');

        $model = $this->getDi()['em']->getRepository(ClientGroup::class)->find($data['id']) ?? throw new InformationException('Group not found');

        return [
            'id' => $model->getId(),
            'title' => $model->getTitle(),
            'created_at' => $model->getCreatedAt(),
            'updated_at' => $model->getUpdatedAt(),
        ];
    }

    /**
     * Deletes clients with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->checkPermissions('client', 'bulk_delete');

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id]);
        }

        return true;
    }

    public function export_csv($data): Response
    {
        $this->checkPermissions('client', 'export');

        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
