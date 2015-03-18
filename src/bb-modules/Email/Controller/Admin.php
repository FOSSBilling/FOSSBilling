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


namespace Box\Mod\Email\Controller;

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
            'subpages' => array(
                array(
                    'location'  => 'activity',
                    'index'     => 200,
                    'label' => 'Email history',
                    'uri'   => $this->di['url']->adminLink('email/history'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/email/history/', 'get_history', array(), get_class($this));
        $app->get('/email/history', 'get_history', array(), get_class($this));
        $app->get('/email/templates', 'get_index', array(), get_class($this));
        $app->get('/email/template/:id', 'get_template', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/email/:id', 'get_email', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_history(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_email_history');
    }
    
    public function get_template(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_template_get(array('id'=>$id));
        return $app->render('mod_email_template', array('template'=>$template));
    }
    
    public function get_email(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_email_get(array('id'=>$id));
        return $app->render('mod_email_details', array('email'=>$template));
    }
}