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
 * This file connects BoxBilling admin area interface and API
 * Class does not extend any other class
 */

namespace Box\Mod\Example\Controller;

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

    /**
     * This method registers menu items in admin area navigation block
     * This navigation is cached in bb-data/cache/{hash}. To see changes please
     * remove the file
     * 
     * @return array
     */
    public function fetchNavigation()
    {
        return array(
            'group'  =>  array(
                'index'     => 1500,                // menu sort order
                'location'  =>  'example',          // menu group identificator for subitems
                'label'     => 'Example module',    // menu group title
                'class'     => 'example',           // used for css styling menu item
            ),
            'subpages'=> array(
                array(
                    'location'  => 'example', // place this module in extensions group
                    'label'     => 'Example module submenu',
                    'index'     => 1500,
                    'uri'       => $this->di['url']->adminLink('example'),
                    'class'     => '',
                ),
            ),
        );
    }

    /**
     * Methods maps admin areas urls to corresponding methods
     * Always use your module prefix to avoid conflicts with other modules
     * in future
     *
     *
     * @example $app->get('/example/test',      'get_test', null, get_class($this)); // calls get_test method on this class
     * @example $app->get('/example/:id',        'get_index', array('id'=>'[0-9]+'), get_class($this));
     * @param \Box_App $app
     */
    public function register(\Box_App &$app)
    {
        $app->get('/example',             'get_index', array(), get_class($this));
        $app->get('/example/test',        'get_test', array(), get_class($this));
        $app->get('/example/user/:id',    'get_user', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/example/api',         'get_api', array(), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        // always call this method to validate if admin is logged in
        $this->di['is_admin_logged'];
        return $app->render('mod_example_index');
    }

    public function get_test(\Box_App $app)
    {
        // always call this method to validate if admin is logged in
        $this->di['is_admin_logged'];

        $params = array();
        $params['youparamname'] = 'yourparamvalue';

        return $app->render('mod_example_index', $params);
    }

    public function get_user(\Box_App $app, $id)
    {
        // always call this method to validate if admin is logged in
        $this->di['is_admin_logged'];

        $params = array();
        $params['userid'] = $id;
        return $app->render('mod_example_index', $params);
    }
    
    public function get_api(\Box_App $app, $id = null)
    {
        // always call this method to validate if admin is logged in
        $api = $this->di['api_admin'];
        $list_from_controller = $api->example_get_something();

        $params = array();
        $params['api_example'] = true;
        $params['list_from_controller'] = $list_from_controller;

        return $app->render('mod_example_index', $params);
    }
}