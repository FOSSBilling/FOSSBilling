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


namespace Box\Mod\Servicehosting\Controller;

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
                    'location'  => 'system',
                    'index'     => 140,
                    'label' => 'Hosting plans and servers',
                    'uri'   => $this->di['url']->adminLink('servicehosting'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/servicehosting',          'get_index', null, get_class($this));
        $app->get('/servicehosting/plan/:id',       'get_plan', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/servicehosting/server/:id',     'get_server', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_servicehosting_index');
    }

    public function get_plan(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $hp = $api->servicehosting_hp_get(array('id'=>$id));
        return $app->render('mod_servicehosting_hp', array('hp'=>$hp));
    }

    public function get_server(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $server = $api->servicehosting_server_get(array('id'=>$id));
        return $app->render('mod_servicehosting_server', array('server'=>$server));
    }
}