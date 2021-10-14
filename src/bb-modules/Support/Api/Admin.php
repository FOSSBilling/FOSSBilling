<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * Support management module
 */
namespace Box\Mod\Support\Api;
class Admin extends \Api_Abstract
{
    /**
     * Get tickets list
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     *
     * @return array
     */
    public function ticket_get_list($data)
    {
        list($sql, $bindings) = $this->getService()->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager =  $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);
        foreach($pager['list'] as $key => $ticketArr){
            $ticket = $this->di['db']->getExistingModelById('SupportTicket', $ticketArr['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->toApiArray($ticket, true, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Return ticket full details
     *
     * @param int $id - ticket id
     *
     * @return array
     */
    public function ticket_get($data)
    {
        $required = array(
            'id' => 'Ticket id is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update ticket details
     *
     * @param int $id - ticket id
     *
     * @optional int $support_helpdesk_id - ticket helpdesk id
     * @optional string $status - ticket status
     * @optional string $subject - ticket subject
     * @optional string $priority - ticket priority
     *
     * @return bool
     */
    public function ticket_update($data)
    {
        $required = array(
            'id' => 'Ticket id is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketUpdate($model, $data);
    }

    /**
     * Update ticket message
     *
     * @param int $id - ticket id
     * @param string $content - new message content
     *
     * @return bool
     */
    public function ticket_message_update($data)
    {
        $required = array(
            'id'      => 'Ticket message id is missing',
            'content' => 'Ticket message content is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicketMessage', $data['id'], 'Ticket message not found');

        return $this->getService()->ticketMessageUpdate($model, $data['content']);
    }

    /**
     * Delete ticket.
     *
     * @param int $id - ticket id
     *
     * @return bool
     */
    public function ticket_delete($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->rm($model);
    }

    /**
     * Add new conversation message to to ticket
     *
     * @param int $id - ticket id
     * @param string $content - ticket message content
     *
     * @return int - ticket message id
     */
    public function ticket_reply($data)
    {
        $required = array(
            'id'      => 'Ticket id is missing',
            'content' => 'Ticket message content is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $ticket = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketReply($ticket, $this->getIdentity(), $data['content']);
    }

    /**
     * Close ticket
     *
     * @param int $id - ticket id
     *
     * @return boolean
     */
    public function ticket_close($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        if ($ticket->status == \Model_SupportTicket::CLOSED) {
            return true;
        }

        return $this->getService()->closeTicket($ticket, $this->getIdentity());
    }

    /**
     * Method to create open new ticket. Tickets can have tasks assigned to them
     * via optional parameters.
     *
     * @param int $client_id - ticket client id
     * @param string $content - ticket message content
     * @param string $subject - ticket subject
     * @param int $support_helpdesk_id - Ticket helpdesk id.
     *
     * @optional string $status - Ticket status. Default - on hold
     *
     * @return int $id - ticket id
     */
    public function ticket_create($data)
    {
        $required = array(
            'client_id'           => 'Client id is missing',
            'content'             => 'Ticket content required',
            'subject'             => 'Ticket subject required',
            'support_helpdesk_id' => 'Ticket support_helpdesk_id is required'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $client   = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk invalid');

        return $this->getService()->ticketCreateForAdmin($client, $helpdesk, $data, $this->getIdentity());

    }

    /**
     * Action to close all tickets which have not received any replies for a
     * time defined in helpdesk
     *
     * Run by cron job
     *
     * @return boolean
     */
    public function batch_ticket_auto_close($data)
    {
        // Auto close support tickets
        $expiredArr = $this->getService()->getExpired();

        foreach ($expiredArr as $ticketArr) {
            $ticketModel =  $this->di['db']->getExistingModelById('SupportTicket', $ticketArr['id'], 'Ticket not found');
            if (!$this->getService()->autoClose($ticketModel)) {
                $this->di['logger']->info('Ticket %s was not closed', $ticketModel->id);
            }
        }

        return true;
    }

    /**
     * Action to close all inquiries which have not received any replies for a
     * time defined in helpdesk
     *
     * Run by cron job
     *
     * @return boolean
     */
    public function batch_public_ticket_auto_close($data)
    {
        // Auto close public tickets
        $expired = $this->getService()->publicGetExpired();
        foreach ($expired as $model) {
            if (!$this->getService()->publicAutoClose($model)) {
                $this->di['logger']->info('Public Ticket %s was not closed', $model->id);
            }
        }

        return true;
    }

    /**
     * Return tickets statuses with counter
     * @return
     */
    public function ticket_get_statuses($data)
    {
        if (isset($data['titles'])) {
            return $this->getService()->getStatuses();
        }

        return $this->getService()->counter();
    }

    /**
     * Get paginated list of inquiries
     *
     * @return array
     */
    public function public_ticket_get_list($data)
    {
        list($sql, $bindings) = $this->getService()->publicGetSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager =  $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);

        foreach($pager['list'] as $key => $ticketArr){
            $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $ticketArr['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->publicToApiArray($ticket);
        }
        return $pager;
    }

    /**
     * Create new inquiry. Send email
     *
     * @param string $name - receivers name
     * @param string $email - receivers email
     * @param string $subject - email subject
     * @param string $message - email message
     *
     * @return int - inquiry id
     * @throws \Box_Exception
     */
    public function public_ticket_create($data)
    {
        $required = array(
            'name'    => 'Client name parameter is missing',
            'email'   => 'Client email parameter is missing',
            'subject' => 'Subject parameter is missing',
            'message' => 'Ticket message is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->publicTicketCreate($data, $this->getIdentity());
    }

    /**
     * Get inquiry details
     *
     * @param int $id - inquiry id
     * @return array
     * @throws \Box_Exception
     */
    public function public_ticket_get($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicToApiArray($model, true);
    }

    /**
     * Delete inquiry
     *
     * @param int $id - inquiry id
     * @return bool
     * @throws \Box_Exception
     */
    public function public_ticket_delete($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicRm($model);
    }

    /**
     * Set iquery status to closed
     *
     * @param int $id - inquiry id
     * @return array
     * @throws \Box_Exception
     */
    public function public_ticket_close($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicCloseTicket($ticket, $this->getIdentity());
    }

    /**
     * Update inquiry details
     *
     * @param int $id - inquiry id
     *
     * @optional string $subject - subject
     * @optional string $status - status
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function public_ticket_update($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicTicketUpdate($model, $data);
    }

    /**
     * Post new reply to inquiry
     *
     * @param int $id - inquiry id
     * @param string $content - text message
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function public_ticket_reply($data)
    {
        $required = array(
            'id'      => 'Ticket id is missing',
            'content' => 'Ticket content required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicTicketReply($ticket, $this->getIdentity(), $data['content']);
    }

    /**
     * Return tickets statuses with counter
     */
    public function public_ticket_get_statuses($data)
    {
        if (isset($data['titles'])) {
            return $this->getService()->publicGetStatuses();
        }

        return $this->getService()->publicCounter();
    }

    /**
     * Get helpdesk list
     *
     * @return array
     */
    public function helpdesk_get_list($data)
    {
        list($sql, $bindings) = $this->getService()->helpdeskGetSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $this->di['pager']->getSimpleResultSet($sql, $bindings, $per_page);
    }

    /**
     * Get pairs of helpdesks
     *
     * @return array
     */
    public function helpdesk_get_pairs($data)
    {
        return $this->getService()->helpdeskGetPairs();
    }

    /**
     * Get helpdesk details
     *
     * @param int $id - helpdesk id
     * @return array
     * @throws \Box_Exception
     */
    public function helpdesk_get($data)
    {
        $required = array(
            'id' => 'Help desk id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskToApiArray($model);
    }

    /**
     * Update helpdesk parameters
     *
     * @param int $id - helpdesk id
     *
     * @optional string $name - helpdesk name
     * @optional string $email - helpdesk email
     * @optional string $can_reopen - flag to enable/disable ability to reopen closed tickets
     * @optional int $close_after - time to wait for reply before auto closing ticket
     * @optional string $signature - helpdesk signature
     *
     * @return boolean
     * @throws \Box_Exception
     */
    public function helpdesk_update($data)
    {
        $required = array(
            'id' => 'Help desk id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskUpdate($model, $data);
    }

    /**
     * Create new helpdesk
     *
     * @param string $name - new helpdesk title
     *
     * @optional string $email - helpdesk email
     * @optional string $can_reopen - flag to enable/disable ability to reopen closed tickets
     * @optional int $close_after - time to wait for reply before auto closing ticket
     * @optional string $signature - helpdesk signature
     *
     * @return int - id
     * @throws \Box_Exception
     */
    public function helpdesk_create($data)
    {
        $required = array(
            'name' => 'Help desk title is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->helpdeskCreate($data);
    }

    /**
     * Delete helpdesk
     *
     * @param int $id - helpdesk id
     *
     * @return boolean
     * @throws \Box_Exception
     */
    public function helpdesk_delete($data)
    {
        $required = array(
            'id' => 'Help desk id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskRm($model);
    }

    /**
     * Get list of canned responses
     *
     * @return array
     */
    public function canned_get_list($data)
    {
        list($sql, $bindings) = $this->getService()->cannedGetSearchQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $bindings, $per_page);
        foreach($pager['list'] as $key => $item){
            $staff = $this->di['db']->getExistingModelById('SupportPr', $item['id'], 'Canned response not found');
            $pager['list'][$key] = $this->getService()->cannedToApiArray($staff);
        }

        return $pager;
    }

    /**
     * Get list of canned responses grouped by category
     *
     * @return array
     */
    public function canned_pairs()
    {
        $res  = $this->di['db']->getAssoc('SELECT id, title FROM support_pr_category WHERE 1');
        $list = array();
        foreach ($res as $id => $title) {
            $list[$title] = $this->di['db']->getAssoc('SELECT id, title FROM support_pr WHERE support_pr_category_id = :id', array('id' => $id));
        }

        return $list;
    }

    /**
     * Get canned response details
     *
     * @param int $id - canned response id
     *
     * @return array
     * @throws \Box_Exception
     */
    public function canned_get($data)
    {
        $required = array(
            'id' => 'Canned reply id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedToApiArray($model);
    }

    /**
     * Delete canned response
     *
     * @param id $id - canned response id
     *
     * @return boolean
     * @throws \Box_Exception
     */
    public function canned_delete($data)
    {
        $required = array(
            'id' => 'Canned reply id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedRm($model);
    }

    /**
     * Create new canned response
     *
     * @param string $title - canned response title
     * @param int $category_id - canned response category id
     *
     * @optional string $content - canned response content
     *
     * @return int
     * @throws \Box_Exception
     */
    public function canned_create($data)
    {
        $required = array(
            'title'       => 'Canned reply title is missing',
            'category_id' => 'Canned reply category id is missing'
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $content = $this->di['array_get']($data, 'content', NULL);

        return $this->getService()->cannedCreate($data['title'], $data['category_id'], $content);
    }

    /**
     * Update canned response
     *
     * @param int $id - canned response id
     *
     * @optional string $title - canned response title
     * @optional int $category_id - canned response category id
     * @optional string $content - canned response content
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function canned_update($data)
    {
        $required = array(
            'id' => 'Canned reply id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedUpdate($model, $data);
    }

    /**
     * Get canned response pairs
     *
     * @return array
     */
    public function canned_category_pairs($data)
    {
        return $this->di['db']->getAssoc('SELECT id, title FROM support_pr_category WHERE 1');
    }

    /**
     * Get canned response category
     *
     * @param int $id - canned response category id
     * @return array
     * @throws \Box_Exception
     */
    public function canned_category_get($data)
    {
        $required = array(
            'id' => 'Canned category id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        return $this->getService()->cannedCategoryToApiArray($model);
    }

    /**
     * Get canned response category
     *
     * @param int $id - canned response category id
     *
     * @optional string $title - new category title
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function canned_category_update($data)
    {
        $required = array(
            'id' => 'Canned category id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        $title = $this->di['array_get']($data, 'title', $model->title);

        return $this->getService()->cannedCategoryUpdate($model, $title);
    }

    /**
     * Delete canned response category
     *
     * @param int $id - canned response category id
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function canned_category_delete($data)
    {
        $required = array(
            'id' => 'Canned category id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        return $this->getService()->cannedCategoryRm($model);
    }

    /**
     * Create canned response category
     *
     * @param string $title - canned response category title
     *
     * @return int - new category id
     * @throws \Box_Exception
     */
    public function canned_category_create($data)
    {
        $required = array(
            'title' => 'Canned category title is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->cannedCategoryCreate($data['title']);
    }

    /**
     * Add note to support ticket
     *
     * @param int $ticket_id - support ticket id to add note to
     * @param string $note - note
     *
     * @return int - new note id
     * @throws \Box_Exception
     */
    public function note_create($data)
    {
        $required = array(
            'ticket_id' => 'ticket_id is missing',
            'note'      => 'Note is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportTicket', $data['ticket_id'], 'Ticket not found');

        return $this->getService()->noteCreate($ticket, $this->getIdentity(), $data['note']);
    }

    /**
     * Delete note from support ticket
     *
     * @param int $id - note id
     *
     * @return bool
     * @throws \Box_Exception
     */
    public function note_delete($data)
    {
        $required = array(
            'id' => 'Note id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicketNote', $data['id'], 'Note not found');

        return $this->getService()->noteRm($model);
    }

    /**
     * Set support ticket related task to completed
     *
     * @param int $id - support ticket id
     *
     * @return boolean
     * @throws \Box_Exception
     */
    public function task_complete($data)
    {
        $required = array(
            'id' => 'Ticket id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketTaskComplete($model);

    }

    /**
     * Deletes tickets with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->ticket_delete(array('id' => $id));
        }

        return true;
    }

    /**
     * Deletes tickets with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete_public($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->public_ticket_delete(array('id' => $id));
        }

        return true;
    }
}