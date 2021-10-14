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
 * This file connects BoxBilling client area interface and API
 * Class does not extend any other class
 */

namespace Box\Mod\Custompages\Controller;

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

    /**
     * Methods maps client areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future
     *
     * @param \Box_App $app - returned by reference
     */
    public function register(\Box_App &$app)
    {
        $app->get('/custompages/:slug', 'get_page', array('slug' => '[a-z0-9-]+'), get_class($this));
    }

    public function get_page(\Box_App $app, $slug)
    {
        $service = new \Box\Mod\Custompages\Service();
        $service->setDi($this->di);
        $page = $service->getPage($slug, 'slug');
        if(isset($page['id'])) {
            return $app->render('mod_custompages_content', array('page' => $page));
        }
        else {
            die(header('Location: '.$this->di['url']->get('')));
        }
    }
}