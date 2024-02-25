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
 * News and announcements management.
 */

namespace Box\Mod\News\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get paginated list of active news items.
     *
     * @return array
     */
    public function get_list($data)
    {
        $data['status'] = 'active';
        [$sql, $params] = $this->getService()->getSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getPer_page();
        $page = $data['page'] ?? null;
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page, $page);
        foreach ($pager['list'] as $key => $item) {
            $post = $this->di['db']->getExistingModelById('Post', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toApiArray($post);
        }

        return $pager;
    }

    /**
     * Get news item by ID or SLUG.
     *
     * @return array
     */
    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \FOSSBilling\Exception('ID or slug is missing');
        }

        $id = $data['id'] ?? null;
        $slug = $data['slug'] ?? null;

        if ($id) {
            $model = $this->getService()->findOneActiveById($id);
        } else {
            $model = $this->getService()->findOneActiveBySlug($slug);
        }

        if (!$model || $model->status !== 'active') {
            throw new \FOSSBilling\Exception('News item not found');
        }

        return $this->getService()->toApiArray($model);
    }
}
