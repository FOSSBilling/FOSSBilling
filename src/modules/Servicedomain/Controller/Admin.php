<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain\Controller;

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
                    'index' => 150,
                    'label' => __trans('Domain registration'),
                    'uri' => $this->di['url']->adminLink('servicedomain'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/servicedomain', 'get_index', null, static::class);
        $app->get('/servicedomain/id/:id', 'get_tld_id', ['id' => '[0-9]+'], static::class);
        $app->get('/servicedomain/registrar/:id', 'get_registrar', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_servicedomain_index');
    }

    public function get_tld_id(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $m = $api->servicedomain_tld_get_id(['id' => $id]);

        return $app->render('mod_servicedomain_tld', ['tld' => $m]);
    }

    public function get_registrar(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $registrar = $api->servicedomain_registrar_get(['id' => $id]);

        return $app->render('mod_servicedomain_registrar', ['registrar' => $registrar]);
    }
}
