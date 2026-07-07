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
            $client = $this->getDi()['db']->getExistingModelById('Client', $clientArr['id'], 'Client not found');
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

        $client = $this->getDi()['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->getDi()['mod_service']('client');
        $result = $service->toSessionArray($client);

        $session = $this->getDi()['session'];
        $session->set('client_id', $client->id);
        $this->getDi()['logger']->info('Logged in as client #%s', $client->id);

        return $result;
    }

    /**
     * Creates new client.
     *
     * @optional string $password - client password
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
        $data['send_welcome_email'] = Tools::normalizeBoolean($data['send_welcome_email'] ?? true, true);

        $service = $this->getService();
        if ($service->emailAlreadyRegistered($data['email'])) {
            throw new InformationException('This email address is already registered.');
        }

        $password = trim((string) ($data['password'] ?? ''));
        $status = $data['status'] ?? \Model_Client::ACTIVE;
        if (!$data['send_welcome_email'] && $password === '') {
            throw new InformationException('A password is required when the welcome email is disabled.');
        }

        if ($data['send_welcome_email'] && $status !== \Model_Client::ACTIVE) {
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

        $model = $this->getDi()['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientDelete', 'params' => ['id' => $model->id]]);

        $id = $model->id;
        $this->getService()->remove($model);
        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientDelete', 'params' => ['id' => $id]]);

        $this->getDi()['logger']->info('Removed client #%s', $id);

        return true;
    }

    /**
     * Update client profile.
     *
     * @optional string $email - client email
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

        $client = $this->getDi()['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->getDi()['mod_service']('client');

        if (!is_null($data['email'] ?? null)) {
            $email = $data['email'];
            $email = $this->getDi()['tools']->validateAndSanitizeEmail($email);
            if ($service->emailAlreadyRegistered($email, $client)) {
                throw new InformationException('This email address is already registered.');
            }
        }

        if (!empty($data['birthday'])) {
            $this->getDi()['validator']->isBirthdayValid($data['birthday']);
        }

        if (($data['birthday'] ?? null) === '') {
            unset($data['birthday']);
        }

        $currency = $data['currency'] ?? null;
        if ($currency && $service->canChangeCurrency($client, $currency)) {
            $client->currency = $currency;
        }

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientUpdate', 'params' => $data]);

        // Special handling for the phone country codes
        $phoneCountryCode = $data['phone_cc'] ?? $client->phone_cc;
        if (!empty($phoneCountryCode)) {
            $client->phone_cc = Tools::validatePhoneCC($phoneCountryCode);
        }

        // Special handling for the phone number itself
        $phone = $data['phone'] ?? $client->phone;
        if (!empty($phone) && is_string($phone)) {
            $client->phone = Tools::validatePhoneNumber($phone);
        }

        $previousStatus = $client->status;

        if (!empty($data['country']) && !Countries::exists($data['country'])) {
            throw new InformationException('Invalid country code: :code', [':code' => $data['country']]);
        }

        if (!empty($data['lang']) && !Locales::exists($data['lang'])) {
            throw new InformationException('Invalid locale code: :code', [':code' => $data['lang']]);
        }

        if (array_key_exists('timezone', $data) && $data['timezone'] !== null && $data['timezone'] !== '' && !in_array($data['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            throw new InformationException('Invalid timezone: :tz', [':tz' => $data['timezone']]);
        }

        $allowedFields = [
            'email', 'first_name', 'last_name', 'aid', 'gender', 'birthday',
            'company', 'company_vat', 'address_1', 'address_2',
            'notes', 'country', 'postcode', 'state', 'city',
            'status', 'email_approved', 'tax_exempt', 'created_at',
            'custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5',
            'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10',
            'custom_11', 'custom_12', 'custom_13', 'custom_14', 'custom_15',
            'custom_16', 'custom_17', 'custom_18', 'custom_19', 'custom_20',
            'client_group_id', 'company_number', 'type', 'lang', 'timezone',
        ];

        foreach ($allowedFields as $field) {
            $client->{$field} = $data[$field] ?? $client->{$field};
        }

        if ($client->status !== \Model_Client::ACTIVE) {
            $client->api_token = null;
        }

        $client->updated_at = date('Y-m-d H:i:s');

        $this->getDi()['db']->store($client);

        if ($client->status !== \Model_Client::ACTIVE && $previousStatus === \Model_Client::ACTIVE) {
            $profileService = $this->getDi()['mod_service']('profile');
            $profileService->invalidateSessions('client', (int) $client->id);
        }

        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientUpdate', 'params' => ['id' => $client->id]]);

        $this->getDi()['logger']->info('Updated client #%s profile', $client->id);

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

        $client = $this->getDi()['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $this->getDi()['events_manager']->fire(['event' => 'onBeforeAdminClientPasswordChange', 'params' => ['id' => $client->id]]);

        $client->pass = $this->getDi()['password']->hashIt($data['password']);
        $client->updated_at = date('Y-m-d H:i:s');
        $this->getDi()['db']->store($client);

        $profileService = $this->getDi()['mod_service']('profile');
        $profileService->invalidateSessions('client', (int) $data['id']);

        $this->getDi()['events_manager']->fire(['event' => 'onAfterAdminClientPasswordChange', 'params' => ['id' => $client->id]]);

        $this->getDi()['logger']->info('Changed client #%s password', $client->id);

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

        $model = $this->getDi()['db']->getExistingModelById('ClientBalance', $data['id'], 'Balance line not found');

        $id = $model->id;
        $client_id = $model->client_id;
        $amount = $model->amount;

        $this->getDi()['db']->trash($model);

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

        $client = $this->getDi()['db']->getExistingModelById('Client', $data['id'], 'Client not found');

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
            $this->getDi()['db']->trash($model);
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

        $model = $this->getDi()['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        $model->title = $data['title'] ?? $model->title;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->getDi()['db']->store($model);

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

        $model = $this->getDi()['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        $clients = $this->getDi()['db']->find('Client', 'client_group_id = :group_id', [':group_id' => $data['id']]);

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

        $model = $this->getDi()['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        return $this->getDi()['db']->toArray($model);
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
