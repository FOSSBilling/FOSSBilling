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
 * Public tickets management.
 */

namespace Box\Mod\Support\Api;

class Guest extends \Api_Abstract
{
    /**
     * Submit new public ticket.
     *
     * @return string - ticket hash
     */
    public function ticket_create(array $data): string
    {
        $required = [
            'name' => 'Please enter your name',
            'email' => 'Please enter your email',
            'subject' => 'Please enter your subject',
            'message' => 'Please enter your message',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (strlen((string) $data['message']) < 4) {
            throw new \FOSSBilling\InformationException('Please enter your message');
        }

        // Sanitize message to prevent XSS attacks
        $data['message'] = $this->di['tools']->sanitizeContent($data['message'], true);

        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get public ticket.
     *
     * @return array - ticket details
     */
    public function ticket_get(array $data): array
    {
        $required = [
            'hash' => 'Public ticket hash required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicToApiArray($publicTicket);
    }

    /**
     * Close public ticket.
     *
     * @return bool
     */
    public function ticket_close(array $data): bool
    {
        $required = [
            'hash' => 'Public ticket hash required',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        return $this->getService()->publicCloseTicket($publicTicket, $this->getIdentity());
    }

    /**
     * Reply to public ticket.
     *
     * @return string - ticket hash
     */
    public function ticket_reply(array $data): string
    {
        $required = [
            'hash' => 'Public ticket hash required',
            'message' => 'Message is required and cannot be blank',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

        // Sanitize message to prevent XSS attacks
        $data['message'] = $this->di['tools']->sanitizeContent($data['message'], true);

        return $this->getService()->publicTicketReplyForGuest($publicTicket, $data['message']);
    }

    /*
    * Support Knowledge Base Functions.
    */

    /**
     * Get whether the knowledge base is enabled, or not.
     *
     * @return bool
     */
    public function kb_enabled(): bool
    {
        return $this->getService()->kbEnabled();
    }

    /**
     * Get paginated list of knowledge base articles.
     * Returns only active articles.
     *
     * @return array
     */
    public function kb_article_get_list(array $data): array
    {
        $data['status'] = 'active';

        $status = $data['status'];
        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? null;
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $page = $data['page'] ?? null;

        $pager = $this->getService()->kbSearchArticles($status, $search, $cat, $per_page, $page);

        foreach ($pager['list'] as $key => $item) {
            $article = $this->di['db']->getExistingModelById('SupportKbArticle', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbToApiArray($article);
        }

        return $pager;
    }

    /**
     * Get active knowledge base article.
     *
     * @return array
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
            $model = $this->getService()->kbFindActiveArticleById($id);
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
     *
     * @return array
     */
    public function kb_category_get_list(array $data): array
    {
        $data['article_status'] = \Model_SupportKbArticle::ACTIVE;
        [$query, $bindings] = $this->getService()->kbCategoryGetSearchQuery($data);

        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($query, $bindings, $per_page);

        $q = $data['q'] ?? null;

        foreach ($pager['list'] as $key => $item) {
            $category = $this->di['db']->getExistingModelById('SupportKbArticleCategory', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->kbCategoryToApiArray($category, $this->getIdentity(), $q);
        }

        return $pager;
    }

    /**
     * Get knowledge base categories id, title pairs.
     *
     * @return array
     */
    public function kb_category_get_pairs(array $data): array
    {
        return $this->getService()->kbCategoryGetPairs();
    }

    /**
     * Get knowledge base category by ID or SLUG.
     *
     * @return array
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
            $model = $this->getService()->kbFindCategoryById($id);
        } else {
            $model = $this->getService()->kbFindCategoryBySlug($slug);
        }

        if (!$model instanceof \Model_SupportKbArticleCategory) {
            throw new \FOSSBilling\InformationException('Knowledge Base category not found');
        }

        return $this->getService()->kbCategoryToApiArray($model, $this->getIdentity());
    }
}
