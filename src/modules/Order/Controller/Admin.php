<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
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

    public function fetchNavigation(): array
    {
        return [
            'group' => [
                'index' => 300,
                'location' => 'order',
                'label' => __trans('Orders'),
                'uri' => $this->di['url']->adminLink('order'),
                'class' => 'package',
            ],
            'subpages' => [
                [
                    'location' => 'order',
                    'index' => 100,
                    'label' => __trans('Overview'),
                    'uri' => $this->di['url']->adminLink('order'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app): void
    {
        $app->get('/order', 'get_index', [], static::class);
        $app->get('/order/', 'get_index', [], static::class);
        $app->get('/order/index', 'get_index', [], static::class);
        $app->get('/order/manage/:id', 'get_order', ['id' => '[0-9]+'], static::class);
        $app->post('/order/new', 'get_new', [], static::class);
    }

    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_order_index');
    }

    public function get_new(\Box_App $app): string
    {
        $api = $this->di['api_admin'];

        $request = $app->getRequest();
        $product = $api->product_get(['id' => $request->request->get('product_id')]);
        $client = $api->client_get(['id' => $request->request->get('client_id')]);

        return $app->render('mod_order_new', ['product' => $product, 'client' => $client]);
    }

    public function get_order(\Box_App $app, $id): string
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
