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


namespace Box\Mod\Activity\Controller;

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
            'group'=> array(
                'index'     => 700,
                'location'  =>  'activity',
                'label' => 'Activity',
                'class'     => 'graphs',
                'sprite_class' => 'dark-sprite-icon sprite-graph',
                ),
            'subpages'=> array(
                array(
                    'location'  =>  'activity',
                    'label' => 'Events history',
                    'index'     => 100,
                    'uri' => $this->di['url']->adminLink('activity'),
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/activity',           'get_index', array(), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_activity_index');
    }
}