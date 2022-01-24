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
 * Knowledge base API
 */

namespace Box\Mod\Kb\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get paginated list of knowledge base articles.
     * Returns only active articles.
     *
     * @return array
     */
    public function article_get_list($data)
    {
        $data['status'] = 'active';

        $status = $this->di['array_get']($data, 'status', NULL);
        $search = $this->di['array_get']($data, 'search', NULL);
        $cat    = $this->di['array_get']($data, 'kb_article_category_id', NULL);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $page = $this->di['array_get']($data, 'page');

        $pager = $this->getService()->searchArticles($status, $search, $cat, $per_page, $page);

        foreach ($pager['list'] as $key => $item) {
            $article              = $this->di['db']->getExistingModelById('KbArticle', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->toApiArray($article);
        }

        return $pager;
    }

    /**
     * Get active knowledge base article
     *
     * @param int $id - knowledge base article ID. Required only if SLUG is not passed.
     * @param string $slug - knowledge base article slug. Required only if ID is not passed.
     *
     * @return array
     */
    public function article_get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('ID or slug is missing');
        }

        $id   = $this->di['array_get']($data, 'id', NULL);
        $slug = $this->di['array_get']($data, 'slug', NULL);

        $model = FALSE;
        if ($id) {
            $model = $this->getService()->findActiveArticleById($id);
        } else {
            $model = $this->getService()->findActiveArticleBySlug($slug);
        }

        if (!$model instanceof \Model_KbArticle) {
            throw new \Box_Exception('Article item not found');
        }
        $this->getService()->hitView($model);

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Get paginated list of knowledge base categories
     * @return array
     */
    public function category_get_list($data)
    {
        $data['article_status'] = \Model_KbArticle::ACTIVE;
        list($query, $bindings) = $this->getService()->categoryGetSearchQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getAdvancedResultSet($query, $bindings, $per_page);

        $q = $this->di['array_get']($data, 'q', null);

        foreach ($pager['list'] as $key => $item) {
            $category               = $this->di['db']->getExistingModelById('KbArticleCategory', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->categoryToApiArray($category, $this->getIdentity(), $q);
        }
        return $pager;
    }

    /**
     * Get knowledge base categories id, title pairs
     *
     * @return array
     */
    public function category_get_pairs($data)
    {
        return $this->getService()->categoryGetPairs();
    }

    /**
     * Get knowledge base category by ID or SLUG
     *
     * @param int $id - knowledge base category ID. Required only if SLUG is not passed.
     * @param string $slug - knowledge base category slug. Required only if ID is not passed.
     *
     * @return array
     */
    public function category_get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('Category ID or slug is missing');
        }

        $id   = $this->di['array_get']($data, 'id', NULL);
        $slug = $this->di['array_get']($data, 'slug', NULL);


        $model = FALSE;
        if ($id) {
            $model = $this->getService()->findCategoryById($id);
        } else {
            $model = $this->getService()->findCategoryBySlug($slug);
        }

        if (!$model instanceof \Model_KbArticleCategory) {
            throw new \Box_Exception('Knowledge base category not found');
        }

        return $this->getService()->categoryToApiArray($model, $this->getIdentity());
    }
}