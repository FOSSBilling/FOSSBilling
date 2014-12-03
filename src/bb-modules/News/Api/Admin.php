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
        $per_page = isset($data['per_page']) ? $data['per_page'] : $this->di['pager']->getPer_page();
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $post               = $this->di['db']->getExistingModelById('Post', $item['id'], 'Post not found');
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
        if(!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('ID or slug is missing');
        }

        $id = isset($data['id']) ? $data['id'] : NULL;
        $service = $this->getService();
        $model = $this->di['db']->getExistingModelById('Post', $id, 'News item not found');
        return $service->toApiArray($model, 'admin');
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
        if(!isset($data['id'])) {
            throw new \Box_Exception('Post id not passed');
        }

        $model = $this->di['db']->getExistingModelById('Post', $data['id'], 'News item not found');

        if(isset($data['content'])) {
            $model->content = $data['content'];
        }

        if(isset($data['title'])) {
            $model->title = $data['title'];
        }

        if(isset($data['slug'])) {
            $model->slug = $data['slug'];
        }
        
        if(isset($data['image'])) {
            $model->image = $data['image'];
        }
        
        if(isset($data['section'])) {
            $model->section = $data['section'];
        }
        
        if(isset($data['publish_at'])) {
            $model->publish_at = $data['publish_at'];
        }
        
        if(isset($data['published_at'])) {
            $model->published_at = $data['published_at'];
        }
        
        if(isset($data['expires_at'])) {
            $model->publish_at = $data['expires_at'];
        }

        if(isset($data['status'])) {
             $model->status = $data['status'];
        }
        
        if(isset($data['created_at'])) {
             $model->created_at = date('c', strtotime($data['created_at']));
        }
        
        if(isset($data['updated_at'])) {
             $model->updated_at = date('c', strtotime($data['updated_at']));
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
        if(!isset($data['title'])) {
            throw new \Box_Exception('Post title not passed');
        }

        $model = $this->di['db']->dispense('Post');
        $model->admin_id = $this->getIdentity()->id;
        $model->title = $data['title'];
        $model->slug = $this->di['tools']->slug($data['title']);
        $model->status = isset($data['status']) ? $data['status'] : NULL;
        $model->content = isset($data['content']) ? $data['content'] : NULL;
        $model->created_at = date('c');
        $model->updated_at = date('c');
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
        if(!isset($data['id'])) {
            throw new \Box_Exception('Post id not passed');
        }

        $model = $this->di['db']->getExistingModelById('Post', $data['id'], 'News item not found');
        $id = $model->id;
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