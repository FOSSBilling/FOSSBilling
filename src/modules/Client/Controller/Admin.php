<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Client\Controller;

use Symfony\Component\HttpFoundation\Response;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function fetchNavigation(): array
    {
        return [
            'group' => [
                'index' => 200,
                'location' => 'client',
                'label' => __trans('Clients'),
                'uri' => $this->di['url']->adminLink('client'),
                'class' => 'address-book',
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
                    'location' => 'activity',
                    'index' => 900,
                    'label' => __trans('Client Login History'),
                    'uri' => $this->di['url']->adminLink('client/logins'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void
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

    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_client_index');
    }

    public function get_manage(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $client = $api->client_get(['id' => $id]);

        return $app->render('mod_client_manage', ['client' => $client]);
    }

    public function get_group(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $model = $api->client_group_get(['id' => $id]);

        return $app->render('mod_client_group', ['group' => $model]);
    }

    public function get_history(\Box_App $app): string
    {
        $api = $this->di['api_admin'];

        return $app->render('mod_client_login_history');
    }

    public function get_login(\Box_App $app, $id): Response
    {
        $api = $this->di['api_admin'];
        $api->client_login(['id' => $id]);

        $redirect_to = '/';

        $query = $app->getRequest()->query->get('r');
        if ($query) {
            $r = $query;
            $redirect_to = '/' . trim((string) $r, '/');
        }

        return $app->redirectUrl($this->di['tools']->url($redirect_to), 301);
    }
}
