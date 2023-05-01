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

namespace Box\Mod\System\Controller;

class Admin implements \Box\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    /**
     * @param \Pimple\Container $di
     * @return void
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container|null
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 600,
                'location' => 'system',
                'label' => __trans('System'),
                'class' => 'settings',
                'sprite_class' => 'dark-sprite-icon sprite-cog3',
            ],
            'subpages' => [
                [
                    'location' => 'system',
                    'label' => __trans('Settings'),
                    'index' => 100,
                    'uri' => $this->di['url']->adminLink('system'),
                    'class' => '',
                ],
                [
                    'location' => 'system',
                    'label' => __trans('Update'),
                    'index' => 100,
                    'uri' => $this->di['url']->adminLink('system/update'),
                    'class' => '',
                ]
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/system', 'get_index', [], static::class);
        $app->get('/system/', 'get_index', [], static::class);
        $app->get('/system/index', 'get_index', [], static::class);
        $app->get('/system/activity', 'get_activity', [], static::class);
        $app->get('/system/update', 'get_update', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_system_index');
    }

    public function get_activity(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_system_activity');
    }

    public function get_update(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_system_update');
    }
}
