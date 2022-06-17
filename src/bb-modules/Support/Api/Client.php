<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Client support tickets management.
 */

namespace Box\Mod\Support\Api;

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
    public function ticket_get_list($data)
    {
        $identity = $this->getIdentity();
        $data['client_id'] = $identity->id;

        [$sql, $bindings] = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);
        foreach ($pager['list'] as $key => $ticketArr) {
            $ticket = $this->di['db']->getExistingModelById('SupportTicket', $ticketArr['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->toApiArray($ticket, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Return ticket full details.
     *
     * @param int $id - ticket id
     *
     * @return array
     */
    public function ticket_get($data)
    {
        $required = [
            'id' => 'Ticket id required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->getService()->findOneByClient($this->getIdentity(), $data['id']);

        return $this->getService()->toApiArray($ticket);
    }

    /**
     * Return pairs for support helpdesk. Can be used to populate select box.
     *
     * @return array
     */
    public function helpdesk_get_pairs()
    {
        return $this->getService()->helpdeskGetPairs();
    }

    /**
     * Method to create open new ticket. Tickets can have tasks assigned to them
     * via optional parameters.
     *
     * @param string $content             - ticket message content
     * @param string $subject             - ticket subject
     * @param string $support_helpdesk_id - Ticket helpdesk id
     *
     * @optional int $rel_type - Ticket relation type
     * @optional int $rel_id - Ticket relation id
     * @optional int $rel_task - Ticket task codename
     * @optional int $rel_new_value - Task can have new value assigned.
     *
     * @return int $id - ticket id
     */
    public function ticket_create($data)
    {
        $required = [
            'content' => 'Ticket content required',
            'subject' => 'Ticket subject required',
            'support_helpdesk_id' => 'Ticket support_helpdesk_id required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk invalid');

        $client = $this->getIdentity();

        return $this->getService()->ticketCreateForClient($client, $helpdesk, $data);
    }

    /**
     * Add new conversation message to ticket. Ticket will be reopened if closed.
     *
     * @param int    $id      - ticket id
     * @param string $content - ticket message
     *
     * @return bool
     */
    public function ticket_reply($data)
    {
        $required = [
            'id' => 'Ticket ID required',
            'content' => 'Ticket content required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $client = $this->getIdentity();

        $bindings = [
            ':id' => $data['id'],
            ':client_id' => $client->id,
        ];
        $ticket = $this->di['db']->findOne('SupportTicket', 'id = :id AND client_id = :client_id', $bindings);

        if (!$ticket instanceof \Model_SupportTicket) {
            throw new \Box_Exception('Ticket not found');
        }

        if (!$this->getService()->canBeReopened($ticket)) {
            throw new \Box_Exception('Ticket can not be reopened.');
        }

        $result = $this->getService()->ticketReply($ticket, $client, $data['content']);

        return ($result > 0) ? true : false;
    }

    /**
     * Close ticket.
     *
     * @param int $id - ticket id
     *
     * @return bool
     */
    public function ticket_close($data)
    {
        $required = [
            'id' => 'Ticket ID required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $client = $this->getIdentity();

        $ticket = $this->getService()->findOneByClient($client, $data['id']);

        return $this->getService()->closeTicket($ticket, $this->getIdentity());
    }
}
