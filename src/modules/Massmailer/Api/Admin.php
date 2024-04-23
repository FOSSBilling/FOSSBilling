<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of active mail messages.
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     *
     * @return array
     */
    public function get_list($data)
    {
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $this->getService()->toApiArray($item);
        }

        return $pager;
    }

    /**
     * Get mail message by id.
     *
     * @return array
     */
    public function get($data)
    {
        $model = $this->_getMessage($data);

        return $this->getService()->toApiArray($model);
    }

    /**
     * Update mail message.
     *
     * @optional string $subject - mail message title
     * @optional string $content - mail message content
     * @optional string $status - mail message status
     * @optional string $from_name - mail message email from name
     * @optional string $from_email - mail message email from email
     * @optional array $filter  - filter parameters to select clients
     *
     * @return bool
     */
    public function update($data)
    {
        $model = $this->_getMessage($data);

        $model->content = $data['content'] ?? $model->content;
        $model->subject = $data['subject'] ?? $model->subject;
        $model->status = $data['status'] ?? $model->status;
        if (isset($data['filter'])) {
            $model->filter = json_encode($data['filter']);
        }

        if (isset($data['from_name'])) {
            if (empty($data['from_name'])) {
                throw new \FOSSBilling\InformationException('Message from name cannot be empty');
            }
            $model->from_name = $data['from_name'];
        }

        if (isset($data['from_email'])) {
            $this->di['tools']->validateAndSanitizeEmail($data['from_email']);
            $this->di['tools']->generatePassword(32);
            $model->from_email = $data['from_email'];
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated mail message #%s', $model->id);

        return true;
    }

    /**
     * Create mail message.
     *
     * @optional string $content - mail message content
     *
     * @return bool
     */
    public function create($data)
    {
        $required = [
            'subject' => 'Message subject not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $default_content = '{% apply markdown %}
Hi {{ c.first_name }} {{ c.last_name }},

Your email is: {{ c.email }}

Aenean vut sagittis in natoque tortor. Facilisis magnis duis nec eros! Augue
sed quis tortor porttitor? Rhoncus tortor pid et a enim dis adipiscing eros
facilisis nunc. Phasellus dis odio lacus pulvinar vel lundium dapibus turpis.

Urna parturient, ultricies nascetur? Et a. Elementum in dapibus ut vel ut
magna tempor, dapibus lacus sed? Ut velit dignissim placerat, tristique pid
vut amet et nunc! Elementum dolor, dictumst porta ultrices. Rhoncus, amet.

Order our services at {{ "order"|link }}

{{ guest.system_company.name }} - {{ guest.system_company.signature }}
{% endapply %}
        ';
        $systemService = $this->di['mod_service']('system');
        $company = $systemService->getCompany();

        $model = $this->di['db']->dispense('mod_massmailer');
        $model->from_email = $company['email'];
        $model->from_name = $company['name'];
        $model->subject = $data['subject'];
        $model->content = $data['content'] ?? $default_content;
        $model->status = 'draft';
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created mail message #%s', $model->id);

        return $id;
    }

    /**
     * Send test mail message by ID to client.
     *
     * @return bool
     */
    public function send_test($data)
    {
        /** @var \Model_MassmailerMessage $model */
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();

        if (empty($model->content)) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $this->getService()->sendMessage($model, $client_id, true);

        $this->di['logger']->info('Sent test mail message #%s to client ', $model->id);

        return true;
    }

    /**
     * Send mail message by ID.
     *
     * @return bool
     */
    public function send($data)
    {
        /** @var \Model_MassmailerMessage $model */
        $model = $this->_getMessage($data);

        if (empty($model->content)) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $clients = $this->getService()->getMessageReceivers($model, $data);
        foreach ($clients as $c) {
            $this->getService()->sendMessage($model, $c['id']);
        }

        $model->status = 'sent';
        $model->sent_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Added mass mail messages #%s to queue', $id);

        return true;
    }

    /**
     * Copy mail message by ID.
     *
     * @return bool
     */
    public function copy($data)
    {
        $model = $this->_getMessage($data);

        $copy = $this->di['db']->dispense('mod_massmailer');
        $copy->from_email = $model->from_email;
        $copy->from_name = $model->from_name;
        $copy->subject = $model->subject . ' (Copy)';
        $copy->content = $model->content;
        $copy->filter = $model->filter;
        $copy->status = 'draft';
        $copy->created_at = date('Y-m-d H:i:s');
        $copy->updated_at = date('Y-m-d H:i:s');

        $copyId = $this->di['db']->store($copy);

        $this->di['logger']->info('Copied mail message #%s to #%s', $model->id, $copyId);

        return $copyId;
    }

    /**
     * Get message receivers list.
     *
     * @return array
     */
    public function receivers($data)
    {
        $model = $this->_getMessage($data);

        return $this->getService()->getMessageReceivers($model, $data);
    }

    /**
     * Delete mail message by ID.
     *
     * @return bool
     */
    public function delete($data)
    {
        $model = $this->_getMessage($data);
        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed mail message #%s', $id);

        return true;
    }

    /**
     * Generate preview text.
     *
     * @return array - parsed subject and content strings
     */
    public function preview($data)
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        [$ps, $pc] = $this->getService()->getParsed($model, $client_id);

        $recipients = [];
        $getRecipients = $data['include_recipients'] ?? false;
        $clients = $this->getService()->getMessageReceivers($model, $data);

        if ($getRecipients) {
            $clientService = $this->di['mod_service']('client');
            foreach ($clients as $client) {
                $clientInfo = $clientService->get(['id' => $client['id']]);
                $recipients[] = [
                    'email' => $clientInfo->email,
                    'name' => $clientInfo->first_name . ' ' . $clientInfo->last_name,
                ];
            }
        }

        return [
            'subject' => $ps,
            'content' => $pc,
            'recipients' => $recipients,
        ];
    }

    /**
     * Returns the email associated with the test client.
     */
    public function get_test_client(): string
    {
        try {
            $client = $this->di['mod_service']('client')->get(['id' => $this->_getTestClientId()]);
        } catch (\Exception) {
            return 'Unknown';
        }

        return $client->email;
    }

    private function _getTestClientId()
    {
        $mod = $this->di['mod']('massmailer');
        $c = $mod->getConfig();

        $required = [
            'test_client_id' => 'Client ID needs to be configured in mass mailer settings.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        return (int) $c['test_client_id'];
    }

    private function _getMessage($data)
    {
        $required = [
            'id' => 'Message ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->di['db']->getExistingModelById('mod_massmailer', $data['id'], 'Message not found');
    }
}
