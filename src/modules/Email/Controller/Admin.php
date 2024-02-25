<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Email\Controller;

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
                    'location' => 'activity',
                    'index' => 200,
                    'label' => __trans('Email history'),
                    'uri' => $this->di['url']->adminLink('email/history'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/email/history/', 'get_history', [], static::class);
        $app->get('/email/history', 'get_history', [], static::class);
        $app->get('/email/templates', 'get_index', [], static::class);
        $app->get('/email/template/:id', 'get_template', ['id' => '[0-9]+'], static::class);
        $app->get('/email/:id', 'get_email', ['id' => '[0-9]+'], static::class);
    }

    public function get_history(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_email_history');
    }

    public function get_template(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_template_get(['id' => $id]);

        return $app->render('mod_email_template', ['template' => $template]);
    }

    public function get_email(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $template = $api->email_email_get(['id' => $id]);

        return $app->render('mod_email_details', ['email' => $template]);
    }
}
