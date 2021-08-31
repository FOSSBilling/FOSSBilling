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

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of knowledge base articles
     *
     * @return array
     */
    public function article_get_list($data)
    {
        $status = $this->di['array_get']($data, 'status', null);
        $search = $this->di['array_get']($data, 'search', null);
        $cat    = $this->di['array_get']($data, 'cat', null);

        $pager = $this->getService()->searchArticles($status, $search, $cat);

        foreach ($pager['list'] as $key => $item) {
            $article               = $this->di['db']->getExistingModelById('KbArticle', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->toApiArray($article);
        }

        return $pager;
    }

    /**
     * Get knowledge base article
     *
     * @param int $id - knowledge base article ID
     *
     * @return array
     */
    public function article_get($data)
    {
        $required = array(
            'id' => 'Article id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('KbArticle', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_KbArticle) {
            throw new \Box_Exception('Article not found');
        }

        return $this->getService()->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Create new knowledge base article
     *
     * @param int $kb_article_category_id - knowledge base category ID
     * @param string $title - knowledge base article title
     *
     * @optional string $status - knowledge base article status
     * @optional string $content - knowledge base article content
     *
     * @return array
     */
    public function article_create($data)
    {
        $required = array(
            'kb_article_category_id' => 'Article category id not passed',
            'title'                  => 'Article title not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $articleCategoryId = $data['kb_article_category_id'];
        $title             = $data['title'];
        $status            = isset($data['status']) ? $data['status'] : \Model_KbArticle::DRAFT;
        $content           = $this->di['array_get']($data, 'content', NULL);

        return $this->getService()->createArticle($articleCategoryId, $title, $status, $content);
    }

    /**
     * Update knowledge base article
     *
     * @param int $id - knowledge base article ID
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
    public function article_update($data)
    {
        $required = array(
            'id' => 'Article ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $articleCategoryId = $this->di['array_get']($data, 'kb_article_category_id', null);
        $title             = $this->di['array_get']($data, 'title', null);
        $slug              = $this->di['array_get']($data, 'slug', null);
        $status            = $this->di['array_get']($data, 'status', null);
        $content           = $this->di['array_get']($data, 'content', null);
        $views             = $this->di['array_get']($data, 'views', null);


        return $this->getService()->updateArticle($data['id'], $articleCategoryId, $title, $slug, $status, $content, $views);
    }

    /**
     * Delete knowledge base article
     *
     * @param int $id - knowledge base article ID
     *
     * @return bool
     */
    public function article_delete($data)
    {
        $required = array(
            'id' => 'Article ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('KbArticle', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_KbArticle) {
            throw new \Box_Exception('Article not found');
        }

        $this->getService()->rm($model);

        return true;
    }

    /**
     * Get paginated list of knowledge base categories
     *
     * @return array
     */
    public function category_get_list($data)
    {
        list($sql, $bindings) = $this->getService()->categoryGetSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getAdvancedResultSet($sql, $bindings, $per_page);

        foreach ($pager['list'] as $key => $item) {
            $category              = $this->di['db']->getExistingModelById('KbArticleCategory', $item['id'], 'KB Article not found');
            $pager['list'][$key] = $this->getService()->categoryToApiArray($category, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Get knowledge base category
     *
     * @param int $id - knowledge base category ID
     *
     * @return array
     */
    public function category_get($data)
    {
        $required = array(
            'id' => 'Category ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('KbArticleCategory', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_KbArticleCategory) {
            throw new \Box_Exception('Article Category not found');
        }

        return $this->getService()->categoryToApiArray($model);
    }

    /**
     * Create new knowledge base category
     *
     * @param string $title - knowledge base category title
     *
     * @optional string $description - knowledge base category description
     *
     * @return array
     */
    public function category_create($data)
    {
        $required = array(
            'title' => 'Category title not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $title       = $data['title'];
        $description = $this->di['array_get']($data, 'description', NULL);

        return $this->getService()->createCategory($title, $description);
    }

    /**
     * Update knowledge base category
     *
     * @param int $id - knowledge base category ID
     *
     * @optional string $title - knowledge base category title
     * @optional string $slug  - knowledge base category slug
     * @optional string $description - knowledge base category description
     *
     * @return array
     */
    public function category_update($data)
    {
        $required = array(
            'id' => 'Category ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('KbArticleCategory', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_KbArticleCategory) {
            throw new \Box_Exception('Article Category not found');
        }

        $title       = $this->di['array_get']($data, 'title', NULL);
        $slug        = $this->di['array_get']($data, 'slug', NULL);
        $description = $this->di['array_get']($data, 'description', NULL);

        return $this->getService()->updateCategory($model, $title, $slug, $description);
    }

    /**
     * Delete knowledge base category
     *
     * @param int $id - knowledge base category ID
     *
     * @return bool
     */
    public function category_delete($data)
    {
        $required = array(
            'id' => 'Category ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->findOne('KbArticleCategory', 'id = ?', array($data['id']));

        if (!$model instanceof \Model_KbArticleCategory) {
            throw new \Box_Exception('Category not found');
        }

        return $this->getService()->categoryRm($model);
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
}