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
 * Client management.
 */

namespace Box\Mod\Client\Api;

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
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);

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
        $service = $this->getService();
        $client = $service->get($data);

        return $service->toApiArray($client, true, $this->getIdentity());
    }

    /**
     * Login to clients area with client id.
     *
     * @return array - client details
     */
    public function login($data)
    {
        $required = [
            'id' => 'ID required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
    public function create($data)
    {
        $required = [
            'email' => 'Email required',
            'first_name' => 'First name is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $validator = $this->di['validator'];
        $data['email'] = $this->di['tools']->validateAndSanitizeEmail($data['email']);

        $service = $this->getService();
        if ($service->emailAlreadyRegistered($data['email'])) {
            throw new \FOSSBilling\InformationException('This email address is already registered.');
        }

        $validator->isPasswordStrong($data['password']);

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientCreate', 'params' => $data]);
        $id = $service->adminCreateClient($data);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientCreate', 'params' => $data]);

        return $id;
    }

    /**
     * Deletes client from system.
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Client id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
     *
     * @return bool
     */
    public function update($data = [])
    {
        $required = ['id' => 'Id required'];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->di['mod_service']('client');

        if (!is_null($data['email'] ?? null)) {
            $email = $data['email'];
            $email = $this->di['tools']->validateAndSanitizeEmail($email);
            if ($service->emailAlreadyRegistered($email, $client)) {
                throw new \FOSSBilling\InformationException('This email address is already registered.');
            }
        }

        if (!empty($data['birthday'])) {
            $this->di['validator']->isBirthdayValid($data['birthday']);
        }

        if (($data['currency'] ?? null) && $service->canChangeCurrency($client, $data['currency'] ?? null)) {
            $client->currency = $data['currency'] ?? $client->currency;
        }

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientUpdate', 'params' => $data]);

        $phoneCC = $data['phone_cc'] ?? $client->phone_cc;
        if (!empty($phoneCC)) {
            $client->phone_cc = intval($phoneCC);
        }

        $client->email = (!empty($data['email']) ? $data['email'] : $client->email);
        $client->first_name = (!empty($data['first_name']) ? $data['first_name'] : $client->first_name);
        $client->last_name = (!empty($data['last_name']) ? $data['last_name'] : $client->last_name);
        $client->aid = (!empty($data['aid']) ? $data['aid'] : $client->aid);
        $client->gender = (!empty($data['gender']) ? $data['gender'] : $client->gender);
        $client->birthday = (!empty($data['birthday']) ? $data['birthday'] : $client->birthday);
        $client->company = (!empty($data['company']) ? $data['company'] : $client->company);
        $client->company_vat = (!empty($data['company_vat']) ? $data['company_vat'] : $client->company_vat);
        $client->address_1 = (!empty($data['address_1']) ? $data['address_1'] : $client->address_1);
        $client->address_2 = (!empty($data['address_2']) ? $data['address_2'] : $client->address_2);
        $client->phone = (!empty($data['phone']) ? $data['phone'] : $client->phone);
        $client->document_type = (!empty($data['document_type']) ? $data['document_type'] : $client->document_type);
        $client->document_nr = (!empty($data['document_nr']) ? $data['document_nr'] : $client->document_nr);
        $client->notes = (!empty($data['notes']) ? $data['notes'] : $client->notes);
        $client->country = (!empty($data['country']) ? $data['country'] : $client->country);
        $client->postcode = (!empty($data['postcode']) ? $data['postcode'] : $client->postcode);
        $client->state = (!empty($data['state']) ? $data['state'] : $client->state);
        $client->city = (!empty($data['city']) ? $data['city'] : $client->city);

        $client->status = (!empty($data['status']) ? $data['status'] : $client->status);
        $client->email_approved = (!empty($data['email_approved']) ? $data['email_approved'] : $client->email_approved);
        $client->tax_exempt = (!empty($data['tax_exempt']) ? $data['tax_exempt'] : $client->tax_exempt);
        $client->created_at = (!empty($data['created_at']) ? $data['created_at'] : $client->created_at);

        $client->custom_1 = (!empty($data['custom_1']) ? $data['custom_1'] : $client->custom_1);
        $client->custom_2 = (!empty($data['custom_2']) ? $data['custom_2'] : $client->custom_2);
        $client->custom_3 = (!empty($data['custom_3']) ? $data['custom_3'] : $client->custom_3);
        $client->custom_4 = (!empty($data['custom_4']) ? $data['custom_4'] : $client->custom_4);
        $client->custom_5 = (!empty($data['custom_5']) ? $data['custom_5'] : $client->custom_5);
        $client->custom_6 = (!empty($data['custom_6']) ? $data['custom_6'] : $client->custom_6);
        $client->custom_7 = (!empty($data['custom_7']) ? $data['custom_7'] : $client->custom_7);
        $client->custom_8 = (!empty($data['custom_8']) ? $data['custom_8'] : $client->custom_8);
        $client->custom_9 = (!empty($data['custom_9']) ? $data['custom_9'] : $client->custom_9);
        $client->custom_10 = (!empty($data['custom_10']) ? $data['custom_10'] : $client->custom_10);

        $client->client_group_id = (!empty($data['group_id']) ? $data['group_id'] : $client->client_group_id);
        $client->company_number = (!empty($data['company_number']) ? $data['company_number'] : $client->company_number);
        $client->type = (!empty($data['type']) ? $data['type'] : $client->type);
        $client->lang = (!empty($data['lang']) ? $data['lang'] : $client->lang);

        $client->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($client);
        $this->di['events_manager']->fire(['event' => 'onAfterAdminClientUpdate', 'params' => ['id' => $client->id]]);

        $this->di['logger']->info('Updated client #%s profile', $client->id);

        return true;
    }

    /**
     * Change client password.
     *
     * @return bool
     */
    public function change_password($data)
    {
        $required = [
            'id' => 'ID required',
            'password' => 'Password required',
            'password_confirm' => 'Password confirmation required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if ($data['password'] != $data['password_confirm']) {
            throw new \FOSSBilling\InformationException('Passwords do not match');
        }

        $this->di['validator']->isPasswordStrong($data['password']);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $this->di['events_manager']->fire(['event' => 'onBeforeAdminClientPasswordChange', 'params' => $data]);

        $client->pass = $this->di['password']->hashIt($data['password']);
        $client->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client);

        $profileService = $this->di['mod_service']('profile');
        $profileService->invalidateSessions('client', $data['id']);

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
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

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
     *
     * @return bool
     */
    public function balance_delete($data)
    {
        $required = [
            'id' => 'Client ID is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
     *
     * @return bool
     */
    public function balance_add_funds($data)
    {
        $required = [
            'id' => 'Client ID required',
            'amount' => 'Amount is required',
            'description' => 'Description is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->di['db']->getExistingModelById('Client', $data['id'], 'Client not found');

        $service = $this->di['mod_service']('client');
        $service->addFunds($client, $data['amount'], $data['description'], $data);

        return true;
    }

    /**
     * Remove password reminders which were not confirmed in 2 hours.
     *
     * @return bool
     */
    public function batch_expire_password_reminders()
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
        [$q, $params] = $this->getService()->getHistorySearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($q, $params, $per_page);

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
     * Remove log entry form clients logins history.
     *
     * @return bool
     */
    public function login_history_delete($data)
    {
        $required = [
            'id' => 'Id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        $model = $this->di['db']->getExistingModelById('ActivityClientHistory', $data['id']);

        if (!$model instanceof \Model_ActivityClientHistory) {
            throw new \FOSSBilling\Exception('Event not found');
        }
        $this->di['db']->trash($model);

        return true;
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
    public function group_create($data)
    {
        $required = [
            'title' => 'Group title is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->createGroup($data);
    }

    /**
     * Update client group.
     *
     * @optional string $title - new group title
     */
    public function group_update($data)
    {
        $required = [
            'id' => 'Group id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
    public function group_delete($data)
    {
        $required = [
            'id' => 'Group id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        $clients = $this->di['db']->find('Client', 'client_group_id = :group_id', [':group_id' => $data['id']]);

        if ((is_countable($clients) ? count($clients) : 0) > 0) {
            throw new \FOSSBilling\InformationException('Group has clients assigned. Please reassign them first.');
        }

        return $this->getService()->deleteGroup($model);
    }

    /**
     * Get client group details.
     *
     * @return array
     */
    public function group_get($data)
    {
        $required = [
            'id' => 'Group id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ClientGroup', $data['id'], 'Group not found');

        return $this->di['db']->toArray($model);
    }

    /**
     * Deletes clients with given IDs.
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes client login logs with given IDs.
     *
     * @return bool
     */
    public function batch_delete_log($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->login_history_delete(['id' => $id]);
        }

        return true;
    }

    public function export_csv($data)
    {
        $data['headers'] ??= [];

        return $this->getService()->exportCSV($data['headers']);
    }
}
