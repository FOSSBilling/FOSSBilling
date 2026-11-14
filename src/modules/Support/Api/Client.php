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
use Box\Mod\Support\HiddenHelpdesks;
use Box\Mod\Support\TicketFiles;
use Box\Mod\Support\TicketLimit;
use Box\Mod\Support\TicketSecrets;
use FOSSBilling\Core\HtmlSanitizerFactory;
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;

class Client extends \FOSSBilling\Core\Api\AbstractApi
{
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
            Options::fromArray($data),
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
        return HiddenHelpdesks::visiblePairs($this->getDi(), $this->getService()->getHelpdeskRepository()->getPairs());
    }

    /**
     * The client's ticket limit for the new-ticket banners: {max, days, limit,
     * left, date}, or null when the client has no limit.
     */
    public function ticket_limit(): ?array
    {
        return TicketLimit::status($this->getDi(), $this->getIdentity());
    }

    /**
     * Upload a file for a ticket message (bound to the message on save).
     *
     * @return array{id: string, name: string, size: int, size_label: string}
     */
    public function ticket_file_upload(array $data): array
    {
        $ticketId = isset($data['ticket_id']) && $data['ticket_id'] !== '' ? (int) $data['ticket_id'] : null;
        $file = $this->getDi()['request']->files->get('file');

        return TicketFiles::uploadForClient($this->getDi(), (int) $this->getIdentity()->getId(), $ticketId, $file);
    }

    /**
     * Discard a waiting upload.
     */
    #[RequiredParams(['id' => 'File ID was not passed'])]
    public function ticket_file_discard(array $data): bool
    {
        TicketFiles::discard($this->getDi(), (string) $data['id'], 'client', (int) $this->getIdentity()->getId());

        return true;
    }

    /**
     * Which secret kinds the client may add, and after how many days untouched
     * secrets expire: {server, other, days}.
     */
    public function ticket_secret_kinds(): array
    {
        return TicketSecrets::kinds($this->getDi());
    }

    /**
     * Add a secret to the client's own ticket; returns its id.
     */
    #[RequiredParams(['ticket_id' => 'Ticket ID was not passed', 'kind' => 'Secret kind was not passed'])]
    public function ticket_secret_add(array $data): array
    {
        return TicketSecrets::addForClient($this->getDi(), (int) $this->getIdentity()->getId(), (int) $data['ticket_id'], (string) $data['kind'], $data);
    }

    /**
     * The client's secrets on a ticket (metadata only) and their history.
     */
    #[RequiredParams(['ticket_id' => 'Ticket ID was not passed'])]
    public function ticket_secret_list(array $data): array
    {
        return TicketSecrets::listForTicket($this->getDi(), (int) $data['ticket_id'], (int) $this->getIdentity()->getId());
    }

    /**
     * Delete the client's own secret.
     */
    #[RequiredParams(['id' => 'Secret ID was not passed'])]
    public function ticket_secret_delete(array $data): bool
    {
        TicketSecrets::delete($this->getDi(), (int) $data['id'], null, (int) $this->getIdentity()->getId());

        return true;
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
        $data['content'] = HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']);

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $helpdesk = $repo->find((int) $data['support_helpdesk_id']);
        if (!$helpdesk instanceof Helpdesk || HiddenHelpdesks::isHidden($this->getDi(), (int) $helpdesk->getId())) {
            throw new \FOSSBilling\Core\Exception\InformationException('Helpdesk invalid');
        }

        $client = $this->getIdentity();
        TicketLimit::check($this->getDi(), $client);

        return $this->getService()->ticketCreateForClient($client, $helpdesk, $data);
    }

    /**
     * Add new conversation message to ticket. Ticket will be reopened if closed.
     */
    #[RequiredParams(['id' => 'Ticket ID was not passed', 'content' => 'Ticket content required'])]
    public function ticket_reply(array $data): bool
    {
        $data['content'] = HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']);

        $client = $this->getIdentity();
        $ticket = $this->getService()->getSupportTicketRepository()->findOneByClient((int) $client->getId(), (int) $data['id']);

        if (!$ticket instanceof SupportTicket) {
            throw new \FOSSBilling\Core\Exception\InformationException('Ticket not found', [], 404);
        }

        if (!$this->getService()->canBeReopened($ticket)) {
            throw new \FOSSBilling\Core\Exception\InformationException('Ticket cannot be reopened.');
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
