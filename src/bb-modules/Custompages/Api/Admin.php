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

namespace Box\Mod\Custompages\Api;

class Admin extends \Api_Abstract
{
    /**
     * Get paginated list of custom pages
     *
     * @return array
     */
    public function get_list($data)
    {
        $search = $this->di['array_get']($data, 'search', null);
        $pager = $this->getService()->searchPages($search);

        foreach ($pager['list'] as $key => $item) {
            $pager['list'][$key] = $item;
        }

        return $pager;
    }

    /**
     * Delete custom page
     *
     * @param int $id - custom page ID
     *
     * @return bool
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'Custom Page ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $this->getService()->deletePage($data['id']);
        return true;
    }

    /**
     * Delete custom pages
     *
     * @param array $ids - custom page IDs
     *
     * @return bool
     */
    public function batch_delete($data)
    {
        $required = array(
            'ids' => 'Custom Page IDs not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $this->getService()->deletePage($data['ids']);
        return true;
    }

    /**
     * Create new custom page
     *
     * @param string $title - custom page title
     * @param string $content - custom page content
     *
     * @optional string $description - custom page meta description
     * @optional string $keywords - custom page meta keywords
     *
     * @return array
     */
    public function create($data)
    {
        $required = array(
            'title' => 'Custom page title not passed',
            'content' => 'Custom page content not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $title       = $data['title'];

        $content       = $data['content'];
        $description = $this->di['array_get']($data, 'description', NULL);
        $keywords = $this->di['array_get']($data, 'keywords', NULL);

        return $this->getService()->createPage($title, $description, $keywords, $content);
    }

    /**
     * Update custom page
     *
     * @param string $id - custom page id
     * @param string $slug - custom page slug
     * @param string $content - custom page content
     *
     * @optional string $description - custom page meta description
     * @optional string $keywords - custom page meta keywords
     *
     * @return array
     */
    public function update($data)
    {
        $required = array(
            'id' => 'Custom page id not passed',
            'title' => 'Custom page title not passed',
            'slug' => 'Custom page slug not passed',
            'content' => 'Custom page content not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $id = $data['id'];
        $title = $data['title'];
        $content = $data['content'];
        $slug = $data['slug'];
        $description = $this->di['array_get']($data, 'description', NULL);
        $keywords = $this->di['array_get']($data, 'keywords', NULL);

        return $this->getService()->updatePage($id, $title, $description, $keywords, $content, $slug);
    }

    /**
     * Get custom page by id
     *
     * @param string $id - custom page id
     *
     * @return array
     */
    public function get_page($data)
    {
        return $this->getService()->getPage($data['page_id']);
    }
}