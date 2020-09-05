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


class Model_ForumTable implements \Box\InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di;

    /**
     * @param Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getSearchQuery($data)
    {
        $query     = "SELECT * FROM forum ORDER BY priority ASC, category ASC";
        $bindingss = array();

        return array($query, $bindingss);
    }

    public function findAll($hydrationMode = null)
    {
        $query = "SELECT f.*, ft.* FROM forum f
                LEFT JOIN forum_topic ft ON ft.forum_id = f.id
                ORDER BY f.priority ASC, ft.id DESC";

        return $this->di['db']->getAll($query);
    }

    public function findAllActive()
    {
        $query = "SELECT f.*, ft.* FROM forum f
                LEFT JOIN forum_topic ft ON ft.forum_id = f.id
                WHERE f.status IN ('active', 'locked')
                ORDER BY f.priority ASC, ft.id DESC";

        return $this->di['db']->getAll($query);
    }

    public function findOneActiveById($id)
    {
        $bindings = array(
            ':forum_id' => $id
        );

        return $this->di['db']->findOne('Forum', "id = :forum_id AND status IN ('active', 'locked')", $bindings);
    }

    public function findOneActiveBySlug($slug)
    {
        $bindings = array(
            ':slug' => $slug
        );

        return $this->di['db']->findOne('Forum', "slug = :slug AND status IN ('active', 'locked')", $bindings);
    }

    public function findAllActiveWithTopics()
    {
        $query = "SELECT f.*, ft.* FROM forum f
                LEFT JOIN forum_topic ft ON ft.forum_id = f.id
                WHERE f.status IN ('active', 'locked')
                ORDER BY f.priority ASC, ft.id DESC";

        return $this->di['db']->getAll($query);
    }

    public function getNextPriority()
    {
        $query = "SELECT priority FROM forum ORDER BY priority ASC LIMIT 1";

        $priority = $this->di['db']->getCell($query);

        return $priority + 1;
    }

    public function moveForum(Model_Forum $forum, $dir = 'up')
    {
        $current = $forum->priority;
        $near    = ($dir == 'up') ? $current - 1 : $current + 1;
        if ($near == 0) {
            return true;
        }

        $fn           = $this->di['db']->findOne('Forum', 'priority = :priority', array(':priority' => $near));
        $fn->priority = $current;
        $this->di['db']->store($fn);

        $forum->priority = $near;
        $this->di['db']->store($forum);

        return true;
    }

    public function getViewsCount(Model_Forum $forum)
    {
        $table = $this->di['table']('ForumTopic');

        return $table->getViewsCountForForum($forum);
    }

    public function getMessagesCount(Model_Forum $forum)
    {
        $table = $this->di['table']('ForumTopicMessage');

        return $table->getMessagesCountForForum($forum);
    }

    public function getPairs()
    {
        $query = "SELECT id, title FROM forum ORDER BY priority ASC";

        return $this->di['db']->getAssoc($query);
    }

    public function rm(Model_Forum $forum)
    {
        $table = $this->di['table']('ForumTopic');

        $forumTopic = $this->di['db']->find('ForumTopic', 'forum_id = :forum_id', array(':forum_id' => $forum->id));

        foreach ($forumTopic as $topic) {
            $table->rm($topic);
        }
        $this->di['db']->trash($forum);
    }

    public function toApiArray(\Model_Forum $forum, $deep = false, $identity = null)
    {
        $data = $this->di['db']->toArray($forum);

        $forumTopic                    = $this->di['db']->find('ForumTopic', 'forum_id = :forum_id', array(':forum_id' => $forum->id));
        $data['stats']['topics_count'] = count($forumTopic);
        $data['stats']['posts_count']  = $this->getMessagesCount($forum);
        $data['stats']['views_count']  = $this->getViewsCount($forum);

        return $data;
    }
}