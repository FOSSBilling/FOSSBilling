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
 * Support management module.
 */

namespace Box\Mod\Support\Api;

use Box\Mod\Support\Entity\CannedResponse;
use Box\Mod\Support\Entity\CannedResponseCategory;
use Box\Mod\Support\Entity\Helpdesk;
use Box\Mod\Support\Entity\KbArticle;
use Box\Mod\Support\Entity\KbArticleCategory;
use Box\Mod\Support\Entity\SupportTicket;
use Box\Mod\Support\HiddenHelpdesks;
use Box\Mod\Support\HiddenKbCategories;
use Box\Mod\Support\TicketAssign;
use Box\Mod\Support\TicketBulk;
use Box\Mod\Support\TicketEscalation;
use Box\Mod\Support\TicketFeedback;
use Box\Mod\Support\TicketFiles;
use Box\Mod\Support\TicketMerge;
use Box\Mod\Support\TicketSecrets;
use FOSSBilling\Core\HtmlSanitizerFactory;
use FOSSBilling\Core\Pagination\Options;
use FOSSBilling\Core\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Core\Api\AbstractApi
{
    /**
     * Get tickets list.
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     * @optional string $sort - sort column: 'id', 'status', 'priority', 'subject', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     */
    public function ticket_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $repo = $this->getService()->getSupportTicketRepository();
        // Pinned tickets first; the assignee filter ('assigned': me, none,
        // any or a staff id) narrows the list.
        $data['pinned_first'] = TicketBulk::pinnedIds($this->getDi());
        $data['assigned_filter'] = TicketAssign::listFilter($data['assigned'] ?? '', (int) $this->getIdentity()->getId());

        return $this->getDi()['pager']->paginateMappedQuery(
            $repo->getSearchQueryBuilder($data),
            Options::fromArray($data),
            fn (SupportTicket $ticket): array => $this->getService()->toApiArray($ticket, false, $this->getIdentity()),
        );
    }

    /**
     * Staff members a ticket can be assigned to: id => name.
     */
    public function ticket_assignable_staff(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return TicketAssign::staff($this->getDi());
    }

    /**
     * Return ticket full details.
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function ticket_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $model = $this->getService()->getTicketById((int) $data['id']);

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update ticket details.
     *
     * @optional int $support_helpdesk_id - ticket helpdesk id
     * @optional string $status - ticket status
     * @optional string $subject - ticket subject
     * @optional string $priority - ticket priority: low, medium or high
     * @optional int $assigned_to - assignee staff id (0: nobody)
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function ticket_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $model = $this->getService()->getTicketById((int) $data['id']);

        // Sanitize subject if provided — plain text, no HTML
        if (isset($data['subject'])) {
            $data['subject'] = htmlspecialchars(trim(strip_tags(str_replace("\0", '', $data['subject']))), ENT_QUOTES | ENT_HTML5, 'UTF-8', false);
        }

        $result = $this->getService()->ticketUpdate($model, $data);

        if (array_key_exists('assigned_to', $data)) {
            TicketAssign::assign($this->getDi(), (int) $model->getId(), (int) $data['assigned_to'] > 0 ? (int) $data['assigned_to'] : null, (int) $this->getIdentity()->getId());
        }

        return $result;
    }

    /**
     * Update ticket message.
     */
    #[RequiredParams(['id' => 'Ticket message ID is missing', 'content' => 'Ticket message content is missing'])]
    public function ticket_message_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $data['content'] = HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']);

        $model = $this->getService()->getTicketMessageById((int) $data['id']);

        return $this->getService()->ticketMessageUpdate($model, $data['content'], $this->getIdentity());
    }

    /**
     * Return the edit history of a ticket message, most recent edit first.
     */
    #[RequiredParams(['id' => 'Ticket message ID is missing'])]
    public function ticket_message_history_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $model = $this->getService()->getTicketMessageById((int) $data['id']);

        return $this->getService()->getMessageHistory($model);
    }

    /**
     * Delete ticket.
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function ticket_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $model = $this->getService()->getTicketById((int) $data['id']);

        return $this->getService()->rm($model);
    }

    /**
     * Add new conversation message to to ticket.
     *
     * @return int - ticket message id
     */
    #[RequiredParams(['id' => 'Ticket ID is missing', 'content' => 'Ticket message content is missing'])]
    public function ticket_reply(array $data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $data['content'] = HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']);

        $ticket = $this->getService()->getTicketById((int) $data['id']);

        return $this->getService()->ticketReply($ticket, $this->getIdentity(), $data['content']);
    }

    /**
     * Close ticket.
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function ticket_close(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $ticket = $this->getService()->getTicketById((int) $data['id']);

        if ($ticket->getStatus() === SupportTicket::STATUS_CLOSED) {
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
    #[RequiredParams(['client_id' => 'Client ID is missing', 'content' => 'Ticket content required', 'subject' => 'Ticket subject required', 'support_helpdesk_id' => 'Ticket support_helpdesk_id is required'])]
    public function ticket_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $data['content'] = HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']);

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $helpdesk = $repo->find((int) $data['support_helpdesk_id']);
        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\Core\Exception\InformationException('Helpdesk invalid');
        }

        return $this->getService()->ticketCreateForAdmin((int) $data['client_id'], $helpdesk, $data, $this->getIdentity());
    }

    /**
     * Action to close all tickets which have not received any replies for a
     * time defined in helpdesk.
     *
     * Run by cron job
     */
    public function batch_ticket_auto_close($data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        // Auto close support tickets
        $expiredArr = $this->getService()->getExpired();

        foreach ($expiredArr as $ticketArr) {
            try {
                $ticketModel = $this->getService()->getTicketById((int) $ticketArr['id']);
                if (!$this->getService()->autoClose($ticketModel)) {
                    $this->getDi()['logger']->info('Ticket {ticket_id} was not closed', ['ticket_id' => $ticketModel->getId()]);
                }
            } catch (\Throwable $e) {
                // One broken ticket must not stop the cron from closing the rest.
                $this->getDi()['logger']->error('Support: auto-closing ticket {ticket_id} failed: {error}', ['ticket_id' => $ticketArr['id'] ?? '?', 'error' => $e->getMessage()]);
            }
        }

        TicketBulk::sweep($this->getDi());
        TicketAssign::sweep($this->getDi());
        TicketFeedback::sweep($this->getDi());

        try {
            TicketMerge::sweep($this->getDi());
        } catch (\Throwable $e) {
            $this->getDi()['logger']->error('Support: moving replies of merged tickets failed: {error}', ['error' => $e->getMessage()]);
        }

        try {
            TicketEscalation::run($this->getDi());
        } catch (\Throwable $e) {
            $this->getDi()['logger']->error('Support: escalation rules failed: {error}', ['error' => $e->getMessage()]);
        }
        TicketEscalation::sweep($this->getDi());

        try {
            TicketFiles::prune($this->getDi());
        } catch (\Throwable $e) {
            $this->getDi()['logger']->error('Support: removing old ticket files failed: {error}', ['error' => $e->getMessage()]);
        }

        try {
            TicketSecrets::prune($this->getDi());
        } catch (\Throwable $e) {
            $this->getDi()['logger']->error('Support: expiring old ticket secrets failed: {error}', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /** Deliver pending close notifications after the rest of cron has completed. */
    public function batch_ticket_retry_close_events($data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');
        if (!\Box\Mod\Support\TicketCloseOutbox::retryPending($this->getDi())) {
            throw new \FOSSBilling\Core\Exception\InformationException('The close-event retry stopped after a database error; pending events will be retried on the next run.');
        }

        return true;
    }

    /**
     * Return tickets statuses with counter.
     */
    public function ticket_get_statuses(array $data): array
    {
        $this->checkPermissions('support', 'view');

        if (isset($data['titles'])) {
            return $this->getService()->getStatuses();
        }

        return $this->getService()->counter();
    }

    /**
     * Get helpdesk list.
     *
     * @optional string $sort - sort column: 'id', 'name', 'email', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     */
    public function helpdesk_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        $result = $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data), $this->getIdentity());
        $result['list'] = HiddenHelpdesks::withFlags($this->getDi(), $result['list'] ?? []);

        return $result;
    }

    /**
     * Get pairs of helpdesks.
     */
    public function helpdesk_get_pairs(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return $this->getService()->getHelpdeskRepository()->getPairs();
    }

    /**
     * Get helpdesk details.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\Core\Exception\InformationException('Help desk not found');
        }

        return $model->toApiArray($this->getIdentity())
            + ['hidden' => HiddenHelpdesks::isHidden($this->getDi(), (int) $model->getId())]
            + ['feedback_request' => TicketFeedback::helpdeskOn($this->getDi(), (int) $model->getId())];
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
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_helpdesk');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\Core\Exception\InformationException('Help desk not found');
        }

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
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['name' => 'Help desk title is missing'])]
    public function helpdesk_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_helpdesk');

        return $this->getService()->helpdeskCreate($data);
    }

    /**
     * Delete helpdesk.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_helpdesk');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\Core\Exception\InformationException('Help desk not found');
        }

        return $this->getService()->helpdeskRm($model);
    }

    /**
     * Get list of canned responses.
     *
     * @optional string $sort - sort column: 'id', 'title', 'category', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     */
    public function canned_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data));
    }

    /**
     * Get list of canned responses grouped by category.
     */
    public function canned_pairs(): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        return $repo->getGroupedPairs();
    }

    /**
     * Get canned response details.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned reply not found');
        }

        return $model->toApiArray();
    }

    /**
     * Delete canned response.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned reply not found');
        }

        return $this->getService()->cannedRm($model);
    }

    /**
     * Create new canned response.
     *
     * @optional string $content - canned response content
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['title' => 'Canned reply title is missing', 'category_id' => 'Canned reply category ID is missing'])]
    public function canned_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_canned');

        $content = $data['content'] ?? null;

        return $this->getService()->cannedCreate($data['title'], (int) $data['category_id'], $content);
    }

    /**
     * Update canned response.
     *
     * @optional string $title - canned response title
     * @optional int $category_id - canned response category id
     * @optional string $content - canned response content
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned reply not found');
        }

        return $this->getService()->cannedUpdate($model, $data);
    }

    /**
     * Get canned response pairs.
     */
    public function canned_category_pairs(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        return $repo->getPairs();
    }

    /**
     * Get canned response category.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned category not found');
        }

        return $model->toApiArray();
    }

    /**
     * Get canned response category.
     *
     * @optional string $title - new category title
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned category not found');
        }

        $title = $data['title'] ?? $model->getTitle();

        return $this->getService()->cannedCategoryUpdate($model, $title);
    }

    /**
     * Delete canned response category.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Canned category not found');
        }

        return $this->getService()->cannedCategoryRm($model);
    }

    /**
     * Create canned response category.
     *
     * @return int - new category id
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['title' => 'Canned category title is missing'])]
    public function canned_category_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_canned');

        return $this->getService()->cannedCategoryCreate($data['title']);
    }

    /**
     * Add note to support ticket.
     *
     * @return int - new note id
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['ticket_id' => 'ticket_ID is missing', 'note' => 'Note is missing'])]
    public function note_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $ticket = $this->getService()->getTicketById((int) $data['ticket_id']);

        $data['note'] = HtmlSanitizerFactory::sanitizePlainText($data['note']);

        if ($data['note'] === '') {
            throw new \FOSSBilling\Core\Exception\InformationException('Note is empty');
        }

        return $this->getService()->noteCreate($ticket, $this->getIdentity(), $data['note']);
    }

    /**
     * Delete note from support ticket.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Note ID is missing'])]
    public function note_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $model = $this->getService()->getTicketNoteById((int) $data['id']);

        return $this->getService()->noteRm($model);
    }

    /**
     * Set support ticket related task to completed.
     *
     * @throws \FOSSBilling\Core\Exception\BaseException
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function task_complete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $model = $this->getService()->getTicketById((int) $data['id']);

        return $this->getService()->ticketTaskComplete($model);
    }

    /**
     * Deletes tickets with given IDs.
     */
    #[RequiredParams(['ids' => 'IDs were not passed'])]
    public function batch_delete($data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        foreach (TicketBulk::ids($data['ids']) as $id) {
            $this->ticket_delete(['id' => $id]);
        }
        TicketBulk::sweep($this->getDi());
        TicketAssign::sweep($this->getDi());

        return true;
    }

    /**
     * Pin tickets so they stay at the top of the staff ticket list.
     *
     * @return int - how many tickets are pinned now
     */
    #[RequiredParams(['ids' => 'Ticket IDs were not passed'])]
    public function batch_ticket_pin($data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        return TicketBulk::pin($this->getDi(), TicketBulk::ids($data['ids']), (int) $this->getIdentity()->getId() ?: null);
    }

    /**
     * Unpin tickets.
     *
     * @return int - how many tickets were pinned
     */
    #[RequiredParams(['ids' => 'Ticket IDs were not passed'])]
    public function batch_ticket_unpin($data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        return TicketBulk::unpin($this->getDi(), TicketBulk::ids($data['ids']));
    }

    /**
     * Pinned ticket ids, newest first (for the staff list markers).
     *
     * @return list<int>
     */
    public function ticket_pinned_ids(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return TicketBulk::pinnedIds($this->getDi());
    }

    /**
     * Upload a file for a ticket reply (bound to the message on save).
     *
     * @return array{id: string, name: string, size: int, size_label: string}
     */
    #[RequiredParams(['ticket_id' => 'Ticket ID is missing'])]
    public function ticket_file_upload(array $data): array
    {
        $this->checkPermissions('support', 'manage_tickets');

        $file = $this->getDi()['request']->files->get('file');
        $adminId = $this->getIdentity() instanceof \Box\Mod\Staff\Entity\Admin ? (int) $this->getIdentity()->getId() : 0;

        return TicketFiles::uploadForStaff($this->getDi(), $adminId, (int) $data['ticket_id'], $file);
    }

    /**
     * Discard a waiting upload.
     */
    #[RequiredParams(['id' => 'File ID is missing'])]
    public function ticket_file_discard(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $adminId = $this->getIdentity() instanceof \Box\Mod\Staff\Entity\Admin ? (int) $this->getIdentity()->getId() : 0;
        TicketFiles::discard($this->getDi(), (string) $data['id'], 'admin', $adminId);

        return true;
    }

    /**
     * Remove an attached file (the message keeps a removed marker).
     */
    #[RequiredParams(['id' => 'File ID is missing'])]
    public function ticket_file_remove(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $adminId = $this->getIdentity() instanceof \Box\Mod\Staff\Entity\Admin ? (int) $this->getIdentity()->getId() : null;
        TicketFiles::remove($this->getDi(), (string) $data['id'], $adminId);

        return true;
    }

    /**
     * How long attached files are kept (days, 0: forever).
     */
    public function ticket_files_update(array $data): bool
    {
        $this->getDi()['mod_service']('extension')->hasManagePermission('mod_support');

        $days = (int) ($data['ticket_files_days'] ?? TicketFiles::KEEP_DAYS);
        if ($days < 0 || $days > 3650) {
            throw new \FOSSBilling\Core\Exception\InformationException('Keep attached files between 0 and 3650 days.');
        }

        $extension = $this->getDi()['mod_service']('extension');

        return $extension->setConfig(array_merge($extension->getConfig('mod_support'), ['ext' => 'mod_support', 'ticket_files_days' => (string) $days]));
    }

    /**
     * Close tickets.
     *
     * @return int - how many tickets were closed now
     */
    #[RequiredParams(['ids' => 'Ticket IDs were not passed'])]
    public function batch_ticket_close($data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $closed = 0;
        foreach (TicketBulk::ids($data['ids']) as $id) {
            $ticket = $this->getService()->getTicketById($id);
            if ($ticket->getStatus() !== SupportTicket::STATUS_CLOSED) {
                $this->getService()->closeTicket($ticket, $this->getIdentity());
                ++$closed;
            }
        }

        return $closed;
    }

    /*
     * Support ticket secrets.
     */
    /**
     * A ticket's secrets (metadata only) and their audit history.
     */
    #[RequiredParams(['ticket_id' => 'Ticket ID is missing'])]
    public function ticket_secret_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $identity = $this->getIdentity();
        $canOpen = $identity instanceof \Box\Mod\Staff\Entity\Admin
            && $this->getDi()['mod_service']('staff')->hasPermission($identity, 'support', 'open_secrets');
        $canDelete = $identity instanceof \Box\Mod\Staff\Entity\Admin
            && $this->getDi()['mod_service']('staff')->hasPermission($identity, 'support', 'manage_tickets');

        return TicketSecrets::listForTicket($this->getDi(), (int) $data['ticket_id'])
            + ['can_open' => $canOpen, 'can_delete' => $canDelete];
    }

    /**
     * Open a secret (audited). Needs the open_secrets permission.
     */
    #[RequiredParams(['id' => 'Secret ID is missing'])]
    public function ticket_secret_open(array $data): array
    {
        $this->checkPermissions('support', 'open_secrets');

        $identity = $this->getIdentity();
        $adminId = $identity instanceof \Box\Mod\Staff\Entity\Admin ? (int) $identity->getId() : 0;

        return TicketSecrets::openForStaff($this->getDi(), (int) $data['id'], $adminId);
    }

    /**
     * Delete a secret (audited).
     */
    #[RequiredParams(['id' => 'Secret ID is missing'])]
    public function ticket_secret_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $identity = $this->getIdentity();
        $adminId = $identity instanceof \Box\Mod\Staff\Entity\Admin ? (int) $identity->getId() : null;
        TicketSecrets::delete($this->getDi(), (int) $data['id'], $adminId, null);

        return true;
    }

    /*
     * Support ticket escalation rules.
     */
    /**
     * Staff report on reply ratings, feedback scores and recent comments.
     */
    public function ticket_feedback_report(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return TicketFeedback::report($this->getDi(), $data['period'] ?? '90');
    }

    /**
     * List escalation rules, by name.
     */
    public function escalation_rule_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return TicketEscalation::rules($this->getDi());
    }

    /**
     * Get an escalation rule.
     */
    #[RequiredParams(['id' => 'Rule ID was not passed'])]
    public function escalation_rule_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return TicketEscalation::get($this->getDi(), (int) $data['id']);
    }

    /**
     * Create or update an escalation rule; returns its id.
     */
    public function escalation_rule_save(array $data): int
    {
        $this->getDi()['mod_service']('extension')->hasManagePermission('mod_support');

        return TicketEscalation::save($this->getDi(), $data);
    }

    /**
     * Delete an escalation rule.
     */
    #[RequiredParams(['id' => 'Rule ID was not passed'])]
    public function escalation_rule_delete(array $data): bool
    {
        $this->getDi()['mod_service']('extension')->hasManagePermission('mod_support');

        TicketEscalation::delete($this->getDi(), (int) $data['id']);

        return true;
    }

    /**
     * Merge tickets into the oldest one; returns its id. Cannot be undone.
     */
    public function ticket_merge(array $data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $ids = $data['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = preg_split('/[\s,#]+/', (string) $ids) ?: [];
        }
        if (isset($data['id']) && (is_int($data['id']) || ctype_digit((string) $data['id']))) {
            $ids[] = (int) $data['id'];
        }
        $subject = isset($data['subject']) ? trim(strip_tags((string) $data['subject'])) : null;

        $merged = TicketMerge::merge($this->getDi(), $ids, $subject === '' ? null : $subject, (int) $this->getIdentity()->getId());

        $this->di['event_dispatcher']->dispatch(new \Box\Mod\Support\Event\AfterTicketMergedEvent($merged['id'], $merged['merged']));
        $this->di['logger']->info('Merged tickets #{ids} into ticket #{id}', ['ids' => implode(',', $merged['merged']), 'id' => $merged['id']]);

        return $merged['id'];
    }

    /*
     * Support Knowledge Base.
     */
    /**
     * Get paginated list of knowledge base articles.
     *
     * @optional string $sort - sort column: 'id', 'title', 'slug', 'status', 'views', 'category', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     */
    public function kb_article_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? $data['cat'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $qb = $repo->getSearchQueryBuilder([
            'status' => $status,
            'search' => $search,
            'kb_article_category_id' => $cat,
            'sort' => $data['sort'] ?? null,
            'direction' => $data['direction'] ?? null,
        ]);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data), $this->getIdentity());
    }

    /**
     * Get knowledge base article.
     */
    #[RequiredParams(['id' => 'Article ID was not passed'])]
    public function kb_article_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $article = $repo->find((int) $data['id']);

        if (!$article instanceof KbArticle) {
            throw new \FOSSBilling\Core\Exception\InformationException('Article not found');
        }

        return $article->toApiArray($this->getIdentity(), includeContent: true);
    }

    /**
     * Create new knowledge base article.
     *
     * @optional string $status - knowledge base article status
     * @optional string $content - knowledge base article content
     */
    #[RequiredParams(['kb_article_category_id' => 'Article category ID was not passed', 'title' => 'Article title not passed'])]
    public function kb_article_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_kb');

        $articleCategoryId = (int) $data['kb_article_category_id'];
        $status = $data['status'] ?? KbArticle::DRAFT;

        $title = trim(strip_tags(str_replace("\0", '', $data['title'])));
        $content = isset($data['content']) ? HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']) : null;

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
     */
    #[RequiredParams(['id' => 'Article ID was not passed'])]
    public function kb_article_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_kb');

        $articleCategoryId = isset($data['kb_article_category_id']) ? (int) $data['kb_article_category_id'] : null;
        $title = isset($data['title']) ? trim(strip_tags(str_replace("\0", '', $data['title']))) : null;
        $slug = $data['slug'] ?? null;
        $status = $data['status'] ?? null;
        $content = isset($data['content']) ? HtmlSanitizerFactory::sanitizeMarkdownContent($data['content']) : null;
        $views = isset($data['views']) ? (int) $data['views'] : null;

        return $this->getService()->kbUpdateArticle((int) $data['id'], $articleCategoryId, $title, $slug, $status, $content, $views);
    }

    /**
     * Delete knowledge base article.
     */
    #[RequiredParams(['id' => 'Article ID was not passed'])]
    public function kb_article_delete($data): bool
    {
        $this->checkPermissions('support', 'manage_kb');

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $article = $repo->find((int) $data['id']);

        if (!$article instanceof KbArticle) {
            throw new \FOSSBilling\Core\Exception\InformationException('Article not found');
        }

        $this->getService()->kbRm($article);

        return true;
    }

    /**
     * Get paginated list of knowledge base categories.
     *
     * @optional string $sort - sort column: 'id', 'title', 'slug', 'created_at' or 'updated_at'
     * @optional string $direction - sort direction: 'ASC' or 'DESC'
     */
    public function kb_category_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        $result = $this->getDi()['pager']->paginateDoctrineQuery($qb, Options::fromArray($data), $this->getIdentity(), $data['q'] ?? null);
        $result['list'] = HiddenKbCategories::withFlags($this->getDi(), $result['list'] ?? []);

        return $result;
    }

    /**
     * Get knowledge base category.
     */
    #[RequiredParams(['id' => 'Category ID was not passed'])]
    public function kb_category_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $cat = $repo->find((int) $data['id']);

        if (!$cat instanceof KbArticleCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Article Category not found');
        }

        return $cat->toApiArray($this->getIdentity()) + ['hidden' => HiddenKbCategories::isHidden($this->getDi(), $cat->getId())];
    }

    /**
     * Create new knowledge base category.
     *
     * @optional string $description - knowledge base category description
     */
    #[RequiredParams(['title' => 'Category title not passed'])]
    public function kb_category_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_kb');

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
     */
    #[RequiredParams(['id' => 'Category ID was not passed'])]
    public function kb_category_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_kb');

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $cat = $repo->find((int) $data['id']);

        if (!$cat instanceof KbArticleCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Article Category not found');
        }

        $title = $data['title'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        $result = $this->getService()->kbUpdateCategory($cat, $title, $slug, $description);
        if (array_key_exists('hidden', $data)) {
            HiddenKbCategories::save($this->getDi(), (int) $cat->getId(), $data['hidden']);
        }

        return $result;
    }

    /**
     * Delete knowledge base category.
     */
    #[RequiredParams(['id' => 'Category ID was not passed'])]
    public function kb_category_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_kb');

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $cat = $repo->find((int) $data['id']);

        if (!$cat instanceof KbArticleCategory) {
            throw new \FOSSBilling\Core\Exception\InformationException('Category not found');
        }

        return $this->getService()->kbCategoryRm($cat);
    }

    /**
     * Get knowledge base categories id, title pairs.
     */
    public function kb_category_get_pairs(array $data): array
    {
        $this->checkPermissions('support', 'view');

        return $this->getService()->getKbArticleCategoryRepository()->getPairs();
    }
}
