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

namespace Box\Mod\Staff\Controller;
use Box\InjectionAwareInterface;

class Admin implements InjectionAwareInterface
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
                    'location'  => 'activity',
                    'index'     => 400,
                    'label' => 'Staff logins history',
                    'uri'   => $this->di['url']->adminLink('staff/logins'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/staff/login',           'get_login', array(), get_class($this));
        $app->get('/staff/manage/:id',      'get_manage', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/staff/group/:id',      'get_group', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/staff/profile',         'get_profile', array(), get_class($this));
        $app->get('/staff/logins',     'get_history', array(), get_class($this));
    }
    
    public function get_login(\Box_App $app)
    {
        // check if at least one admin exists.
        // if not show admin create form
        $service = $this->di['mod_service']('staff');
        $count = $service->getAdminsCount();
        $create = ($count == 0);
        return $app->render('mod_staff_login', array('create_admin'=>$create));
    }
    
    public function get_profile(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_staff_profile');
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $staff = $api->staff_get(array('id'=>$id));

        $extensionService = $this->di['mod_service']("Extension");
        $mods = $extensionService->getCoreAndActiveModules();
        return $app->render('mod_staff_manage', array('staff'=>$staff, 'mods'=>$mods));
    }

    public function get_group(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $group = $api->staff_group_get(array('id'=>$id));

        $extensionService = $this->di['mod_service']("Extension");
        $mods = $extensionService->getCoreAndActiveModules();
        return $app->render('mod_staff_group', array('group'=>$group, 'mods'=>$mods));
    }
    public function get_history(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_staff_login_history');
    }
}