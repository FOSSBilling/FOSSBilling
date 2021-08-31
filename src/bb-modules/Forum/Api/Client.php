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

class Client extends \Api_Abstract
{
    /**
     * Get paginated list of forums
     *
     * @return array
     */
    public function get_list($data)
    {
        $table = $this->di['table']('Forum');
        list($sql, $params) = $table->getSearchQuery($data);
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

        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
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
     * Create new topic
     *
     * @param int $forum_id - forum id
     * @param string $topic - topic title
     * @param string $message - topic message
     *
     * @optional string $status - initial topic status
     *
     * @return int - new topic id
     * @throws Box_Exception
     */
    public function start_topic($data)
    {
        if (!isset($data['forum_id'])) {
            throw new \Box_Exception('Forum ID not passed');
        }
        if (!isset($data['topic'])) {
            throw new \Box_Exception('Forum topic not passed');
        }
        if (!isset($data['message'])) {
            throw new \Box_Exception('Forum message not passed');
        }

        if (strlen($data['message']) < 2) {
            throw new \Box_Exception('Your message is too short');
        }

        $event_params              = $data;
        $event_params['client_id'] = $this->getIdentity()->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientCreateForumTopic', 'params' => $event_params));

        $table = $this->di['table']('Forum');;
        $forum = $table->findOneActiveById($data['forum_id']);
        if (!$forum instanceof \Model_Forum) {
            throw new \Box_Exception('Forum not found');
        }

        if ($forum->status == \Model_Forum::STATUS_LOCKED) {
            throw new \Box_Exception('Forum is locked. No new topics can be started');
        }

        $client = $this->getIdentity();

        $topic             = $this->di['db']->dispense('ForumTopic');
        $topic->forum_id   = $forum->id;
        $topic->title      = $data['topic'];
        $topic->slug       = $this->di['tools']->slug($data['topic']);
        $topic->status     = 'active';
        $topic->created_at = date('Y-m-d H:i:s');
        $topic->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($topic);

        $msg                 = $this->di['db']->dispense('ForumTopicMessage');
        $msg->client_id      = $client->id;
        $msg->forum_topic_id = $topic->id;
        $msg->message        = $data['message'];
        $msg->ip             = $this->getIp();
        $msg->created_at     = date('Y-m-d H:i:s');
        $msg->updated_at     = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['events_manager']->fire(array('event' => 'onAfterClientCreateForumTopic', 'params' => array('id' => $topic->id)));

        $this->subscribe(array('id' => $topic->id));

        $this->di['logger']->info('Started new forum topic "%s"', $topic->title);

        //@EasterEgg to return slug instead of id
        if (isset($data['return']) && $data['return'] == 'slug') {
            return $topic->slug;
        }

        return (int)$topic->id;
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

        return $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
    }

    /**
     * Post new message to topic
     *
     * @param int $forum_topic_id - forum topic id
     * @param string $message - topic message
     *
     * @return id
     * @throws Box_Exception
     */
    public function post_message($data)
    {
        if (!isset($data['forum_topic_id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        if (!isset($data['message'])) {
            throw new \Box_Exception('Topic message not passed');
        }

        if (strlen($data['message']) < 2) {
            throw new \Box_Exception('Your message is too short');
        }

        $topic = $this->di['db']->getExistingModelById('ForumTopic', $data['forum_topic_id'], 'Forum Topic not found');

        if ($topic->status == \Model_ForumTopic::STATUS_LOCKED) {
            throw new \Box_Exception('Forum topic is locked. No new message can be posted');
        }

        $client = $this->getIdentity();

        $data['client_id'] = $client->id;
        $this->di['events_manager']->fire(array('event' => 'onBeforeClientRepliedInForum', 'params' => $data));

        $msg                 = $this->di['db']->dispense('ForumTopicMessage');
        $msg->client_id      = $client->id;
        $msg->forum_topic_id = $topic->id;
        $msg->message        = $data['message'];
        $msg->ip             = $this->getIp();
        $msg->created_at     = date('Y-m-d H:i:s');
        $msg->updated_at     = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $topic->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($topic);

        $this->di['events_manager']->fire(array('event' => 'onAfterClientRepliedInForum', 'params' => array('id' => $topic->id, 'message_id' => $msg->id, 'client_id' => $client->id)));

        $this->di['logger']->info('Posted message in topic #%s', $topic->id);

        return (int)$msg->id;
    }

    /**
     * Check if current client is subscribed to forum notifications
     *
     * @return bool
     * @throws Box_Exception
     */
    public function is_subscribed($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        $client = $this->getIdentity();
        $sql    = "
            SELECT meta_value 
            FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = :ext 
            AND rel_type = :type 
            AND rel_id = :rid
            AND meta_key = :key
        ";
        $tid    = $data['id'];

        $value = $this->di['db']->getCell($sql, array('cid' => $client->id, 'ext' => 'mod_forum', 'type' => 'forum_topic', 'rid' => $tid, 'key' => 'notification'));

        return (bool)$value;
    }

    /**
     * Unsubscribe client from topic notifications
     *
     * @return bool
     * @throws Box_Exception
     */
    public function unsubscribe($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        $client = $this->getIdentity();
        $sql    = "
            DELETE FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = :ext 
            AND rel_type = :type 
            AND rel_id = :rid
            AND meta_key = :key
        ";
        $tid    = $data['id'];

        $this->di['db']->exec($sql, array('cid' => $client->id, 'ext' => 'mod_forum', 'type' => 'forum_topic', 'rid' => $tid, 'key' => 'notification'));

        $this->di['logger']->info('Unsubscribed from forum topic %s', $data['id']);

        return true;
    }

    /**
     * Subscribe client to forum topic notifications
     *
     * @param int $id - forum topic id
     * @return bool
     * @throws Box_Exception
     */
    public function subscribe($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        if ($this->is_subscribed($data)) {
            throw new \Exception('You have already subscribed to this topic notifications');
        }

        $client           = $this->getIdentity();
        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_forum';
        $meta->client_id  = $client->id;
        $meta->rel_type   = 'forum_topic';
        $meta->rel_id     = $data['id'];
        $meta->meta_key   = 'notification';
        $meta->meta_value = 1;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        $this->di['logger']->info('Subscribed to forum topic %s', $data['id']);

        return true;
    }

    /**
     * Get list of topics added to favorites
     *
     * @return array
     */
    public function favorites($data)
    {
        $client = $this->getIdentity();
        $sql    = "
            SELECT rel_id 
            FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = :ext 
            AND rel_type = :type 
            AND meta_key = :key
        ";
        $list   = $this->di['db']->getAssoc($sql, array('cid' => $client->id, 'ext' => 'mod_forum', 'type' => 'forum_topic', 'key' => 'favorite'));
        $result = array();
        $table  = $this->di['table']('ForumTopic');
        $topics = $table->getTopicsByIds(array_values($list));
        foreach ($topics as $topic) {
            $result[] = $table->toApiArray($topic);
        }

        return $result;
    }

    /**
     * Check if topic is added to favorites
     *
     * @param int $id - forum topic id
     * @return bool
     * @throws Box_Exception
     */
    public function is_favorite($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        $client = $this->getIdentity();
        $sql    = "
            SELECT meta_value 
            FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = :ext 
            AND rel_type = :type 
            AND rel_id = :rid
            AND meta_key = :key
        ";
        $tid    = $data['id'];

        $value = $this->di['db']->getCell($sql, array('cid' => $client->id, 'ext' => 'mod_forum', 'type' => 'forum_topic', 'rid' => $tid, 'key' => 'favorite'));

        return (bool)$value;
    }

    /**
     * Add topic to favorites
     *
     * @param int $id - forum topic id
     * @return boolean
     * @throws Box_Exception
     * @throws Exception
     */
    public function favorite_add($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        if ($this->is_favorite($data)) {
            throw new \Exception('You have already added this topic to favorites');
        }

        $client           = $this->getIdentity();
        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_forum';
        $meta->client_id  = $client->id;
        $meta->rel_type   = 'forum_topic';
        $meta->rel_id     = $data['id'];
        $meta->meta_key   = 'favorite';
        $meta->meta_value = 1;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);

        $this->di['logger']->info('Added forum topic %s to favorites', $data['id']);

        return true;
    }

    /**
     * Remove topic from favorites
     *
     * @param int $id - forum topic id
     * @return boolean
     * @throws Box_Exception
     */
    public function favorite_remove($data)
    {
        if (!isset($data['id'])) {
            throw new \Box_Exception('Forum Topic ID not passed');
        }

        $client = $this->getIdentity();
        $sql    = "
            DELETE FROM extension_meta 
            WHERE client_id = :cid 
            AND extension = :ext 
            AND rel_type = :type 
            AND rel_id = :rid
            AND meta_key = :key
        ";
        $tid    = $data['id'];

        $this->di['db']->exec($sql, array('cid' => $client->id, 'ext' => 'mod_forum', 'type' => 'forum_topic', 'rid' => $tid, 'key' => 'favorite'));

        $this->di['logger']->info('Removed forum topic %s from favorites', $data['id']);

        return true;
    }

    /**
     * Forum profile
     *
     * @return array
     * @throws Box_Exception
     */
    public function profile($data)
    {
        return $this->getService()->getProfile($this->getIdentity()->id);
    }
}