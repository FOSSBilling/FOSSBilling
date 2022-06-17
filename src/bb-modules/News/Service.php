<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\News;

class Service
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
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
        $desc = utf8_encode($content);
        $desc = strip_tags($desc);
        $desc = str_replace(["\n", "\r", "\t"], ' ', $desc);
        $desc = substr($desc, 0, 125);

        return $desc;
    }

    public function getSearchQuery($data)
    {
        $sql = 'SELECT *
            FROM post
            WHERE 1 ';

        $params = [];

        $search = $this->di['array_get']($data, 'search', null);
        $status = $this->di['array_get']($data, 'status', null);

        if (null !== $status) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        if (null !== $search) {
            $sql .= ' AND (m.title LIKE :search OR m.content LIKE :search)';
            $params['search'] = '%'.$search.'%';
        }

        $sql .= ' ORDER BY created_at DESC';

        return [$sql, $params];
    }

    public function toApiArray($row, $role = 'guest', $deep = true)
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

        if ('admin' == $role) {
            $data['status'] = $row->status;
        }

        return $data;
    }
}
