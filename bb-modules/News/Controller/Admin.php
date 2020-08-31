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


namespace Box\Mod\News\Controller;

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
            'subpages'  =>  array(
                array(
                    'location'  => 'support',
                    'index'     => 900,
                    'label' => 'Announcements',
                    'uri'   => $this->di['url']->adminLink('news'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/news',           'get_index', array(), get_class($this));
        $app->get('/news/',          'get_index', array(), get_class($this));
        $app->get('/news/index',     'get_index', array(), get_class($this));
        $app->get('/news/index/',    'get_index', array(), get_class($this));
        $app->get('/news/post/:id',  'get_post', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_news_index');
    }
    
    public function get_post(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $post = $api->news_get(array('id'=>$id));
        return $app->render('mod_news_post', array('post'=>$post));
    }
}