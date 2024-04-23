<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\News;

class Service
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function findOneActiveById($id)
    {
        return $this->di['db']->findOne('Post', 'id = :id AND status = "active"', ['id' => $id]);
    }

    public function findOneActiveBySlug($slug)
    {
        return $this->di['db']->findOne('Post', 'slug = :slug AND status = "active"', ['slug' => $slug]);
    }

    /**
     * Generate a placeholder meta description from given string.
     *
     * @param string $content - string to generate description from
     *
     * @return string
     */
    public function generateDescriptionFromContent($content)
    {
        $desc = mb_convert_encoding($content, 'UTF-8');
        $desc = strip_tags($desc);
        $desc = str_replace(["\n", "\r", "\t"], ' ', $desc);

        return substr($desc, 0, 125);
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
            FROM post
            WHERE 1 ';

        $params = [];

        $search = $data['search'] ?? null;
        $status = $data['status'] ?? null;

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params[':status'] = $status;
        }

        if ($search !== null) {
            $sql .= ' AND (title LIKE :search OR content LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY created_at DESC';

        return [$sql, $params];
    }

    public function toApiArray($row, $role = 'guest', $deep = true): array
    {
        $admin = $this->di['db']->getRow('SELECT name, email FROM admin WHERE id=:id', ['id' => $row->admin_id]);

        $pos = strpos($row->content, '<!--more-->');
        $excerpt = ($pos) ? substr($row->content, 0, $pos) : null;

        // Remove <!--more--> from post content
        $content = str_replace('<!--more-->', '', $row->content);

        $data = [
            'id' => $row->id,
            'title' => $row->title,
            'description' => $row->description,
            'content' => $content,
            'slug' => $row->slug,
            'image' => $row->image,
            'section' => $row->section,
            'publish_at' => $row->publish_at,
            'published_at' => $row->published_at,
            'expires_at' => $row->expires_at,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'author' => [
                'name' => $admin['name'],
                'email' => $admin['email'],
            ],
            'excerpt' => $excerpt,
        ];

        if ($role == 'admin') {
            $data['status'] = $row->status;
        }

        return $data;
    }
}
