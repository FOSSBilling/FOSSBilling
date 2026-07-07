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
use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Get tickets list.
     *
     * @optional string status - filter tickets by status
     * @optional string date_from - show tickets created since this day. Can be any string parsable by strtotime()
     * @optional string date_to - show tickets created until this day. Can be any string parsable by strtotime()
     */
    public function ticket_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $repo = $this->getService()->getSupportTicketRepository();

        return $this->getDi()['pager']->paginateMappedQuery(
            $repo->getSearchQueryBuilder($data),
            PaginationOptions::fromArray($data),
            fn (SupportTicket $ticket): array => $this->getService()->toApiArray($ticket, false, $this->getIdentity()),
        );
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
     * @optional string $priority - ticket priority
     */
    #[RequiredParams(['id' => 'Ticket ID is missing'])]
    public function ticket_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $model = $this->getService()->getTicketById((int) $data['id']);

        // Sanitize subject if provided
        if (isset($data['subject'])) {
            $data['subject'] = \FOSSBilling\Tools::sanitizeContent($data['subject'], false);
        }

        return $this->getService()->ticketUpdate($model, $data);
    }

    /**
     * Update ticket message.
     */
    #[RequiredParams(['id' => 'Ticket message ID is missing', 'content' => 'Ticket message content is missing'])]
    public function ticket_message_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_tickets');

        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']);

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

        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']);

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

        $data['content'] = \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']);

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $helpdesk = $repo->find((int) $data['support_helpdesk_id']);
        if (!$helpdesk instanceof Helpdesk) {
            throw new \FOSSBilling\InformationException('Helpdesk invalid');
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
            $ticketModel = $this->getService()->getTicketById((int) $ticketArr['id']);
            if (!$this->getService()->autoClose($ticketModel)) {
                $this->getDi()['logger']->info('Ticket %s was not closed', $ticketModel->getId());
            }
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
     */
    public function helpdesk_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data), $this->getIdentity());
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\InformationException('Help desk not found');
        }

        return $model->toApiArray($this->getIdentity());
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_helpdesk');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\InformationException('Help desk not found');
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
     * @throws \FOSSBilling\Exception
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Help desk ID is missing'])]
    public function helpdesk_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_helpdesk');

        /** @var \Box\Mod\Support\Repository\HelpdeskRepository $repo */
        $repo = $this->getService()->getHelpdeskRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof Helpdesk) {
            throw new \FOSSBilling\InformationException('Help desk not found');
        }

        return $this->getService()->helpdeskRm($model);
    }

    /**
     * Get list of canned responses.
     */
    public function canned_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data));
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\InformationException('Canned reply not found');
        }

        return $model->toApiArray();
    }

    /**
     * Delete canned response.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\InformationException('Canned reply not found');
        }

        return $this->getService()->cannedRm($model);
    }

    /**
     * Create new canned response.
     *
     * @optional string $content - canned response content
     *
     * @throws \FOSSBilling\Exception
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned reply ID is missing'])]
    public function canned_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseRepository $repo */
        $repo = $this->getService()->getCannedResponseRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponse) {
            throw new \FOSSBilling\InformationException('Canned reply not found');
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_get(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\InformationException('Canned category not found');
        }

        return $model->toApiArray();
    }

    /**
     * Get canned response category.
     *
     * @optional string $title - new category title
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_update(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\InformationException('Canned category not found');
        }

        $title = $data['title'] ?? $model->getTitle();

        return $this->getService()->cannedCategoryUpdate($model, $title);
    }

    /**
     * Delete canned response category.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Canned category ID is missing'])]
    public function canned_category_delete(array $data): bool
    {
        $this->checkPermissions('support', 'manage_canned');

        /** @var \Box\Mod\Support\Repository\CannedResponseCategoryRepository $repo */
        $repo = $this->getService()->getCannedResponseCategoryRepository();

        $model = $repo->find((int) $data['id']);
        if (!$model instanceof CannedResponseCategory) {
            throw new \FOSSBilling\InformationException('Canned category not found');
        }

        return $this->getService()->cannedCategoryRm($model);
    }

    /**
     * Create canned response category.
     *
     * @return int - new category id
     *
     * @throws \FOSSBilling\Exception
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
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['ticket_id' => 'ticket_ID is missing', 'note' => 'Note is missing'])]
    public function note_create(array $data): int
    {
        $this->checkPermissions('support', 'manage_tickets');

        $ticket = $this->getService()->getTicketById((int) $data['ticket_id']);

        return $this->getService()->noteCreate($ticket, $this->getIdentity(), $data['note']);
    }

    /**
     * Delete note from support ticket.
     *
     * @throws \FOSSBilling\Exception
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
     * @throws \FOSSBilling\Exception
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

        foreach ($data['ids'] as $id) {
            $this->ticket_delete(['id' => $id]);
        }

        return true;
    }

    /*
     * Support Knowledge Base.
     */
    /**
     * Get paginated list of knowledge base articles.
     */
    public function kb_article_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? $data['cat'] ?? null;

        /** @var \Box\Mod\Support\Repository\KbArticleRepository $repo */
        $repo = $this->getService()->getKbArticleRepository();

        $qb = $repo->getSearchQueryBuilder($status, $search, $cat);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data), $this->getIdentity());
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
            throw new \FOSSBilling\InformationException('Article not found');
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

        $title = \FOSSBilling\Tools::sanitizeContent($data['title'], false);
        $content = isset($data['content']) ? \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']) : null;

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
        $title = isset($data['title']) ? \FOSSBilling\Tools::sanitizeContent($data['title'], false) : null;
        $slug = $data['slug'] ?? null;
        $status = $data['status'] ?? null;
        $content = isset($data['content']) ? \FOSSBilling\Tools::sanitizeMarkdownContent($data['content']) : null;
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
            throw new \FOSSBilling\InformationException('Article not found');
        }

        $this->getService()->kbRm($article);

        return true;
    }

    /**
     * Get paginated list of knowledge base categories.
     */
    public function kb_category_get_list(array $data): array
    {
        $this->checkPermissions('support', 'view');

        /** @var \Box\Mod\Support\Repository\KbArticleCategoryRepository $repo */
        $repo = $this->getService()->getKbArticleCategoryRepository();

        $qb = $repo->getSearchQueryBuilder($data);

        return $this->getDi()['pager']->paginateDoctrineQuery($qb, PaginationOptions::fromArray($data), $this->getIdentity(), $data['q'] ?? null);
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
            throw new \FOSSBilling\InformationException('Article Category not found');
        }

        return $cat->toApiArray($this->getIdentity());
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
            throw new \FOSSBilling\InformationException('Article Category not found');
        }

        $title = $data['title'] ?? null;
        $slug = $data['slug'] ?? null;
        $description = $data['description'] ?? null;

        return $this->getService()->kbUpdateCategory($cat, $title, $slug, $description);
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
            throw new \FOSSBilling\InformationException('Category not found');
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
