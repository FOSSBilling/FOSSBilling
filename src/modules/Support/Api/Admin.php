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
 * Support management module.
 */

namespace Box\Mod\Support\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get tickets list.
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     *
     * @return array
     */
    public function ticket_get_list($data)
    {
        [$sql, $bindings] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
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
     * @return array
     */
    public function ticket_get($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update ticket details.
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
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketUpdate($model, $data);
    }

    /**
     * Update ticket message.
     *
     * @return bool
     */
    public function ticket_message_update($data)
    {
        $required = [
            'id' => 'Ticket message id is missing',
            'content' => 'Ticket message content is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicketMessage', $data['id'], 'Ticket message not found');

        return $this->getService()->ticketMessageUpdate($model, $data['content']);
    }

    /**
     * Delete ticket.
     *
     * @return bool
     */
    public function ticket_delete($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->rm($model);
    }

    /**
     * Add new conversation message to to ticket.
     *
     * @return int - ticket message id
     */
    public function ticket_reply($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
            'content' => 'Ticket message content is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $ticket = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketReply($ticket, $this->getIdentity(), $data['content']);
    }

    /**
     * Close ticket.
     *
     * @return bool
     */
    public function ticket_close($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
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
     * @optional string $status - Ticket status. Default - on hold
     *
     * @return int $id - ticket id
     */
    public function ticket_create($data)
    {
        $required = [
            'client_id' => 'Client id is missing',
            'content' => 'Ticket content required',
            'subject' => 'Ticket subject required',
            'support_helpdesk_id' => 'Ticket support_helpdesk_id is required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $data['content'] = preg_replace('/javascript:\/\/|\%0(d|a)/i', '', $data['content']);

        $client = $this->di['db']->getExistingModelById('Client', $data['client_id'], 'Client not found');
        $helpdesk = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['support_helpdesk_id'], 'Helpdesk invalid');

        return $this->getService()->ticketCreateForAdmin($client, $helpdesk, $data, $this->getIdentity());
    }

    /**
     * Action to close all tickets which have not received any replies for a
     * time defined in helpdesk.
     *
     * Run by cron job
     *
     * @return bool
     */
    public function batch_ticket_auto_close($data)
    {
        // Auto close support tickets
        $expiredArr = $this->getService()->getExpired();

        foreach ($expiredArr as $ticketArr) {
            $ticketModel = $this->di['db']->getExistingModelById('SupportTicket', $ticketArr['id'], 'Ticket not found');
            if (!$this->getService()->autoClose($ticketModel)) {
                $this->di['logger']->info('Ticket %s was not closed', $ticketModel->id);
            }
        }

        return true;
    }

    /**
     * Action to close all inquiries which have not received any replies for a
     * time defined in helpdesk.
     *
     * Run by cron job
     *
     * @return bool
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
     * Return tickets statuses with counter.
     */
    public function ticket_get_statuses($data)
    {
        if (isset($data['titles'])) {
            return $this->getService()->getStatuses();
        }

        return $this->getService()->counter();
    }

    /**
     * Get paginated list of inquiries.
     *
     * @return array
     */
    public function public_ticket_get_list($data)
    {
        [$sql, $bindings] = $this->getService()->publicGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);

        foreach ($pager['list'] as $key => $ticketArr) {
            $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $ticketArr['id'], 'Ticket not found');
            $pager['list'][$key] = $this->getService()->publicToApiArray($ticket);
        }

        return $pager;
    }

    /**
     * Create new inquiry. Send email.
     *
     * @return int - inquiry id
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_create($data)
    {
        $required = [
            'name' => 'Client name parameter is missing',
            'email' => 'Client email parameter is missing',
            'subject' => 'Subject parameter is missing',
            'message' => 'Ticket message is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->publicTicketCreate($data, $this->getIdentity());
    }

    /**
     * Get inquiry details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_get($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicToApiArray($model, true);
    }

    /**
     * Delete inquiry.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_delete($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicRm($model);
    }

    /**
     * Set id status to closed.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_close($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicCloseTicket($ticket, $this->getIdentity());
    }

    /**
     * Update inquiry details.
     *
     * @optional string $subject - subject
     * @optional string $status - status
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_update($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicTicketUpdate($model, $data);
    }

    /**
     * Post new reply to inquiry.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function public_ticket_reply($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
            'content' => 'Ticket content required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportPTicket', $data['id'], 'Ticket not found');

        return $this->getService()->publicTicketReply($ticket, $this->getIdentity(), $data['content']);
    }

    /**
     * Return tickets statuses with counter.
     */
    public function public_ticket_get_statuses($data)
    {
        if (isset($data['titles'])) {
            return $this->getService()->publicGetStatuses();
        }

        return $this->getService()->publicCounter();
    }

    /**
     * Get helpdesk list.
     *
     * @return array
     */
    public function helpdesk_get_list($data)
    {
        [$sql, $bindings] = $this->getService()->helpdeskGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();

        return $this->di['pager']->getSimpleResultSet($sql, $bindings, $per_page);
    }

    /**
     * Get pairs of helpdesks.
     *
     * @return array
     */
    public function helpdesk_get_pairs($data)
    {
        return $this->getService()->helpdeskGetPairs();
    }

    /**
     * Get helpdesk details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function helpdesk_get($data)
    {
        $required = [
            'id' => 'Help desk id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskToApiArray($model);
    }

    /**
     * Update helpdesk parameters.
     *
     * @optional string $name - helpdesk name
     * @optional string $email - helpdesk email
     * @optional string $can_reopen - flag to enable/disable ability to reopen closed tickets
     * @optional int $close_after - time to wait for reply before auto closing ticket
     * @optional string $signature - helpdesk signature
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function helpdesk_update($data)
    {
        $required = [
            'id' => 'Help desk id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskUpdate($model, $data);
    }

    /**
     * Create new helpdesk.
     *
     * @optional string $email - helpdesk email
     * @optional string $can_reopen - flag to enable/disable ability to reopen closed tickets
     * @optional int $close_after - time to wait for reply before auto closing ticket
     * @optional string $signature - helpdesk signature
     *
     * @return int - id
     *
     * @throws \FOSSBilling\Exception
     */
    public function helpdesk_create($data)
    {
        $required = [
            'name' => 'Help desk title is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->helpdeskCreate($data);
    }

    /**
     * Delete helpdesk.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function helpdesk_delete($data)
    {
        $required = [
            'id' => 'Help desk id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportHelpdesk', $data['id'], 'Help desk not found');

        return $this->getService()->helpdeskRm($model);
    }

    /**
     * Get list of canned responses.
     *
     * @return array
     */
    public function canned_get_list($data)
    {
        [$sql, $bindings] = $this->getService()->cannedGetSearchQuery($data);

        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $bindings, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $staff = $this->di['db']->getExistingModelById('SupportPr', $item['id'], 'Canned response not found');
            $pager['list'][$key] = $this->getService()->cannedToApiArray($staff);
        }

        return $pager;
    }

    /**
     * Get list of canned responses grouped by category.
     */
    public function canned_pairs(): array
    {
        $res = $this->di['db']->getAssoc('SELECT id, title FROM support_pr_category WHERE 1');
        $list = [];
        foreach ($res as $id => $title) {
            $list[$title] = $this->di['db']->getAssoc('SELECT id, title FROM support_pr WHERE support_pr_category_id = :id', ['id' => $id]);
        }

        return $list;
    }

    /**
     * Get canned response details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_get($data)
    {
        $required = [
            'id' => 'Canned reply id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedToApiArray($model);
    }

    /**
     * Delete canned response.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_delete($data)
    {
        $required = [
            'id' => 'Canned reply id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedRm($model);
    }

    /**
     * Create new canned response.
     *
     * @optional string $content - canned response content
     *
     * @return int
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_create($data)
    {
        $required = [
            'title' => 'Canned reply title is missing',
            'category_id' => 'Canned reply category id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $content = $data['content'] ?? null;

        return $this->getService()->cannedCreate($data['title'], $data['category_id'], $content);
    }

    /**
     * Update canned response.
     *
     * @optional string $title - canned response title
     * @optional int $category_id - canned response category id
     * @optional string $content - canned response content
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_update($data)
    {
        $required = [
            'id' => 'Canned reply id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPr', $data['id'], 'Canned reply not found');

        return $this->getService()->cannedUpdate($model, $data);
    }

    /**
     * Get canned response pairs.
     *
     * @return array
     */
    public function canned_category_pairs($data)
    {
        return $this->di['db']->getAssoc('SELECT id, title FROM support_pr_category WHERE 1');
    }

    /**
     * Get canned response category.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_category_get($data)
    {
        $required = [
            'id' => 'Canned category id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        return $this->getService()->cannedCategoryToApiArray($model);
    }

    /**
     * Get canned response category.
     *
     * @optional string $title - new category title
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_category_update($data)
    {
        $required = [
            'id' => 'Canned category id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        $title = $data['title'] ?? $model->title;

        return $this->getService()->cannedCategoryUpdate($model, $title);
    }

    /**
     * Delete canned response category.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_category_delete($data)
    {
        $required = [
            'id' => 'Canned category id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportPrCategory', $data['id'], 'Canned category not found');

        return $this->getService()->cannedCategoryRm($model);
    }

    /**
     * Create canned response category.
     *
     * @return int - new category id
     *
     * @throws \FOSSBilling\Exception
     */
    public function canned_category_create($data)
    {
        $required = [
            'title' => 'Canned category title is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->cannedCategoryCreate($data['title']);
    }

    /**
     * Add note to support ticket.
     *
     * @return int - new note id
     *
     * @throws \FOSSBilling\Exception
     */
    public function note_create($data)
    {
        $required = [
            'ticket_id' => 'ticket_id is missing',
            'note' => 'Note is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $ticket = $this->di['db']->getExistingModelById('SupportTicket', $data['ticket_id'], 'Ticket not found');

        return $this->getService()->noteCreate($ticket, $this->getIdentity(), $data['note']);
    }

    /**
     * Delete note from support ticket.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function note_delete($data)
    {
        $required = [
            'id' => 'Note id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicketNote', $data['id'], 'Note not found');

        return $this->getService()->noteRm($model);
    }

    /**
     * Set support ticket related task to completed.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function task_complete($data)
    {
        $required = [
            'id' => 'Ticket id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('SupportTicket', $data['id'], 'Ticket not found');

        return $this->getService()->ticketTaskComplete($model);
    }

    /**
     * Deletes tickets with given IDs.
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
            $this->ticket_delete(['id' => $id]);
        }

        return true;
    }

    /**
     * Deletes tickets with given IDs.
     *
     * @return bool
     */
    public function batch_delete_public($data)
    {
        $required = [
            'ids' => 'IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->public_ticket_delete(['id' => $id]);
        }

        return true;
    }

    /*
    * Support Knowledge Base.
    */

    /**
     * Get paginated list of knowledge base articles.
     *
     * @return array
     */
    public function kb_article_get_list($data)
    {
        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;
        $cat = $data['cat'] ?? null;

        $pager = $this->getService()->kbSearchArticles($status, $search, $cat);

        foreach ($pager['list'] as $key => $item) {
            $article = $this->di['db']->getExistingModelById('SupportKbArticle', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbToApiArray($article);
        }

        return $pager;
    }

    /**
     * Get knowledge base article.
     *
     * @return array
     */
    public function kb_article_get($data)
    {
        $required = [
            'id' => 'Article id not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('SupportKbArticle', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_SupportKbArticle) {
            throw new \FOSSBilling\InformationException('Article not found');
        }

        return $this->getService()->kbToApiArray($model, true, $this->getIdentity());
    }

    /**
     * Create new knowledge base article.
     *
     * @optional string $status - knowledge base article status
     * @optional string $content - knowledge base article content
     *
     * @return array
     */
    public function kb_article_create($data)
    {
        $required = [
            'kb_article_category_id' => 'Article category id not passed',
            'title' => 'Article title not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $articleCategoryId = $data['kb_article_category_id'];
        $title = $data['title'];
        $status = $data['status'] ?? \Model_SupportKbArticle::DRAFT;
        $content = $data['content'] ?? null;

        return $this->getService()->kbCreateArticle($articleCategoryId, $title, $status, $content);
    }

    /**
     * Update knowledge base article.
     *
     * @optional string $title - knowledge base article title
     * @optional int $kb_article_category_id - knowledge base category ID
     * @optional string $slug - knowledge base article slug
     * @optional string $status - knowledge base article status
     * @optional string $content - knowledge base article content
     * @optional int $views - knowledge base article views counter
     *
     * @return bool
     */
    public function kb_article_update($data)
    {
        $required = [
            'id' => 'Article ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $articleCategoryId = $data['kb_article_category_id'] ?? null;
        $title = $data['title'] ?? null;
        $slug = $data['slug'] ?? null;
        $status = $data['status'] ?? null;
        $content = $data['content'] ?? null;
        $views = $data['views'] ?? null;

        return $this->getService()->kbUpdateArticle($data['id'], $articleCategoryId, $title, $slug, $status, $content, $views);
    }

    /**
     * Delete knowledge base article.
     *
     * @return bool
     */
    public function kb_article_delete($data)
    {
        $required = [
            'id' => 'Article ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('SupportKbArticle', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_SupportKbArticle) {
            throw new \FOSSBilling\InformationException('Article not found');
        }

        $this->getService()->kbRm($model);

        return true;
    }

    /**
     * Get paginated list of knowledge base categories.
     *
     * @return array
     */
    public function kb_category_get_list($data)
    {
        [$sql, $bindings] = $this->getService()->kbCategoryGetSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $category = $this->di['db']->getExistingModelById('SupportKbArticleCategory', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbCategoryToApiArray($category, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get knowledge base category.
     *
     * @return array
     */
    public function kb_category_get($data)
    {
        $required = [
            'id' => 'Category ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('SupportKbArticleCategory', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_SupportKbArticleCategory) {
            throw new \FOSSBilling\InformationException('Article Category not found');
        }

        return $this->getService()->kbCategoryToApiArray($model);
    }

    /**
     * Create new knowledge base category.
     *
     * @optional string $description - knowledge base category description
     *
     * @return array
     */
    public function kb_category_create($data)
    {
        $required = [
            'title' => 'Category title not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $title = $data['title'];
        $description = $data['description'] ?? null;

        return $this->getService()->kbCreateCategory($title, $description);
    }

    /**
     * Update knowledge base category.
     *
     * @optional string $title - knowledge base category title
     * @optional string $slug  - knowledge base category slug
     * @optional string $description - knowledge base category description
     *
     * @return array
     */
    public function kb_category_update($data)
    {
        $required = [
            'id' => 'Category ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('SupportKbArticleCategory', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_SupportKbArticleCategory) {
            throw new \FOSSBilling\InformationException('Article Category not found');
        }

        $title = $data['title'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        return $this->getService()->kbUpdateCategory($model, $title, $slug, $description);
    }

    /**
     * Delete knowledge base category.
     *
     * @return bool
     */
    public function kb_category_delete($data)
    {
        $required = [
            'id' => 'Category ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('SupportKbArticleCategory', 'id = ?', [$data['id']]);

        if (!$model instanceof \Model_SupportKbArticleCategory) {
            throw new \FOSSBilling\InformationException('Category not found');
        }

        return $this->getService()->kbCategoryRm($model);
    }

    /**
     * Get knowledge base categories id, title pairs.
     *
     * @return array
     */
    public function kb_category_get_pairs($data)
    {
        return $this->getService()->kbCategoryGetPairs();
    }
}
