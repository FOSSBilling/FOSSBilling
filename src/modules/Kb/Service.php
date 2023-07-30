<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Kb;

class Service
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function searchArticles($status = null, $search = null, $cat = null, $per_page = 100, $page = null)
    {
        $filter = [];

        $sql = '
            SELECT *
            FROM kb_article
            WHERE 1
        ';

        if ($cat) {
            $sql .= ' AND kb_article_category_id = :cid';
            $filter[':cid'] = $cat;
        }

        if ($status) {
            $sql .= ' AND status = :status';
            $filter[':status'] = $status;
        }

        if ($search) {
            $sql .= ' AND title LIKE :q OR content LIKE :q';
            $filter[':q'] = "%$search%";
        }

        $sql .= ' ORDER BY kb_article_category_id DESC, views DESC';

        return $this->di['pager']->getSimpleResultSet($sql, $filter, $per_page, $page);
    }

    public function findActiveArticleById($id)
    {
        $bindings = [
            ':id' => $id,
            ':status' => \Model_KbArticle::ACTIVE,
        ];

        return $this->di['db']->findOne('KbArticle', 'id = :id AND status=:status', $bindings);
    }

    public function findActiveArticleBySlug($slug)
    {
        $bindings = [
            ':slug' => $slug,
            ':status' => \Model_KbArticle::ACTIVE,
        ];

        return $this->di['db']->findOne('KbArticle', 'slug = :slug AND status=:status', $bindings);
    }

    public function findActive()
    {
        return $this->di['db']->find('KbArticle', 'status=:status', [':status' => \Model_KbArticle::ACTIVE]);
    }

    public function hitView(\Model_KbArticle $model)
    {
        ++$model->views;
        $this->di['db']->store($model);
    }

    public function rm(\Model_KbArticle $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted knowledge base article #%s', $id);
    }

    public function toApiArray(\Model_KbArticle $model, $deep = false, $identity = null)
    {
        $data = [
            'id' => $model->id,
            'slug' => $model->slug,
            'title' => $model->title,
            'views' => $model->views,
            'created_at' => $model->created_at,
            'status' => $model->status,
            'updated_at' => $model->updated_at,
        ];

        $cat = $this->di['db']->getExistingModelById('KbArticleCategory', $model->kb_article_category_id, 'Knowledge base category not found');
        $data['category'] = [
            'id' => $cat->id,
            'slug' => $cat->slug,
            'title' => $cat->title,
        ];

        if ($deep) {
            $data['content'] = $model->content;
        }

        if ($identity instanceof \Model_Admin) {
            $data['status'] = $model->status;
            $data['kb_article_category_id'] = $model->kb_article_category_id;
        }

        return $data;
    }

    public function createArticle($articleCategoryId, $title, $status = null, $content = null)
    {
        if (!isset($status)) {
            $status = \Model_KbArticle::DRAFT;
        }

        $model = $this->di['db']->dispense('KbArticle');
        $model->kb_article_category_id = $articleCategoryId;
        $model->title = $title;
        $model->slug = $this->di['tools']->slug($title);
        $model->status = $status;
        $model->content = $content;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');
        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new knowledge base article #%s', $id);

        return $id;
    }

    public function updateArticle($id, $articleCategoryId = null, $title = null, $slug = null, $status = null, $content = null, $views = null)
    {
        $model = $this->di['db']->findOne('KbArticle', 'id = ?', [$id]);

        if (!$model instanceof \Model_KbArticle) {
            throw new \Box_Exception('Article not found');
        }

        if (isset($articleCategoryId)) {
            $model->kb_article_category_id = $articleCategoryId;
        }

        if (isset($title)) {
            $model->title = $title;
        }

        if (isset($slug)) {
            $model->slug = $slug;
        }

        if (isset($status)) {
            $model->status = $status;
        }

        if (isset($content)) {
            $model->content = $content;
        }

        if (isset($views)) {
            $model->views = $views;
        }
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated knowledge base article #%s', $id);

        return true;
    }

    public function categoryGetSearchQuery($data)
    {
        $sql = '
        SELECT kac.*
        FROM kb_article_category kac
        LEFT JOIN kb_article ka ON kac.id  = ka.kb_article_category_id';

        $article_status = $data['article_status'] ?? null;
        $query = $data['q'] ?? null;

        $where = [];
        $bindings = [];
        if ($article_status) {
            $where[] = 'ka.status = :status';

            $bindings[':status'] = $article_status;
        }

        if ($query) {
            $where[] = '(ka.title LIKE :title OR ka.content LIKE :content)';

            $bindings[':title'] = "%$query%";
            $bindings[':content'] = "%$query%";
        }

        if (!empty($where)) {
            $sql = $sql . ' WHERE ' . implode(' AND ', $where);
        }

        $sql = $sql . ' GROUP BY kac.id ORDER BY kac.id DESC';

        return [$sql, $bindings];
    }

    public function categoryFindAll()
    {
        $sql = 'SELECT kac.*, a.*
                FROM kb_article_category kac
                LEFT JOIN kb_article ka
                ON kac.id  = ka.kb_article_category_id
                ';

        return $this->di['db']->getAll($sql);
    }

    public function categoryGetPairs()
    {
        $sql = 'SELECT id, title FROM kb_article_category';
        $pairs = $this->di['db']->getAssoc($sql);

        return $pairs;
    }

    public function categoryRm(\Model_KbArticleCategory $model)
    {
        $bindings = [
            ':kb_article_category_id' => $model->id,
        ];

        $articlesCount = $this->di['db']->getCell('SELECT count(*) as cnt FROM kb_article WHERE kb_article_category_id = :kb_article_category_id', $bindings);

        if ($articlesCount > 0) {
            throw new \Box_Exception('Can not remove category which has articles');
        }

        $id = $model->id;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Deleted knowledge base category #%s', $id);

        return true;
    }

    public function categoryToApiArray(\Model_KbArticleCategory $model, $identity = null, $query = null)
    {
        $data = $this->di['db']->toArray($model);

        $sql = 'kb_article_category_id = :category_id';
        $bindings = [
            ':category_id' => $model->id,
        ];

        if (!$identity instanceof \Model_Admin) {
            $sql .= " AND status = 'active'";
        }

        if ($query) {
            $sql .= 'AND (title LIKE :title OR content LIKE :content)';
            $query = "%$query%";
            $bindings[':content'] = $query;
            $bindings[':title'] = $query;
        }

        $articles = $this->di['db']->find('KbArticle', $sql, $bindings);

        foreach ($articles as $article) {
            $data['articles'][] = $this->toApiArray($article, false, $identity);
        }

        return $data;
    }

    public function createCategory($title, $description = null)
    {
        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_KbArticleCategory', 2);

        $model = $this->di['db']->dispense('KbArticleCategory');
        $model->title = $title;
        $model->description = $description;
        $model->slug = $this->di['tools']->slug($title);
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new knowledge base category #%s', $id);

        return $id;
    }

    public function updateCategory(\Model_KbArticleCategory $model, $title = null, $slug = null, $description = null)
    {
        if (isset($title)) {
            $model->title = $title;
        }

        if (isset($slug)) {
            $model->slug = $slug;
        }

        if (isset($description)) {
            $model->description = $description;
        }

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated knowledge base category #%s', $model->id);

        return true;
    }

    public function findCategoryById($id)
    {
        return $this->di['db']->getExistingModelById('KbArticleCategory', $id, 'Knowledge base category not found');
    }

    public function findCategoryBySlug($slug)
    {
        $bindings = [
            ':slug' => $slug,
        ];

        return $this->di['db']->findOne('KbArticleCategory', 'slug = :slug', $bindings);
    }
}
