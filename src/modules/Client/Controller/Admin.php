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
 * with this source code in the file LICENSE.
 */

namespace Box\Mod\Client\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 200,
                'location' => 'client',
                'label' => __trans('Clients'),
                'uri' => $this->di['url']->adminLink('client'),
                'class' => 'contacts',
                'sprite_class' => 'dark-sprite-icon sprite-users',
            ],
            'subpages' => [
                [
                    'location' => 'client',
                    'label' => __trans('Overview'),
                    'uri' => $this->di['url']->adminLink('client'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'client',
                    'label' => __trans('Advanced search'),
                    'uri' => $this->di['url']->adminLink('client', ['show_filter' => 1]),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'activity',
                    'index' => 900,
                    'label' => __trans('Client login history'),
                    'uri' => $this->di['url']->adminLink('client/logins'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/client', 'get_index', [], static::class);
        $app->get('/client/', 'get_index', [], static::class);
        $app->get('/client/index', 'get_index', [], static::class);
        $app->get('/client/login/:id', 'get_login', ['id' => '[0-9]+'], static::class);
        $app->get('/client/manage/:id', 'get_manage', ['id' => '[0-9]+'], static::class);
        $app->get('/client/group/:id', 'get_group', ['id' => '[0-9]+'], static::class);
        $app->get('/client/create', 'get_create', [], static::class);
        $app->get('/client/logins', 'get_history', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_client_index');
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $client = $api->client_get(['id' => $id]);

        return $app->render('mod_client_manage', ['client' => $client]);
    }

    public function get_group(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $model = $api->client_group_get(['id' => $id]);

        return $app->render('mod_client_group', ['group' => $model]);
    }

    public function get_history(\Box_App $app)
    {
        $api = $this->di['api_admin'];

        return $app->render('mod_client_login_history');
    }

    public function get_login(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $api->client_login(['id' => $id]);

        $redirect_to = '/';

        $query = $_GET['r'] ?? null;
        if ($query) {
            $r = $query;
            $redirect_to = '/' . trim($r, '/');
        }

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $this->di['tools']->url($redirect_to));
        exit;
    }
}
