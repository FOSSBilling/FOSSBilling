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


namespace Box\Mod\Client\Controller;

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
                'index' => 200,
                'location' => 'client',
                'label' => 'Clients',
                'uri' => $this->di['url']->adminLink('client'),
                'class'     => 'contacts',
            ),
            'subpages' => array(
                array(
                    'location' => 'client',
                    'label' => 'Overview',
                    'uri' => $this->di['url']->adminLink('client'),
                    'index' => 100,
                    'class'     => '',
                ),
                array(
                    'location' => 'client',
                    'label' => 'Advanced search',
                    'uri' => $this->di['url']->adminLink('client', array('show_filter' => 1)),
                    'index' => 200,
                    'class'     => '',
                ),
                array(
                    'location' => 'activity',
                    'index' =>  900,
                    'label' => 'Client logins history',
                    'uri' => $this->di['url']->adminLink('client/logins'),
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/client',            'get_index', array(), get_class($this));
        $app->get('/client/',           'get_index', array(), get_class($this));
        $app->get('/client/index',      'get_index', array(), get_class($this));
        $app->get('/client/login/:id',  'get_login', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/client/manage/:id', 'get_manage', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/client/group/:id',  'get_group', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/client/create',     'get_create', array(), get_class($this));
        $app->get('/client/logins',     'get_history', array(), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $app->getApiAdmin();
        return $app->render('mod_client_index');
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $client = $api->client_get(array('id'=>$id));
        return $app->render('mod_client_manage', array('client'=>$client));
    }

    public function get_group(\Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $model = $api->client_group_get(array('id'=>$id));
        return $app->render('mod_client_group', array('group'=>$model));
    }
    
    public function get_history(\Box_App $app)
    {
        $api = $app->getApiAdmin();
        return $app->render('mod_client_login_history');
    }

    public function get_login(\Box_App $app, $id)
    {
        $api = $app->getApiAdmin();
        $api->client_login(array('id'=>$id));

        $redirect_to = '/';
        if ($this->di['request']->getQuery('r')) {
            $r           = $this->di['request']->getQuery('r');
            $redirect_to = '/' . trim($r, '/');
        }

        Header( "HTTP/1.1 301 Moved Permanently" );
        Header( "Location: ".$this->di['tools']->url($redirect_to) );
        exit;
    }
}