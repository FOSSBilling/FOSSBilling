<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages;

use FOSSBilling\PaginationOptions;

class Service
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getModulePermissions(): array
    {
        return [
            'view' => [
                'type' => 'bool',
                'display_name' => __trans('View custom pages'),
                'description' => __trans('Allows the staff member to view custom pages.'),
            ],
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage custom pages'),
                'description' => __trans('Allows the staff member to create, update, and delete custom pages.'),
            ],
        ];
    }

    public function install(): bool
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

    public function searchPages(array $data = [])
    {
        $filter = [];
        $search = $data['search'] ?? null;
        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        $sql = 'SELECT * FROM custom_pages WHERE 1';
        if ($id !== null && $id !== '') {
            $sql .= ' AND id = :id';
            $filter[':id'] = (int) $id;
        }

        if ($slug !== null && $slug !== '') {
            $sql .= ' AND slug LIKE :slug';
            $filter[':slug'] = '%' . $slug . '%';
        }

        if ($search) {
            $sql .= ' AND (title LIKE :q OR slug LIKE :q OR description LIKE :q OR keywords LIKE :q OR content LIKE :q)';
            $filter[':q'] = "%$search%";
        }
        $sql .= ' ORDER BY id DESC';

        return $this->di['pager']->getPaginatedResultSet($sql, $filter, PaginationOptions::fromArray($data));
    }

    public function deletePage($id): void
    {
        if (is_array($id)) {
            foreach ($id as $i => $x) {
                $id[$i] = (int) $x;
            }
            $placeholders = implode(', ', array_fill(0, count($id), '?'));
            $this->di['dbal']->executeStatement("DELETE FROM custom_pages WHERE id IN ($placeholders)", $id);
        } else {
            $this->di['dbal']->executeStatement('DELETE FROM custom_pages WHERE id = ?', [$id]);
        }
    }

    public function getPage($id, $type = 'id')
    {
        $allowedColumns = ['id', 'slug'];
        if (!in_array($type, $allowedColumns, true)) {
            throw new \FOSSBilling\Exception('Invalid column type: :type', [':type' => $type]);
        }

        return $this->di['dbal']->executeQuery(
            "SELECT * FROM custom_pages WHERE $type = ?",
            [$id]
        )->fetchAssociative();
    }

    public function createPage($title, $description, $keywords, $content)
    {
        $slug = $this->di['tools']->slug($title);
        $i = 0;
        $exists = $this->di['dbal']->executeQuery(
            'SELECT id FROM custom_pages WHERE slug = ?',
            [$slug]
        )->fetchOne();
        while ($exists) {
            $slug = $this->di['tools']->slug($title) . '-' . ++$i;
            $exists = $this->di['dbal']->executeQuery(
                'SELECT id FROM custom_pages WHERE slug = ?',
                [$slug]
            )->fetchOne();
        }
        $this->di['dbal']->executeStatement(
            'INSERT INTO custom_pages (title, description, keywords, content, slug) VALUES (?, ?, ?, ?, ?)',
            [$title, $description, $keywords, $content, $slug]
        );
        $id = $this->di['dbal']->lastInsertId();
        $this->di['logger']->info('Created new custom page #%s', $id);

        return $id;
    }

    public function updatePage($id, $title, $description, $keywords, $content, $slug)
    {
        $slug = $this->di['tools']->slug($slug);
        $exists = $this->di['dbal']->executeQuery(
            'SELECT id FROM custom_pages WHERE slug = ? AND id <> ?',
            [$slug, $id]
        )->fetchOne();
        if ($exists) {
            throw new \FOSSBilling\Exception('You need to set unique slug.', null, 9999);
        }
        $this->di['dbal']->executeStatement(
            'UPDATE custom_pages SET title = ?, description = ?, keywords = ?, content = ?, slug = ? WHERE id = ?',
            [$title, $description, $keywords, $content, $slug, $id]
        );
        $this->di['logger']->info('Updated custom page #%s', $id);

        return $id;
    }
}
