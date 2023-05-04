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

namespace Box\Mod\Extension\Controller;

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
        $file = 'mod_' . $mod . '_settings';

        return $app->render($file);
    }
}
