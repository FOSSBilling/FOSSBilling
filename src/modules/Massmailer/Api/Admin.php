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

namespace Box\Mod\Massmailer\Api;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of active mail messages.
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     */
    public function get_list(array $data): array
    {
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $qb = $this->getService()->getSearchQueryBuilder($data);
        $pager = $this->di['pager']->paginateDoctrineQuery($qb, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $item['filter'] = $this->getService()->normalizeFilter($item['filter'] ?? null);
            $pager['list'][$key] = $item;
        }

        return $pager;
    }

    /**
     * Get mail message by id.
     */
    public function get(array $data): array
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
     */
    public function update(array $data): bool
    {
        $model = $this->_getMessage($data);

        $model->setContent($data['content'] ?? $model->getContent());
        $model->setSubject($data['subject'] ?? $model->getSubject());
        $model->setStatus($data['status'] ?? $model->getStatus());
        if (isset($data['filter'])) {
            $model->setFilter($this->getService()->serializeFilter($data['filter']));
        }

        if (isset($data['from_name'])) {
            if (empty($data['from_name'])) {
                throw new \FOSSBilling\InformationException('Message from name cannot be empty');
            }
            $model->setFromName($data['from_name']);
        }

        if (isset($data['from_email'])) {
            $this->di['tools']->validateAndSanitizeEmail($data['from_email']);
            $this->di['tools']->generatePassword(32);
            $model->setFromEmail($data['from_email']);
        }

        $model->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->di['em']->flush();

        $this->di['logger']->info('Updated mail message #%s', $model->getId());

        return true;
    }

    /**
     * Create mail message.
     *
     * @optional string $content - mail message content
     */
    #[RequiredParams(['subject' => 'Message subject was not passed'])]
    public function create(array $data): int
    {
        $default_content = '{% apply markdown_to_html %}
Hi {{ c.first_name }} {{ c.last_name }},

Your email is: {{ c.email }}

Aenean vut sagittis in natoque tortor. Facilisis magnis duis nec eros! Augue
sed quis tortor porttitor? Rhoncus tortor pid et a enim dis adipiscing eros
facilisis nunc. Phasellus dis odio lacus pulvinar vel lundium dapibus turpis.

Urna parturient, ultricies nascetur? Et a. Elementum in dapibus ut vel ut
magna tempor, dapibus lacus sed? Ut velit dignissim placerat, tristique pid
vut amet et nunc! Elementum dolor, dictumst porta ultrices. Rhoncus, amet.

Order our services at {{ "order"|url }}

{{ guest.system_company.name }} - {{ guest.system_company.signature }}
{% endapply %}
        ';
        $systemService = $this->di['mod_service']('system');
        $company = $systemService->getCompany();

        $model = (new MassmailerMessage())
            ->setFromEmail($company['email'])
            ->setFromName($company['name'])
            ->setSubject($data['subject'])
            ->setContent($data['content'] ?? $default_content)
            ->setFilter(json_encode([], JSON_THROW_ON_ERROR))
            ->setStatus(MassmailerMessage::STATUS_DRAFT)
            ->setCreatedAt(date('Y-m-d H:i:s'))
            ->setUpdatedAt(date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $model->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to retrieve ID of created mail message.');
        }

        $this->di['logger']->info('Created mail message #%s', $id);

        return $id;
    }

    /**
     * Send test mail message by ID to client.
     */
    public function send_test(array $data): bool
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();

        if (empty($model->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $this->getService()->sendMessage($model, $client_id, true);

        $this->di['logger']->info('Sent test mail message #%s to client ', $model->getId());

        return true;
    }

    /**
     * Send mail message by ID.
     */
    public function send(array $data): bool
    {
        $model = $this->_getMessage($data);

        if (empty($model->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $clients = $this->getService()->getMessageReceivers($model);
        foreach ($clients as $c) {
            $this->getService()->sendMessage($model, $c['id']);
        }

        $model->setStatus(MassmailerMessage::STATUS_SENT);
        $model->setSentAt(date('Y-m-d H:i:s'));
        $this->di['em']->flush();

        $this->di['logger']->info('Added mass mail messages #%s to queue', $model->getId());

        return true;
    }

    /**
     * Copy mail message by ID.
     */
    public function copy(array $data): int
    {
        $model = $this->_getMessage($data);

        $copy = (new MassmailerMessage())
            ->setFromEmail($model->getFromEmail())
            ->setFromName($model->getFromName())
            ->setSubject(($model->getSubject() ?? '') . ' (Copy)')
            ->setContent($model->getContent())
            ->setFilter($model->getFilter())
            ->setStatus(MassmailerMessage::STATUS_DRAFT)
            ->setCreatedAt(date('Y-m-d H:i:s'))
            ->setUpdatedAt(date('Y-m-d H:i:s'));

        $this->di['em']->persist($copy);
        $this->di['em']->flush();

        $id = $copy->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to retrieve ID of copied mail message.');
        }

        $this->di['logger']->info('Copied mail message #%s to #%s', $model->getId(), $id);

        return $id;
    }

    /**
     * Get message receivers list.
     */
    public function receivers(array $data): array
    {
        $model = $this->_getMessage($data);

        return $this->getService()->getMessageReceivers($model);
    }

    /**
     * Delete mail message by ID.
     */
    public function delete(array $data): bool
    {
        $model = $this->_getMessage($data);
        $id = $model->getId();

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed mail message #%s', $id);

        return true;
    }

    /**
     * Generate preview text.
     *
     * @return array - parsed subject and content strings
     */
    public function preview(array $data): array
    {
        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        [$ps, $pc] = $this->getService()->getParsed($model, $client_id);

        $recipients = [];
        $getRecipients = $data['include_recipients'] ?? false;
        $clients = $this->getService()->getMessageReceivers($model);

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

    private function _getTestClientId(): int
    {
        $mod = $this->di['mod']('massmailer');
        $c = $mod->getConfig();

        $required = [
            'test_client_id' => 'Client ID needs to be configured in mass mailer settings.',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $c);

        return (int) $c['test_client_id'];
    }

    #[RequiredParams(['id' => 'Message ID was not passed'])]
    private function _getMessage(array $data): MassmailerMessage
    {
        $model = $this->getService()->getMessageRepository()->find((int) $data['id']);
        if (!$model instanceof MassmailerMessage) {
            throw new \FOSSBilling\Exception('Message not found');
        }

        return $model;
    }
}
