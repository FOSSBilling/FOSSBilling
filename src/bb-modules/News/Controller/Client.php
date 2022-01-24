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


namespace Box\Mod\News\Controller;

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
        $app->get('/news', 'get_news', array(), get_class($this));
        $app->get('/news/:slug', 'get_news_item', array('slug' => '[a-z0-9-]+'), get_class($this));
    }

    public function get_news(\Box_App $app)
    {
        return $app->render('mod_news_index');
    }

    public function get_news_item(\Box_App $app, $slug)
    {
        $post = $this->di['api_guest']->news_get(array('slug'=>$slug));
        return $app->render('mod_news_post', array('post'=>$post));
    }

}