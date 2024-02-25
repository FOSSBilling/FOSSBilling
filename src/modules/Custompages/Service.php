<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages;

class Service
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function install()
    {
        $sql = '
            CREATE TABLE IF NOT EXISTS `custom_pages` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `title` varchar(255) NOT NULL,
                `description` varchar(555) NOT NULL,
                `keywords` varchar(555) NOT NULL,
                `content` text NOT NULL,
                `slug` varchar(255) NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8';
        $this->di['db']->exec($sql);

        return true;
    }

    public function searchPages($search = null, $per_page = 100, $page = null)
    {
        $filter = [];
        $sql = 'SELECT * FROM custom_pages WHERE 1';
        if ($search) {
            $sql .= ' AND title LIKE :q OR content LIKE :q';
            $filter[':q'] = "%$search%";
        }
        $sql .= ' ORDER BY id DESC';

        return $this->di['pager']->getSimpleResultSet($sql, $filter, $per_page, $page);
    }

    public function deletePage($id)
    {
        if (is_array($id)) {
            foreach ($id as $i => $x) {
                $id[$i] = (int) $x;
            }
            $this->di['pdo']->query('DELETE from custom_pages WHERE id in (' . join(', ', $id) . ')');
        } else {
            $this->di['pdo']->prepare('DELETE from custom_pages WHERE id = ?')->execute([$id]);
        }
    }

    public function getPage($id, $type = 'id')
    {
        $q = $this->di['pdo']->prepare('SELECT * from custom_pages WHERE ' . $type . ' = ?');
        $q->execute([$id]);

        return $q->fetch();
    }

    public function createPage($title, $description, $keywords, $content)
    {
        $slug = $this->di['tools']->slug($title);
        $i = 0;
        $ex = $this->di['pdo']->prepare('SELECT id from custom_pages WHERE slug = ?');
        $ex->execute([$slug]);
        $ex = $ex->rowCount();
        while ($ex > 0) {
            $slug = $this->di['tools']->slug($title) . '-' . ++$i;
            $ex = $this->di['pdo']->prepare('SELECT id from custom_pages WHERE slug = ?');
            $ex->execute([$slug]);
            $ex = $ex->rowCount();
        }
        $this->di['pdo']->prepare('INSERT into custom_pages (title, description, keywords, content, slug) VALUES (?, ?, ?, ?, ?)')->execute([$title, $description, $keywords, $content, $slug]);
        $id = $this->di['pdo']->lastInsertId();
        $this->di['logger']->info('Created new custom page #%s', $id);

        return $id;
    }

    public function updatePage($id, $title, $description, $keywords, $content, $slug)
    {
        $slug = $this->di['tools']->slug($slug);
        $ex = $this->di['pdo']->prepare('SELECT id from custom_pages WHERE slug = ? AND id <> ?');
        $ex->execute([$slug, $id]);
        if ($ex->rowCount() > 0) {
            exit(json_encode(['result' => null, 'error' => ['message' => 'You need to set unique slug.', 'code' => 9999]]));
        }
        $this->di['pdo']->prepare('UPDATE custom_pages SET title = ?, description = ?, keywords = ?, content = ?, slug = ? WHERE id = ?')->execute([$title, $description, $keywords, $content, $slug, $id]);
        $this->di['logger']->info('Updated custom page #%s', $id);

        return $id;
    }
}
