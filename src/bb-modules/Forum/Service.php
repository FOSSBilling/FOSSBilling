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


namespace Box\Mod\Forum;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
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

    public function getProfile($client_id)
    {
        $sql='SELECT 
            id,
            CONCAT(c.first_name, " ", c.last_name) as name, 
            CONCAT("https://gravatar.com/avatar/", MD5(c.email)) as gravatar,
            c.created_at
            FROM client c
            WHERE c.id = :cid
        ';
        
        $details = $this->di['db']->getRow($sql, array(':cid'=>$client_id));
        $posts = $this->di['db']->getCell('SELECT COUNT(id) FROM forum_topic_message WHERE client_id = :cid', array(':cid'=>$client_id));
        
        $points = $this->di['db']->getCell('SELECT meta_value 
            FROM extension_meta 
            WHERE extension = "mod_forum" 
            AND meta_key = "points_total" 
            AND client_id = :cid', 
                array('cid'=>$client_id));

        $profile = array();
        $profile['id']          = $details['id'];
        $profile['posts']       = $posts;
        $profile['points']      = ($points) ? $points : 0;
        $profile['gravatar']    = $details['gravatar'];
        $profile['name']        = $details['name'];
        $profile['created_at']  = $details['created_at'];
        return $profile;
    }
    
    public function getMembersListQuery($data)
    {
        $sql='
            SELECT 
            0 as posts,
            CONCAT(c.first_name, " ", c.last_name) as name, 
            CONCAT("https://gravatar.com/avatar/", MD5(c.email)) as gravatar,
            c.created_at
            FROM client c
            RIGHT JOIN forum_topic_message fp ON (fp.client_id = c.id)
            WHERE fp.id > 0
        ';
        
        $params = array();
        
        $first_char = $this->di['array_get']($data, 'first_char', NULL);
        
        if(NULL !== $first_char) {
            $sql .= ' AND c.first_name LIKE :first_char';
            $params['first_char'] = $first_char.'%';
        }

        $sql .= ' ORDER BY c.first_name DESC ';
        return array($sql, $params);
    }
    
    public function getMessagesQuery($data)
    {
        $sql='
        SELECT fp.id, fp.client_id, fp.admin_id, fp.message, fp.points,
        ft.slug as forum_topic_slug, 
        f.slug as forum_slug, 
        fp.created_at, 
        fp.updated_at, 
        f.id as forum_id, 
        fp.forum_topic_id, 
        CONCAT(f.category, " > ", f.title, " > ", ft.title) as forum_title
        FROM forum_topic_message fp
        LEFT JOIN forum_topic ft ON (ft.id = fp.forum_topic_id)
        LEFT JOIN forum f ON (f.id = ft.forum_id)
        WHERE 1 
        ';
        
        $params = array();
        
        $search          = (isset($data['q']) && !empty($data['q'])) ? $data['q'] : NULL;
        $search2         = (isset($data['search']) && !empty($data['search'])) ? $data['search'] : NULL;
        $forum_topic_id  = (isset($data['forum_topic_id']) && !empty($data['forum_topic_id'])) ? $data['forum_topic_id'] : NULL;
        $forum_id        = $this->di['array_get']($data, 'forum_id', NULL);
        $client_id       = $this->di['array_get']($data, 'client_id', NULL);
        $with_points       = $this->di['array_get']($data, 'with_points', NULL);

        if($with_points) {
            $sql .= ' AND fp.points IS NOT NULL AND fp.points != "" ';
        }
        
        if($forum_topic_id) {
            $sql .= ' AND ft.id = :forum_topic_id';
            $params['forum_topic_id'] = $forum_topic_id;
        }

        if($forum_id) {
            $sql .= ' AND f.id = :forum_id';
            $params['forum_id'] = $forum_id;
        }
        
        if($client_id) {
            $sql .= ' AND fp.client_id = :cid';
            $params['cid'] = $client_id;
        }

        if($search) {
            $sql .= ' AND fp.message LIKE :search';
            $params['search'] = "%$search%";
        }
        
        if($search2) {
            $sql .= ' AND fp.message LIKE :search2';
            $params['search2'] = "%$search2%";
        }
        
        if(isset($data['orderby'])) {
            if(in_array(strtolower($data['orderby']), array('id', 'created_at'))) {
                $sortorder = (isset($data['sortorder']) && in_array(strtolower($data['sortorder']), array('asc', 'desc'))) ? $data['sortorder'] : 'asc' ;
                $sql .= ' ORDER BY '.sprintf('fp.%s %s', $data['orderby'], strtoupper($sortorder));
            }
        } else {
            $sql .= ' ORDER BY fp.id DESC';
        }
        
        return array($sql, $params);
    }
    
    public function updateTotalPoints($client_id, $amount, $increment = false)
    {
        $meta = $this->getTotalPointsMeta($client_id);
        if($increment) {
            $amount = $meta->meta_value + $amount;
        }
        $meta->meta_value = $amount;
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);
    }

    public function declinePointsForPost($post_id)
    {
        $post = $this->di['db']->getExistingModelById('forum_topic_message', $post_id, 'Message not found');

        $minus_points = -$post->points;

        $post->points     = null;
        $post->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($post);

        $this->updateTotalPoints($post->client_id, $minus_points, true);
    }
    
    private function getTotalPointsMeta($client_id)
    {
        $meta = $this->di['db']->findOne('extension_meta', 
                'extension = "mod_forum" AND meta_key = "points_total" AND client_id = :cid', 
                array('cid'=>$client_id));
        if(!$meta) {
            $meta = $this->di['db']->dispense('extension_meta');
            $meta->extension = 'mod_forum';
            $meta->client_id = $client_id;
            $meta->meta_key = 'points_total';
            $meta->meta_value = 0;
            $meta->created_at = date('Y-m-d H:i:s');
            $meta->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($meta);
        }        
        return $meta;
    }
    
    public function getTopicSubscribers($topic_id)
    {
        $sql="
            SELECT client_id 
            FROM extension_meta 
            WHERE extension = 'mod_forum'
            AND rel_type = 'forum_topic'
            AND rel_id = :rid
            AND meta_key = 'notification'
        ";

        return $this->di['db']->getAssoc($sql, array(':rid'=>$topic_id));
    }
    
    public static function onAfterClientCreateForumTopic(\Box_Event $event)
    {
        $params = $event->getParameters();
        $id = $params['id'];
    }
    
    public static function onAfterAdminRepliedInForum(\Box_Event $event)
    {
        $di = $event->getDi();
        $api = $di['api_admin'];
        $params = $event->getParameters();
        $id = $params['id'];
        $message_id = $params['message_id'];

        $service = $di['mod_service']('forum');
        $list = $service->getTopicSubscribers($id);

        $message = $api->forum_message_get(array('id'=>$message_id));
        $topic = $api->forum_topic_get(array('id'=>$id));
        
        foreach($list as $cid) {
            $email = array();
            $email['to_client'] = $cid;
            $email['code'] = 'mod_forum_new_post';
            $email['topic_id'] = $id;
            $email['message_id'] = $message_id;
            
            $email['message'] = $message;
            $email['topic'] = $topic;
            
            try {
                $api->email_template_send($email);
            } catch(\Exception $exc) {
                error_log($exc->getMessage());
            }
        }
    }
    
    public static function onAfterClientRepliedInForum(\Box_Event $event)
    {
        $di = $event->getDi();
        $api = $di['api_admin'];
        $params = $event->getParameters();
        $id = $params['id'];
        $message_id = $params['message_id'];
        $client_id = $params['client_id'];
        
        $service = $di['mod_service']('forum');
        $list = $service->getTopicSubscribers($id);

        $message = $api->forum_message_get(array('id'=>$message_id));
        $topic = $api->forum_topic_get(array('id'=>$id));
        
        foreach($list as $cid) {
            if($client_id == $cid) {
                //do not send email to author
                continue;
            }
            
            $email = array();
            $email['to_client'] = $cid;
            $email['code'] = 'mod_forum_new_post';
            $email['topic_id'] = $id;
            $email['message_id'] = $message_id;
            
            $email['message'] = $message;
            $email['topic'] = $topic;
            
            try {
                $api->email_template_send($email);
            } catch(\Exception $exc) {
                error_log($exc->getMessage());
            }
        }
        
        //forum points

        $mod = $di['mod']('forum');
        $config = $mod->getConfig();
        $points = $di['array_get']($config, 'points', 0);
        $points_forums = $di['array_get']($config, 'points_forums', array());

        if(isset($config['forum_points_enable']) 
            && $config['forum_points_enable'] 
            && !empty($points_forums)
            && in_array($topic['forum']['id'], $points_forums)
            && strlen($message['message']) >= $config['post_length']
            && $points > 0) {
            
            $di['db']->exec('UPDATE forum_topic_message SET points = :points WHERE id = :id',
                    array('points'=>$points, 'id'=>$message_id));
            
            $service = $mod->getService();
            $service->updateTotalPoints($client_id, $points, true);
        }
    }
}