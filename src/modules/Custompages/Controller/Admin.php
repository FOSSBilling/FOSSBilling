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

namespace Box\Mod\Custompages\Controller;

class Admin implements \Box\InjectionAwareInterface
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
            'subpages' => [
                [
                    'location' => 'extensions',
                    'label' => __trans('Custom Pages'),
                    'index' => 2000,
                    'uri' => $this->di['url']->adminLink('custompages'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/custompages', 'get_index', [], static::class);
        $app->get('/custompages/:id', 'get_page', ['id' => '[0-9]+'], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_custompages_index');
    }

    public function get_page(\Box_App $app, $id)
    {
        return $app->render('mod_custompages_page', ['page_id' => $id]);
    }
}
