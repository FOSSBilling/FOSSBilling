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

namespace Box\Mod\Activity\Controller;

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
                'index' => 700,
                'location' => 'activity',
                'label' => __trans('Activity'),
                'class' => 'graphs',
                'sprite_class' => 'dark-sprite-icon sprite-graph',
                ],
            'subpages' => [
                [
                    'location' => 'activity',
                    'label' => __trans('Event history'),
                    'index' => 100,
                    'uri' => $this->di['url']->adminLink('activity'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/activity', 'get_index', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_activity_index');
    }
}
