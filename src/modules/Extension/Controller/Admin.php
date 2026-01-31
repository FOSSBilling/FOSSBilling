<?php

/**
 * Copyright 2022-2025 FOSSBilling
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

    public function fetchNavigation(): array
    {
        return [
            'group' => [
                'location' => 'extensions',
                'index' => 1000,
                'label' => __trans('Extensions'),
                'class' => 'iPlugin',
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

    public function register(\Box_App &$app): void
    {
        $app->get('/extension', 'get_index', [], static::class);
        $app->get('/extension/settings/:mod', 'get_settings', ['mod' => '[a-z0-9-]+'], static::class);
        $app->get('/extension/languages', 'get_langs', [], static::class);

        // Product extension admin routes
        $app->get('/product/:type/:page/:id', 'get_product_ext_page', ['type' => '[a-z]+', 'page' => '[a-z_]+', 'id' => '[0-9]+'], static::class);
        $app->get('/product/:type/:page', 'get_product_ext_page', ['type' => '[a-z]+', 'page' => '[a-z_]+'], static::class);
    }

    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_index');
    }

    public function get_langs(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_extension_languages');
    }

    public function get_settings(\Box_App $app, $mod): string
    {
        $this->di['is_admin_logged'];
        $extensionService = $this->di['mod_service']('Extension');
        $extensionService->hasManagePermission($mod, $app);

        $file = 'mod_' . $mod . '_settings';

        return $app->render($file);
    }

    /**
     * Handle product extension admin page routes.
     * Routes like /product/domain/tld/123 or /product/domain/registrar/456
     */
    public function get_product_ext_page(\Box_App $app, $type, $page, $id = null): string
    {
        $this->di['is_admin_logged'];

        // Build template name: mod_<type>_<page>
        $file = 'mod_' . $type . '_' . $page;

        // Prepare parameters
        $params = [];
        if ($id !== null) {
            $params['id'] = $id;

            // Load specific data based on page type
            if ($type === 'domain') {
                $api = $this->di['api_admin'];
                if ($page === 'tld') {
                    // Load TLD data by ID - method is tld_get_id in servicedomain module
                    $tld = $api->servicedomain_admin_tld_get_id(['id' => $id]);
                    $params['tld'] = $tld;
                } elseif ($page === 'registrar') {
                    // Load registrar data by ID - method is registrar_get in servicedomain module
                    $registrar = $api->servicedomain_admin_registrar_get(['id' => $id]);
                    $params['registrar'] = $registrar;
                }
            }
        }

        return $app->render($file, $params);
    }
}
