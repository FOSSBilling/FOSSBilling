<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Api;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get paginated list of active mail messages.
     *
     * @optional string $status - filter list by status
     * @optional string $search - search query to search for mail messages
     */
    public function get_list(array $data): array
    {
        $this->checkPermissions('massmailer', 'view');

        $qb = $this->getService()->getSearchQueryBuilder($data);
        $pager = $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));

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
        $this->checkPermissions('massmailer', 'view');

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
        $this->checkPermissions('massmailer', 'create_and_edit');

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
            $this->getDi()['tools']->validateAndSanitizeEmail($data['from_email']);
            $model->setFromEmail($data['from_email']);
        }

        $model->setUpdatedAt(date('Y-m-d H:i:s'));
        $this->getDi()['em']->flush();

        $this->getDi()['logger']->info('Updated mail message #%s', $model->getId());

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
        $this->checkPermissions('massmailer', 'create_and_edit');

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

{{ guest.system_company.name }}
{% endapply %}
{{ guest.system_email.signature }}
        ';
        $systemService = $this->getDi()['mod_service']('system');
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

        $this->getDi()['em']->persist($model);
        $this->getDi()['em']->flush();

        $id = $model->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to retrieve ID of created mail message.');
        }

        $this->getDi()['logger']->info('Created mail message #%s', $id);

        return $id;
    }

    /**
     * Send test mail message by ID to client.
     */
    public function send_test(array $data): bool
    {
        $this->checkPermissions('massmailer', 'send');

        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();

        if (empty($model->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $this->getService()->sendMessage($model, $client_id, true);

        $this->getDi()['logger']->info('Sent test mail message #%s to client ', $model->getId());

        return true;
    }

    /**
     * Send mail message by ID.
     */
    public function send(array $data): bool
    {
        $this->checkPermissions('massmailer', 'send');

        $model = $this->_getMessage($data);

        if (empty($model->getContent())) {
            throw new \FOSSBilling\InformationException('Add some content before sending message');
        }

        $clients = $this->getService()->getMessageReceivers($model);
        foreach ($clients as $c) {
            $this->getService()->sendMessage($model, (int) $c['id']);
        }

        $model->setStatus(MassmailerMessage::STATUS_SENT);
        $model->setSentAt(date('Y-m-d H:i:s'));
        $this->getDi()['em']->flush();

        $this->getDi()['logger']->info('Added mass mail messages #%s to queue', $model->getId());

        return true;
    }

    /**
     * Copy mail message by ID.
     */
    public function copy(array $data): int
    {
        $this->checkPermissions('massmailer', 'create_and_edit');

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

        $this->getDi()['em']->persist($copy);
        $this->getDi()['em']->flush();

        $id = $copy->getId();
        if ($id === null) {
            throw new \FOSSBilling\Exception('Failed to retrieve ID of copied mail message.');
        }

        $this->getDi()['logger']->info('Copied mail message #%s to #%s', $model->getId(), $id);

        return $id;
    }

    /**
     * Get message receivers list.
     */
    public function receivers(array $data): array
    {
        $this->checkPermissions('massmailer', 'view');

        $model = $this->_getMessage($data);

        return $this->getService()->getMessageReceivers($model);
    }

    /**
     * Delete mail message by ID.
     */
    public function delete(array $data): bool
    {
        $this->checkPermissions('massmailer', 'delete');

        $model = $this->_getMessage($data);
        $id = $model->getId();

        $this->getDi()['em']->remove($model);
        $this->getDi()['em']->flush();

        $this->getDi()['logger']->info('Removed mail message #%s', $id);

        return true;
    }

    /**
     * Generate preview text.
     *
     * @return array - parsed subject and content strings
     */
    public function preview(array $data): array
    {
        $this->checkPermissions('massmailer', 'view');

        $model = $this->_getMessage($data);
        $client_id = $this->_getTestClientId();
        [$ps, $pc] = $this->getService()->getParsed($model, $client_id);

        $recipients = [];
        $getRecipients = $data['include_recipients'] ?? false;
        $clients = $this->getService()->getMessageReceivers($model);

        if ($getRecipients) {
            $clientService = $this->getDi()['mod_service']('client');
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
        $this->checkPermissions('massmailer', 'view');

        try {
            $client = $this->getDi()['mod_service']('client')->get(['id' => $this->_getTestClientId()]);
        } catch (\Exception) {
            return 'Unknown';
        }

        return $client->getEmail();
    }

    private function _getTestClientId(): int
    {
        $mod = $this->getDi()['mod']('massmailer');
        $c = $mod->getConfig();

        $required = [
            'test_client_id' => 'Client ID needs to be configured in mass mailer settings.',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $c);

        return (int) $c['test_client_id'];
    }

    #[RequiredParams(['id' => 'Message ID was not passed'])]
    private function _getMessage(array $data): MassmailerMessage
    {
        $model = $this->getService()->getMessageRepository()->find((int) $data['id']);
        if (!$model instanceof MassmailerMessage) {
            throw new \FOSSBilling\InformationException('Message not found');
        }

        return $model;
    }
}
