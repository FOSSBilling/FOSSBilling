<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Order\Controller;

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
                'index' => 300,
                'location' => 'order',
                'label' => __trans('Orders'),
                'uri' => $this->di['url']->adminLink('order'),
                'class' => 'orders',
                'sprite_class' => 'dark-sprite-icon sprite-basket',
            ],
            'subpages' => [
                [
                    'location' => 'order',
                    'index' => 100,
                    'label' => __trans('Overview'),
                    'uri' => $this->di['url']->adminLink('order'),
                    'class' => '',
                ],
                [
                    'location' => 'order',
                    'index' => 200,
                    'label' => __trans('Advanced search'),
                    'uri' => $this->di['url']->adminLink('order', ['show_filter' => 1]),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/order', 'get_index', [], static::class);
        $app->get('/order/', 'get_index', [], static::class);
        $app->get('/order/index', 'get_index', [], static::class);
        $app->get('/order/manage/:id', 'get_order', ['id' => '[0-9]+'], static::class);
        $app->post('/order/new', 'get_new', [], static::class);
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_order_index');
    }

    public function get_new(\Box_App $app)
    {
        $api = $this->di['api_admin'];

        $product = $api->product_get(['id' => $_POST['product_id'] ?? null]);
        $client = $api->client_get(['id' => $_POST['client_id'] ?? null]);

        return $app->render('mod_order_new', ['product' => $product, 'client' => $client]);
    }

    public function get_order(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $data = [
            'id' => $id,
        ];
        $order = $api->order_get($data);
        $set = ['order' => $order];

        if (isset($order['plugin']) && !empty($order['plugin'])) {
            $set['plugin'] = 'plugin_' . $order['plugin'] . '_manage.html.twig';
        }

        return $app->render('mod_order_manage', $set);
    }
}
