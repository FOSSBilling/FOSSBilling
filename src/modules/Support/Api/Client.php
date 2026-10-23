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
 * Client support tickets management.
 */

namespace Box\Mod\Support\Api;

use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\SupportTicket;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Api\AbstractApi
{
    public function notification_get_list(): array
    {
        return $this->notificationService()->getFeed((int) $this->getIdentity()->getId());
    }

    #[RequiredParams(['id' => 'Notification ID was not passed'])]
    public function notification_mark_read(array $data): array
    {
        $this->requireNotificationPost();
        $clientId = (int) $this->getIdentity()->getId();
        $this->notificationService()->getRepository()->markRead($clientId, (int) $data['id']);

        return $this->notificationService()->getRepository()->summary($clientId);
    }

    #[RequiredParams(['through_id' => 'Notification boundary was not passed'])]
    public function notification_mark_all_read(array $data): array
    {
        $this->requireNotificationPost();
        $clientId = (int) $this->getIdentity()->getId();
        $this->notificationService()->getRepository()->markAllRead($clientId, (int) $data['through_id']);

        return $this->notificationService()->getRepository()->summary($clientId);
    }

    #[RequiredParams(['ticket_id' => 'Ticket ID was not passed', 'through_message_id' => 'Message boundary was not passed'])]
    public function notification_read_ticket(array $data): array
    {
        $this->requireNotificationPost();
        $identity = $this->getIdentity();
        $this->getService()->findOneByClient($identity, (int) $data['ticket_id']);
        $clientId = (int) $identity->getId();
        $this->notificationService()->getRepository()->markTicketRead($clientId, (int) $data['ticket_id'], (int) $data['through_message_id']);

        return $this->notificationService()->getRepository()->summary($clientId);
    }

    private function notificationService(): \Box\Mod\Support\ServiceNotification
    {
        return $this->getDi()['mod_service']('support', 'notification');
    }

    private function requireNotificationPost(): void
    {
        if (!$this->getDi()['request']->isMethod('POST')) {
            throw new InformationException('This action requires POST', null, 405);
        }
    }

    /**
     * Get the list of tickets for the logged in client.
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     */
    public function ticket_get_list(array $data): array
    {
        $identity = $this->getIdentity();
        $data['client_id'] = $identity->getId();

        $repo = $this->getService()->getSupportTicketRepository();

        return $this->getDi()['pager']->paginateMappedQuery(
            $repo->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
            fn (SupportTicket $ticket): array => $this->getService()->toApiArray($ticket, true, $this->getIdentity()),
        );
    }

    /**
     * Return ticket full details.
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed'])]
    public function ticket_get(array $data): array
    {
        $identity = $this->getIdentity();
        $ticket = $this->getService()->findOneByClient($identity, (int) $data['id']);

        return $this->getService()->toApiArray($ticket, true, $identity);
    }

    /**
     * Return pairs for support helpdesk. Can be used to populate select box.
     */
    public function helpdesk_get_pairs(): array
    {
        return $this->getService()->getHelpdeskRepository()->getPairs();
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
        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']);

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $helpdesk = $repo->find((int) $data['support_helpdesk_id']);
        if (!$helpdesk instanceof Helpdesk) {
            throw new InformationException('Helpdesk invalid');
        }

        $client = $this->getIdentity();

        return $this->getService()->ticketCreateForClient($client, $helpdesk, $data);
    }

    /**
     * Add new conversation message to ticket. Ticket will be reopened if closed.
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed', 'content' => 'Ticket content required'])]
    public function ticket_reply(array $data): bool
    {
        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']);

        $client = $this->getIdentity();
        $ticket = $this->getService()->getSupportTicketRepository()->findOneByClient((int) $client->getId(), (int) $data['id']);

        if (!$ticket instanceof SupportTicket) {
            throw new InformationException('Ticket not found');
        }

        if (!$this->getService()->canBeReopened($ticket)) {
            throw new InformationException('Ticket cannot be reopened.');
        }

        $result = $this->getService()->ticketReply($ticket, $client, $data['content']);

        return $result > 0;
    }

    /**
     * Close ticket.
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed'])]
    public function ticket_close(array $data): bool
    {
        $client = $this->getIdentity();

        $ticket = $this->getService()->findOneByClient($client, (int) $data['id']);

        return $this->getService()->closeTicket($ticket, $this->getIdentity());
    }
}
