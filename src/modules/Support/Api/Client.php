<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/**
 * Client support tickets management.
 */

namespace Box\Mod\Support\Api;

use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \Api_Abstract
{
    /**
     * Get client tickets list.
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     *
     * @return array
     */
    public function ticket_get_list(array $data): array
    {
        $identity = $this->getIdentity();
        $data['client_id'] = $identity->id;

        [$sql, $bindings] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $bindings, $per_page);
        foreach ($pager['list'] as $key => $ticketArr) {
            $ticket = $this->di['db']->getExistingModelById('SupportTicket', $ticketArr['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->toApiArray($ticket, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Return ticket full details.
     *
     * @return array
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed'])]
    public function ticket_get(array $data): array
    {
        $ticket = $this->getService()->findOneByClient($this->getIdentity(), $data['id']);

        return $this->getService()->toApiArray($ticket);
    }

    /**
     * Return pairs for support helpdesk. Can be used to populate select box.
     *
     * @return array
     */
    public function helpdesk_get_pairs(): array
    {
        return $this->getService()->helpdeskGetPairs();
    }

    /**
     * Method to create open new ticket. Tickets can have tasks assigned to them
     * via optional parameters.
     *
     * @optional int $rel_type - Ticket relation type
     * @optional int $rel_id - Ticket relation id
     * @optional int $rel_task - Ticket task codename
     * @optional int $rel_new_value - Task can have new value assigned.
     *
     * @return int $id - ticket id
     */
    #[RequiredParams([
        'content' => 'Ticket content required',
        'subject' => 'Ticket subject required',
        'support_helpdesk_id' => 'Ticket support_helpdesk_id required',
    ])]
    public function ticket_create(array $data): int
    {
        // Sanitize content to prevent XSS attacks
        $data['content'] = \FOSSBilling\Tools::sanitizeContent($data['content'], true);

        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk invalid');

        $client = $this->getIdentity();

        return $this->getService()->ticketCreateForClient($client, $helpdesk, $data);
    }

    /**
     * Add new conversation message to ticket. Ticket will be reopened if closed.
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed', 'content' => 'Ticket content required'])]
    public function ticket_reply(array $data): bool
    {
        // Sanitize content to prevent XSS attacks
        $data['content'] = \FOSSBilling\Tools::sanitizeContent($data['content'], true);

        $client = $this->getIdentity();

        $bindings = [
            ':id' => $data['id'],
            ':client_id' => $client->id,
        ];
        $ticket = $this->di['db']->findOne('SupportTicket', 'id = :id AND client_id = :client_id', $bindings);

        if (!$ticket instanceof \Model_SupportTicket) {
            throw new \FOSSBilling\InformationException('Ticket not found');
        }

        if (!$this->getService()->canBeReopened($ticket)) {
            throw new \FOSSBilling\InformationException('Ticket cannot be reopened.');
        }

        $result = $this->getService()->ticketReply($ticket, $client, $data['content']);

        return ($result > 0) ? true : false;
    }

    /**
     * Close ticket.
     *
     * @return bool
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed'])]
    public function ticket_close(array $data): bool
    {
        $client = $this->getIdentity();

        $ticket = $this->getService()->findOneByClient($client, $data['id']);

        return $this->getService()->closeTicket($ticket, $this->getIdentity());
    }
}