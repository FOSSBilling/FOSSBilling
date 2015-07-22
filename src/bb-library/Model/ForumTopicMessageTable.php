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
 * Model_ForumTopicMessageTable
 *
 */
class Model_ForumTopicMessageTable implements \Box\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function getSearchQuery($data)
    {
        $query = "SELECT ftm.*
                    FROM forum_topic_message ftm
                    LEFT JOIN forum_topic ft ON ft.id = ftm.forum_topic_id
                    LEFT JOIN forum f ON f.id = ft.forum_id
                    LEFT JOIN admin a ON a.id = ftm.admin_id
                    LEFT JOIN client c ON c.id = ftm.client_id";

        $search         = (isset($data['q']) && !empty($data['q'])) ? $data['q'] : NULL;
        $search2        = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : NULL;
        $forum_topic_id = (isset($data['forum_topic_id']) && !empty($data['forum_topic_id'])) ? $data['forum_topic_id'] : NULL;
        $forum_id       = $this->di['array_get']($data, 'forum_id', NULL); 
        $client_id      = $this->di['array_get']($data, 'client_id', NULL); 

        $where = $bindings = array();

        if ($forum_topic_id) {
            $where[]                     = "ftm.forum_topic_id = :forum_topic_id";
            $bindings[':forum_topic_id'] = $forum_topic_id;
        }

        if ($forum_id) {
            $where[]               = "ft.forum_id = :forum_id";
            $bindings[':forum_id'] = $forum_id;
        }

        if ($client_id) {
            $where[]                = "ftm.client_id = :client_id";
            $bindings[':client_id'] = $client_id;
        }

        if ($search) {
            $where[]              = "ftm.message LIKE :message";
            $bindings[':message'] = "%$search%";
        }

        if ($search2) {
            $where[]               = "ftm.message LIKE :message2";
            $bindings[':message2'] = "%$search%";
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        if (isset($data['orderby'])) {
            if (in_array(strtolower($data['orderby']), array('id', 'created_at'))) {
                $sortorder = (isset($data['sortorder']) && in_array(strtolower($data['sortorder']), array('asc', 'desc'))) ? $data['sortorder'] : 'asc';
                $query .= " ORDER BY ftm." . $data['orderby'] . " " . strtoupper($sortorder);
            }
        } else {
            $query .= " ORDER BY ftm.id ASC";
        }

        return array($query, $bindings);
    }

    public function search(array $filter = null)
    {
        if (!isset($filter['q'])) {
            return array();
        }

        $searchQuery = $filter['q'];

        $query = "SELECT ftm.*, ft.id, ft.slug, f.id, f.slug
                    FROM forum_topic_message ftm
                    LEFT JOIN client c ON c.id = ftm.client_id
                    LEFT JOIN forum_topic ft ON ft.id = ftm.forum_topic_id
                    LEFT JOIN forum f ON f.id = ft.forum_id
                    WHERE ft.status IN ('active', 'locked')";

        $where = $bindings = array();

        if (isset($filter['match_date']) && $filter['match_date'] !== "0") {
            $seconds                 = (int)$filter['match_date'] * 24 * 60 * 60;
            $where[]                 = "(UNIX_TIMESTAMP() - ftm.created_at) < :created_at";
            $bindings[':created_at'] = $seconds;
        }
        $where[]              = "ftm.message LIKE :message";
        $bindings[':message'] = "%$searchQuery%";

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " ORDER BY ftm.id DESC";

        return $this->di['db']->getAssoc($query, $bindings);
    }

    public function searchForum(Model_Forum $forum, array $filter = null)
    {
        if (!isset($filter['q'])) {
            return array();
        }

        $searchQuery = $filter['q'];

        $query = "SELECT ftm.*, ft.id, ft.slug
                    FROM forum_topic_message ftm
                    LEFT JOIN client c ON c.id = ftm.client_id
                    LEFT JOIN forum_topic ft ON ft.id = ftm.forum_topic_id
                    WHERE ft.status IN ('active', 'locked')
                    AND ft.forum_id = :forum_id";

        $where                 = $bindings = array();
        $bindings[':forum_id'] = $forum->id;

        if (isset($filter['match_date']) && $filter['match_date'] !== "0") {
            $seconds                 = (int)$filter['match_date'] * 24 * 60 * 60;
            $where[]                 = "(UNIX_TIMESTAMP() - ftm.created_at) < :created_at";
            $bindings[':created_at'] = $seconds;
        }

        $where[]              = "ftm.message LIKE :message";
        $bindings[':message'] = "%$searchQuery%";

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= " ORDER BY ftm.id DESC";

        return $this->di['db']->getAssoc($query, $bindings);
    }

    public function findTopicMessages(Model_ForumTopic $topic)
    {
        $query    = "SELECT ftm.*, ft.id, ft.slug
                    FROM forum_topic_message ftm
                    LEFT JOIN client c ON c.id = ftm.client_id
                    WHERE ftm.forum_topic_id = :forum_topic_id
                    ORDER BY ftm.id ASC";
        $bindings = array(
            ':forum_topic_id' => $topic->id
        );

        return $this->di['db']->getAssoc($query, $bindings);
    }

    public function getMessagesCountForTopic(Model_ForumTopic $topic)
    {
        $query    = 'SELECT COUNT(id) as posts
                    FROM forum_topic_message
                    WHERE forum_topic_id = :forum_topic_id
                    GROUP BY forum_topic_id';
        $bindings = array(
            ':forum_topic_id' => $topic->id
        );

        return $this->di['db']->getCell($query, $bindings);
    }

    public function getMessagesCountForForum(Model_Forum $forum)
    {
        $query    = 'SELECT COUNT(ftm.id) as posts
                    FROM forum_topic_message ftm
                    LEFT JOIN forum_topic ft ON ftm.forum_topic_id = ft.id
                    WHERE ft.forum_id = :forum_id
                    GROUP BY ft.forum_id';
        $bindings = array(
            ':forum_id' => $forum->id
        );

        return $this->di['db']->getCell($query, $bindings);
    }

    public function getAuthorDetails(Model_ForumTopicMessage $model)
    {
        $author = false;

        if ($model->admin_id) {
            $author = $this->di['db']->getExistingModelById('Admin', $model->admin_id, 'Admin not found');
        }

        if ($model->client_id) {
            $author = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');
        }

        if ($author) {
            return array(
                'id'    => $author->id,
                'name'  => $author->getFullName(),
                'email' => $author->email,
            );
        }

        return array(
            'id'    => null,
            'name'  => 'Anonymous',
            'email' => 'anonymous@anonymous.com',
        );
    }

    /**
     * Do not remove client messages but mark them as null
     * @param Model_Client $client
     */
    public function rmByClient(Model_Client $client)
    {
        $query = "UPDATE forum_topic_message
                SET client_id = NULL
                WHERE client_id = :client_id";
        $bindings = array(
            ':client_id' => $client->id
        );

        $this->di['db']->exec($query, $bindings);
    }

    public function rm(Model_ForumTopicMessage $model)
    {
        $messages = $this->di['db']->find('ForumTopicMessage', 'forum_topic_id = :forum_topic_id', array(':forum_topic_id' => $model->forum_topic_id));
        if (count($messages) == 1) {
            throw new \Box_Exception('This is a last message in topic. Remove topic in order to remove this message.');
        }

        $this->di['db']->trash($model);
    }

    public function toApiArray(Model_ForumTopicMessage $model, $deep = false, $identity = null)
    {
        $topic = $this->di['db']->load('ForumTopic', $model->forum_topic_id);
        $forum = $this->di['db']->load('Forum', $topic->forum_id);

        $data = array(
            'id'             => $model->id,
            'forum_topic_id' => $model->forum_topic_id,
            'message'        => $model->message,
            'created_at'     => $model->created_at,
            'updated_at'     => $model->updated_at,
            'author'         => $this->getAuthorDetails($model),
        );

        $data['forum_slug']       = ($forum) ? $forum->slug : null;
        $data['forum_topic_slug'] = ($topic) ? $topic->slug : null;


        if ($identity instanceof Model_Admin) {
            $data['ip'] = $model->ip;
        }

        return $data;
    }

}