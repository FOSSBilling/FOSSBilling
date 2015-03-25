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

    public function register(\Box_App &$app)
    {
        //@deprecated
        $app->get('/client/me', 'get_profile', array(), get_class($this));

        $app->get('/client/reset-password-confirm/:hash', 'get_reset_password_confirm', array('hash'=>'[a-z0-9]+'), get_class($this));
        $app->get('/client', 'get_client_index', array(), get_class($this));
        $app->get('/client/logout', 'get_client_logout', array(), get_class($this));
        $app->get('/client/:page', 'get_client_page', array('page'=>'[a-z0-9-]+'), get_class($this));
        $app->get('/client/confirm-email/:hash', 'get_client_confirmation', array('page'=>'[a-z0-9-]+'), get_class($this));
    }

    /**
     * @param Box_App $app
     * @deprecated
     */
    public function get_profile(\Box_App $app)
    {
        return $app->redirect('/client/profile');
    }

    /**
     * @param Box_App $app
     * @deprecated
     */
    public function get_balance(\Box_App $app)
    {
        return $app->redirect('/client/balance');
    }

    public function get_client_index(\Box_App $app)
    {
        $this->di['is_client_logged'];
        return $app->render('mod_client_index');
    }

    public function get_client_confirmation(\Box_App $app, $hash)
    {
        $service = $this->di['mod_service']('client');
        $service->approveClientEmailByHash($hash);
        $systemService = $this->di['mod_service']('System');
        $systemService->setPendingMessage(__('Email address was confirmed'));
        $app->redirect('/');
    }

    public function get_client_logout(\Box_App $app)
    {
        $api = $this->di['api_client'];
        $api->profile_logout();
        $app->redirect('/');
    }

    public function get_client_page(\Box_App $app, $page)
    {
        $this->di['is_client_logged'];
        $template = 'mod_client_'.$page;
        return $app->render($template);
    }

    public function get_reset_password_confirm(\Box_App $app, $hash)
    {
        $api = $this->di['api_guest'];
        $data = array(
            'hash' =>  $hash,
        );
        $api->client_confirm_reset($data);
        $app->redirect('/login');
    }

}