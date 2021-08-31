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


namespace Box\Mod\Forum\Controller;

class Admin implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return array(
            'subpages'=>array(
                array(
                    'location'  => 'support',
                    'index'     => 700,
                    'label' => 'Forum',
                    'uri'   => $this->di['url']->adminLink('forum'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/forum', 'get_index', array(), get_class($this));
        $app->get('/forum/profile/:id', 'get_profile', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/forum/:id', 'get_forum', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/forum/topic/:id', 'get_topic', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_forum_index');
    }
    
    public function get_forum(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $forum = $api->forum_get(array('id'=>$id));
        return $app->render('mod_forum_forum', array('forum'=>$forum));
    }
    
    public function get_topic(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $topic = $api->forum_topic_get(array('id'=>$id));
        return $app->render('mod_forum_topic', array('topic'=>$topic));
    }
    
    public function get_profile(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_forum_profile', array('client_id'=>$id));
    }
}