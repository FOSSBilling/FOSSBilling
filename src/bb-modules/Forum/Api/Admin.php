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

class Admin extends \Api_Abstract
{
    /**
     * Get pairs of forums
     * 
     * @return array
     */
    public function get_pairs($data)
    {
        $table = $this->di['table']('Forum');
        return $table->getPairs();
    }
    
    /**
     * Get paginated list of forums
     * 
     * @return array
     */
    public function get_list($data)
    {
        $table = $this->di['table']('Forum');
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list ($sql, $params)= $table->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as $key => $item){
            $forum = $this->di['db']->getExistingModelById('Forum', $item['id'], 'Forum not found');
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
        $list = $this->di['db']->find('Forum');
        
        $result = array();
        foreach($list as $f) {
            $result[$f->category][] = $table->toApiArray($f);
        }
        return $result;
    }
    
    /**
     * Get forum details
     * 
     * @param int $id - forum id
     * @return array
     * @throws \Box_Exception 
     */
    public function get($data)
    {
        $required = array(
            'id' => 'Forum id was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $this->di['table']('Forum');
        $model = $this->di['db']->getExistingModelById('Forum', $data['id'], 'Forum not found');

        return $table->toApiArray($model, true, $this->getIdentity());
    }
    
    /**
     * Create new forum
     * 
     * @param string $title - new forum title
     * 
     * @optional string $category - new forum category
     * 
     * @return int - new forum id
     * @throws \Box_Exception 
     */
    public function create($data)
    {
        $required = array(
            'title' => 'Forum title was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $systemService = $this->di['mod_service']('system');
        $systemService->checkLimits('Model_Forum', 3);

        $priority = $this->di['db']->getCell("SELECT MAX(priority) FROM forum LIMIT 1");

        $model              = $this->di['db']->dispense('Forum');
        $model->category    = $this->di['array_get']($data, 'category', NULL);
        $model->title       = $data['title'];
        $model->slug        = $this->di['tools']->slug($data['title']);
        $model->status      = isset($data['status']) ? $data['status'] : \Model_Forum::STATUS_ACTIVE;
        $model->description = $this->di['array_get']($data, 'description', NULL);
        $model->priority    = $priority + 1;
        $model->created_at  = date('Y-m-d H:i:s');
        $model->updated_at  = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Created new forum "%s"', $model->title);

        return $model->id;
    }
    
    /**
     * Update existing forum
     * 
     * @param int $id - forum id
     * 
     * @optional string $category - new forum category
     * @optional string $title - new forum title
     * @optional string $status - new forum status
     * @optional string $slug - new forum slug
     * @optional string $description - new forum description
     * @optional string $priority - new forum priority
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function update($data)
    {
        $required = array(
            'id' => 'Forum id missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required,  $data);

        $model = $this->di['db']->getExistingModelById('Forum', $data['id'], 'Forum not found');

        $category = $this->di['array_get']($data, 'category');
        if($category) {
            if($this->di['array_get']($data, 'update_categories')) {
                $this->di['db']->exec('UPDATE forum SET category = :cat WHERE category = :old_cat', 
                        array('cat'=>$category, 'old_cat'=>$model->category));
            }
            $model->category = $category;
        }

        $model->title = $this->di['array_get']($data, 'title', $model->title);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->slug = $this->di['array_get']($data, 'slug', $model->slug);
        $model->description = $this->di['array_get']($data, 'description', $model->description);
        $model->priority = $this->di['array_get']($data, 'priority', $model->priority);

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated forum "%s"', $model->title);
        return true;
    }

    /**
     * Change forums sorting order
     * 
     * @param array $priority - forum id => priority pairs
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function update_priority($data)
    {
        if(!isset($data['priority']) || !is_array($data['priority'])) {
            throw new \Box_Exception('priority params is missing');
        }

        foreach($data['priority'] as $id => $p) {
            $model = $this->di['db']->getExistingModelById('Forum', $id);
            if($model instanceof \Model_Forum) {
                $model->priority = $p;
                $model->updated_at = date('Y-m-d H:i:s');
                $this->di['db']->store($model);
            }
        }
        
        $this->di['logger']->info('Changed forums priorities');
        return true;
    }

    /**
     * Remove forum with all topics
     * 
     * @param int $id - forum id
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'Forum id was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $this->di['table']('Forum');
        $model = $this->di['db']->getExistingModelById('Forum', $data['id'], 'Forum not found');
        
        $id = $model->id;
        $table->rm($model);
        
        $this->di['logger']->info('Deleted forum #%s', $id);
        return true;
    }

    /**
     * Get paginated list of topics
     * 
     * @return array
     */
    public function topic_get_list($data)
    {
        $table = $this->di['table']('ForumTopic');
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list($sql, $params) = $table->getSearchQuery($data);
        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $topic               = $this->di['db']->getExistingModelById('ForumTopic', $item['id'], 'Forum topic not found');
            $pager['list'][$key] = $table->toApiArray($topic);
        }

        return $pager;
    }

    /**
     * Get topic details
     * 
     * @param int $id - topic id
     * @return array
     * @throws \Box_Exception 
     */
    public function topic_get($data)
    {
        $required = array(
            'id' => 'Topic id was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $this->di['table']('ForumTopic');
        $model = $this->di['db']->getExistingModelById('ForumTopic', $data['id'], 'Forum Topic not found');
        
        return $table->toApiArray($model, true, $this->getIdentity());
    }

    /**
     * Remove topic
     * 
     * @param int $id - topic id
     * @return boolean
     * @throws \Box_Exception 
     */
    public function topic_delete($data)
    {
        $required = array(
            'id' => 'Topic id was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $this->di['table']('ForumTopic');
        $model = $this->di['db']->getExistingModelById('ForumTopic', $data['id'], 'Forum Topic not found');

        $id = $model->id;
        $table->rm($model);
        $this->di['logger']->info('Deleted forum topic #%s', $id);
        return true;
    }

    /**
     * Create new topic
     * 
     * @param int $forum_id - forum id
     * @param string $title - topic title
     * @param string $message - topic message
     * 
     * @optional string $status - initial topic status
     * 
     * @return int - new topic id
     * @throws \Box_Exception 
     */
    public function topic_create($data)
    {
        $required = array(
            'forum_id' => 'Forum ID was not passed',
            'title'    => 'Forum topic title not passed',
            'message'  => 'Forum topic message not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if(strlen($data['message']) < 2) {
            throw new \Box_Exception('Your message is too short');
        }

        $this->di['db']->getExistingModelById('Forum', $data['forum_id'], 'Forum not found');
        $forum = $this->di['db']->getExistingModelById('Forum', $data['forum_id'], 'Forum not found');

        $topic = $this->di['db']->dispense('ForumTopic');
        $topic->forum_id = $forum->id;
        $topic->title = $data['title'];
        $topic->slug = $this->di['tools']->slug($data['title']);
        $topic->status = isset($data['status']) ? $data['status'] : \Model_ForumTopic::STATUS_ACTIVE;
        $topic->created_at = date('Y-m-d H:i:s');
        $topic->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($topic);

        $msg = $this->di['db']->dispense('ForumTopicMessage');
        $msg->admin_id = $this->getIdentity()->id;
        $msg->forum_topic_id = $topic->id;
        $msg->message = $data['message'];
        $msg->ip = $this->getIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $this->di['logger']->info('Started new forum topic "%s"', $topic->title);

        return (int)$topic->id;
    }

    /**
     * Update forum topic
     * 
     * @param int $id - topic id
     * 
     * @optional string $title - topic title
     * @optional string $message - topic message
     * @optional string $status - topic status
     * @optional string $slug - topic slug
     * @optional int $views - topic views count
     * @optional bool $sticky - topic sticky flag
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function topic_update($data)
    {
        $required = array(
            'id' => 'Forum topic id was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ForumTopic', $data['id'], 'Forum Topic not found');
        
        $model->forum_id = $this->di['array_get']($data, 'forum_id', $model->forum_id);
        $model->title = $this->di['array_get']($data, 'title', $model->title);
        $model->status = $this->di['array_get']($data, 'status', $model->status);
        $model->slug = $this->di['array_get']($data, 'slug', $model->slug);
        $model->sticky = $this->di['array_get']($data, 'sticky', $model->sticky);
        $model->views = $this->di['array_get']($data, 'views', $model->views);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated forum topic "%s"', $model->title);
        return true;
    }
    
    /**
     * Get topic messages list
     * 
     * @optional int $forum_topic_id - topic id
     * @optional int $client_id - filter by client id
     * @optional bool $with_points - get messages with points only
     * 
     * @return array
     * @throws \Box_Exception 
     */
    public function message_get_list($data)
    {
        $data['orderby']    = 'created_at';
        $data['sortorder']  = 'desc';
        $per_page = $this->di['array_get']($data, 'per_page', $this->di['pager']->getPer_page());
        list($sql, $params) = $this->getService()->getMessagesQuery($data);

        $pager = $this->di['pager']->getSimpleResultSet($sql, $params, $per_page);
        foreach($pager['list'] as &$entry) {
            $entry['author'] = $this->getService()->getProfile($entry['client_id']);
            if (!$entry['client_id'] && $entry['admin_id']){
                $admin = $this->di['db']->getExistingModelById('Admin', $entry['admin_id'], 'Admin not found');
                $entry['author']['gravatar'] = 'https://gravatar.com/avatar/' . md5($admin->email);
            }
        }
        return $pager;
    }
    
    /**
     * Get forum topic message
     * 
     * @param int $id - message id
     * @return type
     * @throws \Box_Exception 
     */
    public function message_get($data)
    {
        $required = array(
            'id' => 'Forum Topic message ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $this->di['table']('ForumTopicMessage');

        $msg = $this->di['db']->getExistingModelById('ForumTopicMessage', $data['id'], 'Forum Topic Message not found');

        return $table->toApiArray($msg, true, $this->getIdentity());
    }

    /**
     * Update forum topic message
     * 
     * @param int $id - message id
     * 
     * @optional string $message - topic message
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function message_update($data)
    {
        $required = array(
            'id' => 'Forum Topic message ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ForumTopicMessage', $data['id'], 'Forum Topic Message not found');

        $model->message = $this->di['array_get']($data, 'message', $model->message);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated forum topic message #%s', $model->id);
        return true;
    }

    /**
     * Delete topic message
     * 
     * @param int $id - message id
     * 
     * @return boolean
     * @throws \Box_Exception 
     */
    public function message_delete($data)
    {
        $required = array(
            'id' => 'Forum Topic message ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $table = $table = $this->di['table']('ForumTopicMessage');
        $model =  $this->di['db']->getExistingModelById('ForumTopicMessage', $data['id'], 'Forum Topic Message not found');

        $id = $model->id;
        $table->rm($model);

        $this->di['logger']->info('Admin removed forum topic message  #%s', $id);
        return true;
    }

    /**
     * Post new message to topic
     * 
     * @param int $forum_topic_id - forum topic id
     * @param string $message - topic message
     * 
     * @return id
     * @throws \Box_Exception 
     */
    public function message_create($data)
    {
        $required = array(
            'forum_topic_id' => 'Forum Topic ID not passed',
            'message'        => 'Topic message not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        if(strlen($data['message']) < 2) {
            throw new \Box_Exception('Your message is too short');
        }

        $topic = $this->di['db']->getExistingModelById('ForumTopic', $data['forum_topic_id'], 'Forum Topic not found');

        $admin = $this->getIdentity();

        $msg = $this->di['db']->dispense('ForumTopicMessage');
        $msg->admin_id = $admin->id;
        $msg->forum_topic_id = $topic->id;
        $msg->message = $data['message'];
        $msg->ip = $this->getIp();
        $msg->created_at = date('Y-m-d H:i:s');
        $msg->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($msg);

        $topic->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($topic);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminRepliedInForum', 'params'=>array('id'=>$topic->id, 'message_id'=>$msg->id, 'admin_id'=>$admin->id)));
        
        $this->di['logger']->info('Posted new forum message %s', $msg->id);
        return (int)$msg->id;
    }

    /**
     * Decline post. Post will be considered as not worth the points.
     * 
     * @param int $id - message id
     * 
     * @return bool
     * @throws \Box_Exception
     */
    public function points_deduct($data)
    {
        $required = array(
            'id' => 'Message ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
       
        $service = $service = $this->di['mod_service']('forum');
        $service->declinePointsForPost($data['id']); 
        
        $this->di['logger']->info('Deducted points from forum message #%s', $data['id']);
        return true;
    }
    
    /**
     * Update total points for client
     * 
     * @param int $client_id - client id
     * @param float $amount - new points total
     * 
     * @return boolean
     * @throws \Box_Exception
     */
    public function points_update($data)
    {
        $required = array(
            'client_id' => 'Client ID not passed',
            'amount' => 'Amount not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $service = $service = $this->di['mod_service']('forum');
        $service->updateTotalPoints($data['client_id'], $data['amount']); 
        
        $this->di['logger']->info('Updated client %s forum points balance to %s', $data['client_id'], $data['amount']);
        return true;
    }
    
    /**
     * Client forum profile
     * 
     * @param int $client_id - client id
     * @return array
     * @throws \Box_Exception
     */
    public function profile_get($data)
    {
        $required = array(
            'client_id' => 'Client ID not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $forumService = $this->di['mod_service']('forum');
        return $forumService->getProfile($data['client_id']);
    }
}