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


namespace Box\Mod\System\Controller;

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
                'index'     => 600,
                'location'  =>  'system',
                'label'     => 'Configuration',
                'class'     => 'settings',
                'sprite_class' => 'dark-sprite-icon sprite-cog3',
            ),
            'subpages'=> array(
                array(
                    'location'  => 'system',
                    'label' => 'Settings',
                    'index'     => 100,
                    'uri' => $this->di['url']->adminLink('system'),
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/system',           'get_index', array(), get_class($this));
        $app->get('/system/',           'get_index', array(), get_class($this));
        $app->get('/system/index',           'get_index', array(), get_class($this));
        $app->get('/system/activity',           'get_activity', array(), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_system_index');
    }
    
    public function get_activity(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_system_activity');
    }
}