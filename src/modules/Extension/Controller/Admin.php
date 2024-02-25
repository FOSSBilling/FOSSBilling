<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Extension\Controller;

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
            'group' => [
                'location' => 'extensions',
                'index' => 1000,
                'label' => __trans('Extensions'),
                'class' => 'iPlugin',
                'sprite_class' => 'dark-sprite-icon sprite-electroPlug',
            ],
            'subpages' => [
                [
                    'location' => 'extensions',
                    'label' => __trans('Overview'),
                    'uri' => $this->di['url']->adminLink('extension'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'extensions',
                    'label' => __trans('Languages'),
                    'index' => 200,
                    'uri' => $this->di['url']->adminLink('extension/languages'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/extension', 'get_index', [], static::class);
        $app->get('/extension/settings/:mod', 'get_settings', ['mod' => '[a-z0-9-]+'], static::class);
        $app->get('/extension/languages', 'get_langs', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_index');
    }

    public function get_langs(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_languages');
    }

    public function get_settings(\Box_App $app, $mod)
    {
        $this->di['is_admin_logged'];
        $extensionService = $this->di['mod_service']('Extension');
        $extensionService->hasManagePermission($mod, $app);

        $file = 'mod_' . $mod . '_settings';

        return $app->render($file);
    }
}
