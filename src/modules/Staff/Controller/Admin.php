<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
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
        return [
            'subpages' => [
                [
                    'location' => 'activity',
                    'index' => 400,
                    'label' => __trans('Staff login history'),
                    'uri' => $this->di['url']->adminLink('staff/logins'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/staff/login', 'get_login', [], static::class);
        $app->get('/staff/manage/:id', 'get_manage', ['id' => '[0-9]+'], static::class);
        $app->get('/staff/group/:id', 'get_group', ['id' => '[0-9]+'], static::class);
        $app->get('/staff/profile', 'get_profile', [], static::class);
        $app->get('/staff/logins', 'get_history', [], static::class);
        // staff password reset
        $app->get('/staff/passwordreset', 'get_passwordreset', [], get_class($this));
        $app->get('/staff/email/:hash', 'get_updatepassword', ['hash' => '[a-zA-Z0-9]+'], get_class($this));
    }

    public function get_login(\Box_App $app)
    {
        // check if at least one admin exists.
        // if not show admin create form
        $service = $this->di['mod_service']('staff');
        $count = $service->getAdminsCount();
        $create = (0 == $count);
        if($this->di['auth']->isAdminLoggedIn()){
            return $app->redirect('');
        }
        return $app->render('mod_staff_login', ['create_admin' => $create]);
    }

    public function get_profile(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_staff_profile');
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $staff = $api->staff_get(['id' => $id]);

        $extensionService = $this->di['mod_service']('Extension');
        $mods = $extensionService->getCoreAndActiveModules();

        return $app->render('mod_staff_manage', ['staff' => $staff, 'mods' => $mods]);
    }

    public function get_group(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $group = $api->staff_group_get(['id' => $id]);

        $extensionService = $this->di['mod_service']('Extension');
        $mods = $extensionService->getCoreAndActiveModules();

        return $app->render('mod_staff_group', ['group' => $group, 'mods' => $mods]);
    }

    public function get_history(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_staff_login_history');
    }

    public function get_passwordreset(\Box_App $app)
    {
        return $app->render('mod_staff_password_reset');
    }

    public function get_updatepassword(\Box_App $app, $hash)
    {
        $this->di['events_manager']->fire(['event' => 'onBeforePasswordResetStaff']);
        $mod = $this->di['mod']('staff');
        $config = $mod->getConfig();
        if ( isset($config['public']['reset_pw']) && $config['public']['reset_pw'] == '0'){
            throw new \Box_Exception('Password reset has been disabled');
        }
        // send confirmation email
        $service = $this->di['mod_service']('staff');
        $reset = $this->di['db']->findOne('AdminPasswordReset', 'hash = ?', [$hash]);
        if (!$reset instanceof \Model_AdminPasswordReset) {
            throw new \Box_Exception('The link have expired or you have already confirmed password reset.');
        }
        if(strtotime($reset -> created_at) - time() + 900 <  0){
            throw new \Box_Exception('The link have expired or you have already confirmed password reset.');
        }
        $admin = $this->di['db']->getExistingModelById('Admin', $reset -> admin_id, 'User not found');
        $data['hash'] = $reset -> hash;
        $data['email'] = $admin -> email;
        return $app->render('mod_staff_password_update', ['data' => $data]);

    }
}
