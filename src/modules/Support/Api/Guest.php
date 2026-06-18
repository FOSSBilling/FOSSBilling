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

/**
 * Public tickets management.
 */

namespace Box\Mod\Support\Api;

use FOSSBilling\PaginationOptions;
use FOSSBilling\Validation\Api\RequiredParams;

class Guest extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Submit new public ticket.
     *
     * @return string - ticket hash
     */
    #[RequiredParams([
        'name' => 'Please enter your name',
        'email' => 'Please enter your email address',
        'subject' => 'Please enter the subject',
    ])]
    public function ticket_create(array $data): string
    {
        $this->getDi()['rate_limiter']->consumeOrThrow('guest_ticket_create', (string) $this->getIp());

        $content = $data['content'] ?? $data['message'] ?? null;
        if (!is_string($content) || strlen($content) < 4) {
            throw new \FOSSBilling\InformationException('Please enter your message');
        }

        // Sanitize message to prevent XSS attacks
        $data['content'] = \FOSSBilling\Tools::sanitizeContent($content, true);

        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get public ticket.
     *
     * @return array - ticket details
     */
    #[RequiredParams(['hash' => 'Public ticket hash required'])]
    public function ticket_get(array $data): array
    {
        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicToApiArray($publicTicket);
    }

    /**
     * Close public ticket.
     */
    #[RequiredParams(['hash' => 'Public ticket hash required'])]
    public function ticket_close(array $data): bool
    {
        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicCloseTicket($publicTicket, $this->getIdentity());
    }

    /**
     * Reply to public ticket.
     *
     * @return string - ticket hash
     */
    #[RequiredParams(['hash' => 'Public ticket hash required'])]
    public function ticket_reply(array $data): string
    {
        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);
        $message = $data['content'] ?? $data['message'] ?? null;

        if (!is_string($message)) {
            throw new \FOSSBilling\InformationException('Message cannot be empty');
        }

        // Sanitize message to prevent XSS attacks
        $message = \FOSSBilling\Tools::sanitizeContent($message, true);

        return $this->getService()->publicTicketReplyForGuest($publicTicket, $message);
    }

    /**
     * Get whether public tickets are enabled for guests.
     */
    public function public_tickets_enabled(): bool
    {
        return $this->getService()->publicTicketsEnabled();
    }

    public function helpdesk_get_pairs(): array
    {
        return $this->getService()->helpdeskGetPairs();
    }

    /*
     * Support Knowledge Base Functions.
     */
    /**
     * Get whether the knowledge base is enabled, or not.
     */
    public function kb_enabled(): bool
    {
        return $this->getService()->kbEnabled();
    }

    /**
     * Get paginated list of knowledge base articles.
     * Returns only active articles.
     */
    public function kb_article_get_list(array $data): array
    {
        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? null;

        $pager = $this->getService()->kbSearchArticles('active', $search, $cat, PaginationOptions::fromArray($data));

        foreach ($pager['list'] as $key => $item) {
            $article = $this->getDi()['db']->getExistingModelById('SupportKbArticle', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbToApiArray($article);
        }

        return $pager;
    }

    /**
     * Get active knowledge base article.
     */
    public function kb_article_get(array $data): array
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\InformationException('ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        $model = false;
        if ($id) {
            $model = $this->getService()->kbFindActiveArticleById((int) $id);
        } else {
            $model = $this->getService()->kbFindActiveArticleBySlug($slug);
        }

        if (!$model instanceof \Model_SupportKbArticle) {
            throw new \FOSSBilling\InformationException('Article item not found');
        }
        $this->getService()->kbHitView($model);

        return $this->getService()->kbToApiArray($model, true, $this->getIdentity());
    }

    /**
     * Get paginated list of knowledge base categories.
     */
    public function kb_category_get_list(array $data): array
    {
        $data['article_status'] = \Model_SupportKbArticle::ACTIVE;
        [$query, $bindings] = $this->getService()->kbCategoryGetSearchQuery($data);

        $pager = $this->getDi()['pager']->getPaginatedResultSet($query, $bindings, PaginationOptions::fromArray($data));

        $q = $data['q'] ?? null;

        foreach ($pager['list'] as $key => $item) {
            $category = $this->getDi()['db']->getExistingModelById('SupportKbArticleCategory', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbCategoryToApiArray($category, $this->getIdentity(), $q);
        }

        return $pager;
    }

    /**
     * Get knowledge base categories id, title pairs.
     */
    public function kb_category_get_pairs(array $data): array
    {
        return $this->getService()->kbCategoryGetPairs();
    }

    /**
     * Get knowledge base category by ID or SLUG.
     */
    public function kb_category_get(array $data): array
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\InformationException('Category ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        $model = false;
        if ($id) {
            $model = $this->getService()->kbFindCategoryById((int) $id);
        } else {
            $model = $this->getService()->kbFindCategoryBySlug($slug);
        }

        if (!$model instanceof \Model_SupportKbArticleCategory) {
            throw new \FOSSBilling\InformationException('Knowledge Base category not found');
        }

        return $this->getService()->kbCategoryToApiArray($model, $this->getIdentity());
    }
}
