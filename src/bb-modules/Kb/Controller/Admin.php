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


namespace Box\Mod\Kb\Controller;

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
            'group'  =>  array(
                'index' => 501,
                'location' => 'kb',
                'label' => 'Knowledge Base',
                'uri' => $this->di['url']->adminLink('kb'),
                'class' => 'support',
            ),
            'subpages'  =>  array(
                array(
                    'location'  => 'kb',
                    'index'     => 800,
                    'label' => 'Knowledge Base',
                    'uri'   => $this->di['url']->adminLink('kb'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/kb',           'get_index', array(), get_class($this));
        $app->get('/kb/article/:id',  'get_post', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/kb/category/:id',  'get_cat', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_kb_index');
    }
    
    public function get_post(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $post = $api->kb_article_get(array('id'=>$id));
        return $app->render('mod_kb_article', array('post'=>$post));
    }

    public function get_cat(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat = $api->kb_category_get(array('id'=>$id));
        return $app->render('mod_kb_category', array('category'=>$cat));
    }

}