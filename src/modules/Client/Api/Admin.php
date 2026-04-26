<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
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
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'view');

        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, data: $data);

        foreach ($pager['list'] as $key => $clientArr) {
            $client = $this->di['db']->getExistingModelById('Client', $clientArr['id'], 'Client not found');
            $pager['list'][$key] = $this->getService()->toApiArray($client, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get a list of clients.
     *
     * @param array $data Filtering options
     *
     * @return array List of clients in a paginated manner
     */
    public function get_pairs($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'view');

        $service = $this->di['mod_service']('client');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'view');

        $service = $this->getService();
        $client = $service->get($data);
        $includeSensitive = $this->di['mod_service']('Staff')->hasPermission(null, 'client', 'manage_api_keys');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'impersonate_login');

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->di['mod_service']('client');
        $result = $service->toSessionArray($client);

        $session = $this->di['session'];
        $session->set('client_id', $client->id);
        $this->di['logger']->info('Logged in as client #%s', $client->id);

        return $result;
    }

    /**
     * Creates new client.
     *
     * @optional string $password - client password
     * @optional string $auth_type - client authorization type. Default null
     * @optional string $last_name - client last name
     * @optional string $aid - alternative ID. If you import clients from other systems you can use this field to store foreign system ID
     * @optional string $group_id - client group id
     * @optional string $status - client status: "active, suspended, canceled"
     * @optional string $created_at - ISO 8601 date for client creation date
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
     * @optional string $document_type - Related document type, ie: passport, driving license
     * @optional string $document_nr - Related document number, ie: passport number: LC45698122
     * @optional string $notes - Notes about client. Visible for admin only
     * @optional string $lang - Client language
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
     *
     * @return int - client id
     */
    #[RequiredParams(['email' => 'Email required', 'first_name' => 'First name is required'])]
    public function create($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'create');

        $validator = $this->di['validator'];
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $service = $this->getService();
        if ($service->emailAlreadyRegistered($data['email'])) {
            throw new InformationException('This email address is already registered.');
        }

        $validator->isPasswordStrong($data['password']);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientCreate', 'params' => $data]);
        $id = $service->adminCreateClient($data);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientCreate', 'params' => $data]);

        return $id;
    }

    /**
     * Deletes client from system.
     */
    #[RequiredParams(['id' => 'Client ID is missing'])]
    public function delete($data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'delete');

        $model = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientDelete', 'params' => ['id' => $model->id]]);

        $id = $model->id;
        $this->getService()->remove($model);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientDelete', 'params' => ['id' => $id]]);

        $this->di['logger']->info('Removed client #%s', $id);

        return true;
    }

    /**
     * Update client profile.
     *
     * @optional string $email - client email
     * @optional string $first_name - client first_name
     * @optional string $last_name - client last_name
     * @optional string $status - client status
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
     * @optional string $document_type - Related document type, ie: passport, driving license
     * @optional string $document_nr - Related document number, ie: passport number: LC45698122
     * @optional string $lang - Client language
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
     */
    #[RequiredParams(['id' => 'Client ID was not passed'])]
    public function update($data = []): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'edit_profile');

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->di['mod_service']('client');

        if (!is_null($data['email'] ?? null)) {
            $email = $data['email'];
            $email = $this->di['tools']->validateAndSanitizeEmail($email);
            if ($service->emailAlreadyRegistered($email, $client)) {
                throw new InformationException('This email address is already registered.');
            }
        }

        if (!empty($data['birthday'])) {
            $this->di['validator']->isBirthdayValid($data['birthday']);
        }

        if (($data['currency'] ?? null) && $service->canChangeCurrency($client, $data['currency'] ?? null)) {
            $client->currency = $data['currency'] ?? $client->currency;
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientUpdate', 'params' => $data]);

        // Special handling for the phone country codes
        $phoneCC = $data['phone_cc'] ?? $client->phone_cc;
        if (!empty($phoneCC)) {
            $client->phone_cc = Tools::validatePhoneCC($phoneCC);
        }

        // Special handling for the phone number itself
        $phone = $data['phone'] ?? $client->phone;
        if (!empty($phone) && is_string($phone)) {
            $client->phone = Tools::validatePhoneNumber($phone);
        }

        $previousStatus = $client->status;

        $allowedFields = [
            'email', 'first_name', 'last_name', 'aid', 'gender', 'birthday',
            'company', 'company_vat', 'address_1', 'address_2', 'document_type',
            'document_nr', 'notes', 'country', 'postcode', 'state', 'city',
            'status', 'email_approved', 'tax_exempt', 'created_at',
            'custom_1', 'custom_2', 'custom_3', 'custom_4', 'custom_5',
            'custom_6', 'custom_7', 'custom_8', 'custom_9', 'custom_10',
            'client_group_id', 'company_number', 'type', 'lang',
        ];

        foreach ($allowedFields as $field) {
            $client->{$field} = $data[$field] ?? $client->{$field};
        }

        if ($client->status !== \Model_Client::ACTIVE) {
            $client->api_token = null;
        }

        $client->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($client);

        if ($client->status !== \Model_Client::ACTIVE && $previousStatus === \Model_Client::ACTIVE) {
            $profileService = $this->di['mod_service']('profile');
            $profileService->invalidateSessions('client', (int) $client->id);
        }

        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientUpdate', 'params' => ['id' => $client->id]]);

        $this->di['logger']->info('Updated client #%s profile', $client->id);

        return true;
    }

    /**
     * Change client password.
     */
    #[RequiredParams(['id' => 'ID required', 'password' => 'Password required', 'password_confirm' => 'Password confirmation required'])]
    public function change_password(array $data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'change_password');

        $this->di['validator']->passwordsMatch($data);

        $this->di['validator']->isPasswordStrong($data['password']);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientPasswordChange', 'params' => $data]);

        $client->pass = $this->di['password']->hashIt($data['password']);
        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);

        $profileService = $this->di['mod_service']('profile');
        $profileService->invalidateSessions('client', (int) $data['id']);

        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientPasswordChange', 'params' => ['id' => $client->id, 'password' => $data['password']]]);

        $this->di['logger']->info('Changed client #%s password', $client->id);

        return true;
    }

    /**
     * Returns list of client payments.
     *
     * @return array
     */
    public function balance_get_list($data)
    {
        $service = $this->di['mod_service']('Client', 'Balance');
        [$q, $params] = $service->getSearchQuery($data);
        $pager = $this->di['pager']->getPaginatedResultSet($q, $params, data: $data);

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'manage_balance');

        $model = $this->di['db']->getExistingModelById('ClientBalance', $data['id'], 'Balance line not found');

        $id = $model->id;
        $client_id = $model->client_id;
        $amount = $model->amount;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed line %s from client #%s balance for %s', $id, $client_id, $amount);

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'manage_balance');

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->di['mod_service']('client');
        $service->addFunds($client, $data['amount'], $data['description'], $data);

        return true;
    }

    /**
     * Remove password reminders which were not confirmed in 2 hours.
     */
    public function batch_expire_password_reminders(): bool
    {
        $service = $this->di['mod_service']('client');
        $expired = $service->getExpiredPasswordReminders();
        foreach ($expired as $model) {
            $this->di['db']->trash($model);
        }

        $this->di['logger']->info('Executed action to delete expired clients password reminders');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'view_login_history');

        [$q, $params] = $this->getService()->getHistorySearchQuery($data);
        $pager = $this->di['pager']->getPaginatedResultSet($q, $params, data: $data);

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
        $service = $this->di['mod_service']('client');

        return $service->counter();
    }

    /**
     * Return client groups. Id and title pairs.
     *
     * @return array
     */
    public function group_get_pairs($data)
    {
        $service = $this->di['mod_service']('client');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'manage_groups');

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'manage_groups');

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        $model->title = $data['title'] ?? $model->title;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

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
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'manage_groups');

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        $clients = $this->di['db']->find('Client', 'client_group_id = :group_id', [':group_id' => $data['id']]);

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
        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        return $this->di['db']->toArray($model);
    }

    /**
     * Deletes clients with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'bulk_delete');

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id]);
        }

        return true;
    }

    public function export_csv($data)
    {
        $this->di['mod_service']('Staff')->checkPermissionsAndThrowException('client', 'export');

        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
