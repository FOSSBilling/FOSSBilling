<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
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
        $app->get('/forum', 'get_index', [], get_class($this));
        $app->get('/forum/members-list', 'get_members', [], get_class($this));
        $app->get('/forum/topics.rss', 'get_rss', [], get_class($this));
        $app->get('/forum/:forum', 'get_forum', ['forum' => '[a-z0-9-]+'], get_class($this));
        $app->get('/forum/:forum/:topic', 'get_forum_topic', ['forum' => '[a-z0-9-]+', 'topic' => '[a-z0-9-]+'], get_class($this));
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
        $data = [
            'slug' => $forum,
        ];
        $f = $api->forum_get($data);

        return $app->render('mod_forum_forum', ['forum' => $f]);
    }

    public function get_forum_topic(\Box_App $app, $forum, $topic)
    {
        $api = $this->di['api_guest'];
        $data = [
            'slug' => $forum,
        ];
        $f = $api->forum_get($data);

        $data = [
            'slug' => $topic,
        ];
        $t = $api->forum_get_topic($data);

        return $app->render('mod_forum_topic', ['topic' => $t, 'forum' => $f]);
    }

    public function get_members(\Box_App $app)
    {
        return $app->render('mod_forum_members_list', []);
    }
}
