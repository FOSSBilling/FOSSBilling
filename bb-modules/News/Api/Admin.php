<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * News management
 */

namespace Box\Mod\News\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of active news items
     *
     * @return array
     */
    public function get_list($data)
    {
        $service = $this->getService();
        list ($sql, $params) = $service->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager    = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $post                = $this->di['db']->getExistingModelById('Post', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toApiArray($post, 'admin');
        }

        return $pager;
    }

    /**
     * Get news item by ID
     *
     * @param int $id - news item ID
     *
     * @return array
     */
    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('ID or slug is missing');
        }

        $id   = $this->di['array_get']($data, 'id');
        $slug = $this->di['array_get']($data, 'slug');

        $model = null;
        if ($id) {
            $model = $this->di['db']->load('Post', $id);
        } else {
            if (!empty($slug)) {
                $model = $this->di['db']->findOne('Post', 'slug = :slug', array('slug' => $slug));
            }
        }

        if (!$model instanceof \Model_Post) {
            throw new \Box_Exception('News item not found');
        }

        return $this->getService()->toApiArray($model, 'admin');
    }

    /**
     * Update news item.
     *
     * @param int $id - news item ID
     *
     * @optional string $title - news item title
     * @optional string $slug - news item slug
     * @optional string $content - news item content
     * @optional string $status - news item status
     *
     * @return bool
     */
    public function update($data)
    {
        $required = array(
            'id' => 'Post id not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Post', $data['id'], 'News item not found');

        $model->content = $this->di['array_get']($data, 'content', $model->content);
        $model->title   = $this->di['array_get']($data, 'title', $model->title);
        $model->slug    = $this->di['array_get']($data, 'slug', $model->slug);
        $model->image   = $this->di['array_get']($data, 'image', $model->image);
        $model->section = $this->di['array_get']($data, 'section', $model->section);
        $model->status  = $this->di['array_get']($data, 'status', $model->status);

        $publish_at = $this->di['array_get']($data, 'publish_at', 0);
        if ($publish_at) {
            $model->publish_at = date('Y-m-d H:i:s', strtotime($publish_at));
        }

        $published_at = $this->di['array_get']($data, 'published_at', 0);
        if ($published_at) {
            $model->published_at = date('Y-m-d H:i:s', strtotime($published_at));
        }

        $expires_at = $this->di['array_get']($data, 'expires_at', 0);
        if ($expires_at) {
            $model->expires_at = date('Y-m-d H:i:s', strtotime($expires_at));
        }

        $created_at = $this->di['array_get']($data, 'created_at', 0);
        if ($created_at) {
            $model->created_at = date('Y-m-d H:i:s', strtotime($created_at));
        }

        $updated_at = $this->di['array_get']($data, 'updated_at', 0);
        if ($created_at) {
            $model->updated_at = date('Y-m-d H:i:s', strtotime($updated_at));
        }
        $model->admin_id = $this->getIdentity()->id;
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated news item #%s', $model->id);

        return TRUE;
    }

    /**
     * Creat new news item.
     *
     * @param string $title - news item title
     *
     * @optional string $content - news item content
     * @optional string $status - news item status
     *
     * @return bool
     */
    public function create($data)
    {
        $required = array(
            'title' => 'Post title not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model             = $this->di['db']->dispense('Post');
        $model->admin_id   = $this->getIdentity()->id;
        $model->title      = $data['title'];
        $model->slug       = $this->di['tools']->slug($data['title']);
        $model->status     = $this->di['array_get']($data, 'status', NULL);
        $model->content    = $this->di['array_get']($data, 'content', NULL);
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Created news item #%s', $model->id);

        return $model->id;
    }

    /**
     * Delete news item by ID
     *
     * @param int $id - news item ID
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'Post ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Post', $data['id'], 'News item not found');
        $id    = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Removed news item #%s', $id);

        return true;
    }

    /**
     * Deletes news items with given IDs
     *
     * @param array $ids - IDs for deletion
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        foreach ($data['ids'] as $id) {
            $this->delete(array('id' => $id));
        }

        return true;
    }
}