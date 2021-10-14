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


namespace Box\Mod\Kb\Controller;

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
        $app->get('/kb', 'get_kb', array(), get_class($this));
        $app->get('/kb/:category', 'get_kb_category', array('category' => '[a-z0-9-]+'), get_class($this));
        $app->get('/kb/:category/:slug', 'get_kb_article', array('category' => '[a-z0-9-]+', 'slug' => '[a-z0-9-]+'), get_class($this));
    }

    public function get_kb(\Box_App $app)
    {
        return $app->render('mod_kb_index');
    }

    public function get_kb_category(\Box_App $app, $category)
    {
        $api = $this->di['api_guest'];
        $data = array('slug'=>$category);
        $model = $api->kb_category_get($data);
        return $app->render('mod_kb_category', array('category'=>$model));
    }

    public function get_kb_article(\Box_App $app, $category, $slug)
    {
        $api = $this->di['api_guest'];
        $data = array('slug'=>$slug);
        $article = $api->kb_article_get($data);
        return $app->render('mod_kb_article', array('article'=>$article));
    }
}