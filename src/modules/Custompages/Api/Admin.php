<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Custompages\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of custom pages.
     *
     * @return array
     */
    public function get_list($data)
    {
        $search = $data['search'] ?? null;
        $pager = $this->getService()->searchPages($search);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $item;
        }

        return $pager;
    }

    /**
     * Delete custom page.
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = [
            'id' => 'Custom Page ID not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $this->getService()->deletePage($data['id']);

        return true;
    }

    /**
     * Delete custom pages.
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = [
            'ids' => 'Custom Page IDs not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $this->getService()->deletePage($data['ids']);

        return true;
    }

    /**
     * Create new custom page.
     *
     * @optional string $description - custom page meta description
     * @optional string $keywords - custom page meta keywords
     *
     * @return array
     */
    public function create($data)
    {
        $required = [
            'title' => 'Custom page title not passed',
            'content' => 'Custom page content not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $title = $data['title'];

        $content = $data['content'];
        $description = $data['description'] ?? null;
        $keywords = $data['keywords'] ?? null;

        return $this->getService()->createPage($title, $description, $keywords, $content);
    }

    /**
     * Update custom page.
     *
     * @optional string $description - custom page meta description
     * @optional string $keywords - custom page meta keywords
     *
     * @return array
     */
    public function update($data)
    {
        $required = [
            'id' => 'Custom page id not passed',
            'title' => 'Custom page title not passed',
            'slug' => 'Custom page slug not passed',
            'content' => 'Custom page content not passed',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $id = $data['id'];
        $title = $data['title'];
        $content = $data['content'];
        $slug = $data['slug'];
        $description = $data['description'] ?? null;
        $keywords = $data['keywords'] ?? null;

        return $this->getService()->updatePage($id, $title, $description, $keywords, $content, $slug);
    }

    /**
     * Get custom page by id.
     *
     * @return array
     */
    public function get_page($data)
    {
        return $this->getService()->getPage($data['page_id']);
    }
}
