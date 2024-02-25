<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Massmailer\Controller;

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
                    'location' => 'extensions',
                    'index' => 4000,
                    'label' => __trans('Mass mailer'),
                    'uri' => $this->di['url']->adminLink('massmailer'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/massmailer', 'get_index', [], static::class);
        $app->get('/massmailer/message/:id', 'get_edit', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_massmailer_index');
    }

    public function get_edit(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $model = $api->massmailer_get(['id' => $id]);

        return $app->render('mod_massmailer_message', ['msg' => $model]);
    }
}
