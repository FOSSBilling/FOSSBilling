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


namespace Box\Mod\Forum\Controller;

class Client implements \Box\InjectionAwareInterface
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

    public function register(\Box_App &$app)
    {
        $app->get('/forum', 'get_index', array(), get_class($this));
        $app->get('/forum/members-list', 'get_members', array(), get_class($this));
        $app->get('/forum/topics.rss', 'get_rss', array(), get_class($this));
        $app->get('/forum/:forum', 'get_forum', array('forum' => '[a-z0-9-]+'), get_class($this));
        $app->get('/forum/:forum/:topic', 'get_forum_topic', array('forum' => '[a-z0-9-]+', 'topic' => '[a-z0-9-]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        return $app->render('mod_forum_index');
    }

    public function get_rss(\Box_App $app)
    {
        header('Content-Type: application/rss+xml;');
        return $app->render('mod_forum_rss');
    }
    
    public function get_forum(\Box_App $app, $forum)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'slug'  =>  $forum,
        );
        $f = $api->forum_get($data);
        return $app->render('mod_forum_forum', array('forum'=>$f));
    }

    public function get_forum_topic(\Box_App $app, $forum, $topic)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'slug'  =>  $forum,
        );
        $f = $api->forum_get($data);

        $data = array(
            'slug'  =>  $topic,
        );
        $t = $api->forum_get_topic($data);
        return $app->render('mod_forum_topic', array('topic'=>$t, 'forum'=>$f));
    }
    
    public function get_members(\Box_App $app)
    {
        return $app->render('mod_forum_members_list', array());
    }
}