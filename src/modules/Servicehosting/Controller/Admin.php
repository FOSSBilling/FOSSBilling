<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Controller;

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

    public function fetchNavigation()
    {
        return [
            'subpages' => [
                [
                    'location' => 'system',
                    'index' => 140,
                    'label' => __trans('Hosting plans and servers'),
                    'uri' => $this->di['url']->adminLink('servicehosting'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/servicehosting', 'get_index', null, static::class);
        $app->get('/servicehosting/plan/:id', 'get_plan', ['id' => '[0-9]+'], static::class);
        $app->get('/servicehosting/server/:id', 'get_server', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_servicehosting_index');
    }

    public function get_plan(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $hp = $api->servicehosting_hp_get(['id' => $id]);

        return $app->render('mod_servicehosting_hp', ['hp' => $hp]);
    }

    public function get_server(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $server = $api->servicehosting_server_get(['id' => $id]);

        return $app->render('mod_servicehosting_server', ['server' => $server]);
    }
}
