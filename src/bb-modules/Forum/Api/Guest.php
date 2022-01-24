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

/**
 * Forum management
 */

namespace Box\Mod\Forum\Api;

class Guest extends \Api_Abstract
{
    /**
     * Get paginated list of forums
     *
     * @return array
     */
    public function get_list($data)
    {
        $table = $this->di['table']('Forum');
        list ($sql, $params) = $table->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $forum               = $this->di['db']->getExistingModelById('Forum', $item['id'], 'Forum not found');
            $pager['list'][$key] = $table->toApiArray($forum);
        }

        return $pager;
    }

    /**
     * Get forums list grouped by category name
     *
     * @return array
     */
    public function get_categories($data)
    {
        $table = $this->di['table']('Forum');

        $list = $this->di['db']->find('Forum', 'ORDER BY priority ASC, category ASC');

        $result = array();
        foreach ($list as $f) {
            $result[$f->category][] = $table->toApiArray($f);
        }

        return $result;
    }

    /**
     * Get forum details
     *
     * @param int $id - forum id
     *
     * @return array
     * @throws Box_Exception
     */
    public function get($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('ID or slug is missing');
        }

        $id   = $this->di['array_get']($data, 'id', NULL);
        $slug = $this->di['array_get']($data, 'slug', NULL);

        $table = $this->di['table']('Forum');

        $model = FALSE;
        if ($id) {
            $model = $table->findOneActiveById($id);
        } else {
            $model = $table->findOneActiveBySlug($slug);
        }

        if (!$model instanceof \Model_Forum) {
            throw new \Box_Exception('Forum not found');
        }

        return $table->toApiArray($model);
    }

    /**
     * Get paginated list of topics
     *
     * @return array
     */
    public function get_topic_list($data)
    {
        $table = $this->di['table']('ForumTopic');
        list($sql, $params) = $table->getSearchQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as $key => $item){
            $forum = $this->di['db']->getExistingModelById('ForumTopic', $item['id'], 'Forum topic not found');
            $pager['list'][$key] = $table->toApiArray($forum);
        }

        return $pager;
    }

    /**
     * Get topic details
     *
     * @param int $id - topic id
     * @return array
     * @throws Box_Exception
     */
    public function get_topic($data)
    {
        if (!isset($data['id']) && !isset($data['slug'])) {
            throw new \Box_Exception('ID or slug is missing');
        }

        $id   = $this->di['array_get']($data, 'id', NULL);
        $slug = $this->di['array_get']($data, 'slug', NULL);

        $table = $this->di['table']('ForumTopic');

        $model = FALSE;
        if ($id) {
            $model = $this->di['db']->getExistingModelById('ForumTopic', $id, 'Forum topic not found');
        } else {
            $model = $this->di['db']->findOne('ForumTopic', 'slug = :slug', array(':slug' => $slug));
        }
        if (!$model instanceof \Model_ForumTopic) {
            throw new \Box_Exception('Forum Topic not found');
        }
        $table->hitView($model);

        return $table->toApiArray($model);
    }

    /**
     * Get topic messages list
     *
     * @param int $forum_topic_id - topic id
     * @return array
     * @throws Box_Exception
     */
    public function get_topic_message_list($data)
    {
        if (!isset($data['forum_topic_id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }
        $table = $this->di['table']('ForumTopicMessage');
        list($sql, $params) = $table->getSearchQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as $key => $item){
            $forum = $this->di['db']->getExistingModelById('ForumTopicMessage', $item['id'], 'Forum topic message not found');
            $pager['list'][$key] = $table->toApiArray($forum);
        }

        return $pager;
    }

    /**
     * Search topic messages
     *
     * @param string $q - query string
     * @return array - paginated list of results
     * @throws Box_Exception
     */
    public function search($data)
    {
        $required = array(
            'q' => 'Enter some keywords for search',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if (strlen($data['q']) < 3) {
            throw new \Box_Exception('Search keyword must be longer than 3 characters');
        }

        $table = $this->di['table']('ForumTopicMessage');
        list($sql, $params) = $table->getSearchQuery($data);
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    public function members_list($data)
    {
        list($sql, $params) = $this->getService()->getMembersListQuery($data);

        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        return $this->di['pager']->getAdvancedResultSet($sql, $params, $per_page);
    }
}