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


namespace Box\Mod\Massmailer\Controller;

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
            'subpages'=>array(
                array(
                    'location'  => 'extensions',
                    'index'     => 4000,
                    'label' => 'Mass mailer',
                    'uri'   => $this->di['url']->adminLink('massmailer'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/massmailer', 'get_index', array(), get_class($this));
        $app->get('/massmailer/message/:id', 'get_edit', array('id'=>'[0-9]+'), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_massmailer_index');
    }
    
    public function get_edit(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $model = $api->massmailer_get(array('id'=>$id));
        return $app->render('mod_massmailer_message', array('msg'=>$model));
    }
}