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
    public function ticket_create($data)
    {
        $required = [
            'name' => 'Please enter your name',
            'email' => 'Please enter your email',
            'subject' => 'Please enter your subject',
            'message' => 'Please enter your message',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (strlen($data['message']) < 4) {
            throw new \FOSSBilling\InformationException('Please enter your message');
        }

        return $this->getService()->ticketCreateForGuest($data);
    }

    /**
     * Get public ticket.
     *
     * @return array - ticket details
     */
    public function ticket_get($data)
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
    public function ticket_close($data)
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
    public function ticket_reply($data)
    {
        $required = [
            'hash' => 'Public ticket hash required',
            'message' => 'Message is required and cannot be blank',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $publicTicket = $this->getService()->publicFindOneByHash($data['hash']);

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
    public function kb_enabled()
    {
        return $this->getService()->kbEnabled();
    }

    /**
     * Get paginated list of knowledge base articles.
     * Returns only active articles.
     *
     * @return array
     */
    public function kb_article_get_list($data)
    {
        $data['status'] = 'active';

        $status = $data['status'] ?? null;
        $search = $data['search'] ?? null;
        $cat = $data['kb_article_category_id'] ?? null;
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
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
    public function kb_article_get($data)
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
    public function kb_category_get_list($data)
    {
        $data['article_status'] = \Model_SupportKbArticle::ACTIVE;
        [$query, $bindings] = $this->getService()->kbCategoryGetSearchQuery($data);

        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getAdvancedResultSet($query, $bindings, $per_page);

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
    public function kb_category_get_pairs($data)
    {
        return $this->getService()->kbCategoryGetPairs();
    }

    /**
     * Get knowledge base category by ID or SLUG.
     *
     * @return array
     */
    public function kb_category_get($data)
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
            throw new \FOSSBilling\InformationException('Knowledge base category not found');
        }

        return $this->getService()->kbCategoryToApiArray($model, $this->getIdentity());
    }
}
