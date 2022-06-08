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
        return [
            'subpages' => [
                [
                    'location' => 'support',
                    'index' => 700,
                    'label' => 'Forum',
                    'uri' => $this->di['url']->adminLink('forum'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/forum', 'get_index', [], get_class($this));
        $app->get('/forum/profile/:id', 'get_profile', ['id' => '[0-9]+'], get_class($this));
        $app->get('/forum/:id', 'get_forum', ['id' => '[0-9]+'], get_class($this));
        $app->get('/forum/topic/:id', 'get_topic', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_forum_index');
    }

    public function get_forum(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $forum = $api->forum_get(['id' => $id]);

        return $app->render('mod_forum_forum', ['forum' => $forum]);
    }

    public function get_topic(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $topic = $api->forum_topic_get(['id' => $id]);

        return $app->render('mod_forum_topic', ['topic' => $topic]);
    }

    public function get_profile(\Box_App $app, $id)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_forum_profile', ['client_id' => $id]);
    }
}
