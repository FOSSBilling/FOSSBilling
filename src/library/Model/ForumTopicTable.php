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
 * Model_ForumTopicTable
 *
 */
class Model_ForumTopicTable implements \Box\InjectionAwareInterface
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

    public function getActiveForumTopics(Model_Forum $forum)
    {
        $query = "SELECT * FROM forum_topic ft
                LEFT JOIN forum_topic_message ftm ON ftm.forum_topic_id = ft.id
                WHERE ft.forum_id = :forum_id
                AND ft.status IN ('active', 'locked')
                ORDER BY ftm.created_at DESC";

        $bindings = array(
            ':forum_id' => $forum->id
        );

        return $this->di['db']->exec($query, $bindings);
    }

    public function getSearchQuery($data)
    {
      $query = "SELECT ft.* FROM forum_topic ft
                LEFT JOIN forum_topic_message ftm ON ftm.forum_topic_id = ft.id";

        $search = isset($data['search']) ? $data['search'] : NULL;

        $where = $bindings = array();

        if (isset($data['forum_id']) && !empty($data['forum_id'])) {
            $where[]               = "ft.forum_id = :forum_id";
            $bindings[':forum_id'] = $data['forum_id'];
        }

        if ($search) {
            $where[]              = "ft.title LIKE :title OR ftm.message LIKE :message";
            $bindings[':title']   = "%$search%";
            $bindings[':message'] = "%$search%";
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }
        $query .= " ORDER BY ft.sticky DESC, ftm.id DESC";

        return array($query, $bindings);
    }

    public function getTopicsByIds(array $ids)
    {
        if(empty($ids)) {
            return array();
        }

        $bindings = array(
            ':ids' => implode(',', $ids)
        );

        return $this->di['db']->find('ForumTopic', "id IN (:ids) AND status IN ('active', 'locked')", $bindings);
    }

    public function findTopic($slug)
    {
        $query = "SELECT ft.*, f.* FROM forum_topic ft
                LEFT JOIN forum f ON ft.forum_id = f.id
                WHERE ft.slug = :slug
                AND ft.status IN ('active', 'locked')";

       $bindings = array(
           ':slug' => $slug
       );
        
        return $this->di['db']->getAssoc($query, $bindings);
    }
    
    public function findForumTopic($forum, $topic)
    {
        $query = "SELECT ft.*, f.*, ftm.*
                FROM forum_topic ft
                LEFT JOIN forum f ON ft.forum_id = f.id
                LEFT JOIN forum_topic_message ftm ON ftm.forum_topic_id = ft.id
                WHERE ft.slug = :topic
                AND f.slug = :forum";

        $bindings = array(
            ':topic' => $topic,
            ':forum' => $forum
        );
        
        return $this->di['db']->getAssoc($query, $bindings);
    }

    public function getViewsCountForForum(Model_Forum $forum)
    {
        $query    = "SELECT SUM(views) as views
                FROM forum_topic
                WHERE forum_id = :forum_id
                GROUP BY id
                ";
        $bindings = array(
            ':forum_id' => $forum->id
        );

        return $this->di['db']->getCell($query, $bindings);
    }
    
    public function hitView(Model_ForumTopic $topic)
    {
        $topic->views  = $topic->views + 1;
        $this->di['db']->store($topic);
    }
    
    public function getAuthorClient(Model_ForumTopic $topic)
    {
        $bindings = array(
            ':forum_topic_id' => $topic->id
        );
        $forumTopicMessage = $this->di['db']->findOne('ForumTopicMessage', 'forum_topic_id = :forum_topic_id ORDER BY id ASC LIMIT 1', $bindings);
        if($forumTopicMessage instanceof Model_ForumTopicMessage) {
            return $this->di['db']->getExistingModelById('Client', $forumTopicMessage->client_id, 'Client not found');
        }
        return null;
    }

    public function getParticipants(Model_ForumTopic $topic)
    {
        $q="SELECT DISTINCT(client_id) as id FROM `forum_topic_message`
            WHERE forum_topic_id = :id
            AND client_id IS NOT NULL";

        $pdo = $this->di['pdo'];
        $stmt = $pdo->prepare($q);
        $stmt->execute(array('id'=>$topic->id));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function rm(Model_ForumTopic $topic)
    {
        $bindings = array(
            ':forum_topic_id' => $topic->id
        );

        $forumTopicMessage = $this->di['db']->find('ForumTopicMessage', 'forum_topic_id = :forum_topic_id', $bindings);

        foreach ($forumTopicMessage as $msg) {
            $this->di['db']->trash($msg);
        }

        $this->di['db']->trash($topic);
    }

    public function toApiArray(Model_ForumTopic $topic, $deep = false, $identity = null)
    {
        $table = $this->di['table']('ForumTopicMessage');

        $bindings = array(
            ':forum_topic_id' => $topic->id
        );

        $forum = $this->di['db']->getExistingModelById('Forum', $topic->forum_id);;
        $first = $this->di['db']->findOne('ForumTopicMessage', 'forum_topic_id = :forum_topic_id ORDER BY id ASC LIMIT 1', $bindings);;
        $last = $this->di['db']->findOne('ForumTopicMessage', 'forum_topic_id = :forum_topic_id ORDER BY id DESC LIMIT 1', $bindings);;

        $data = array(
            'id'            =>  $topic->id,
            'title'         =>  $topic->title,
            'slug'          =>  $topic->slug,
            'status'        =>  $topic->status,
            'sticky'        =>  $topic->sticky,
            'created_at'    =>  $topic->created_at,
            'updated_at'    =>  $topic->updated_at,
        );
        $data['forum'] = array(
            'id'        =>  $forum->id,
            'slug'      =>  $forum->slug,
            'title'     =>  $forum->title,
            'category'  =>  $forum->category,
        );
        if($first instanceof Model_ForumTopicMessage) {
            $data['first'] = $table->toApiArray($first, $deep, $identity);
        }

        if($last instanceof Model_ForumTopicMessage) {
            $data['last'] = $table->toApiArray($last, $deep, $identity);
        }

        $data['stats']['posts_count'] = $table->getMessagesCountForTopic($topic);
        $data['stats']['views_count'] = $topic->views;
        
        return $data;
    }
}