<?php

/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Order\Controller;

class Admin implements \Box\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'group' => [
                'index' => 300,
                'location' => 'order',
                'label' => 'Orders',
                'uri' => $this->di['url']->adminLink('order'),
                'class' => 'orders',
                'sprite_class' => 'dark-sprite-icon sprite-basket',
                ],
            'subpages' => [
                [
                    'location' => 'order',
                    'index' => 100,
                    'label' => 'Overview',
                    'uri' => $this->di['url']->adminLink('order'),
                    'class' => '',
                ],
                [
                    'location' => 'order',
                    'index' => 200,
                    'label' => 'Advanced search',
                    'uri' => $this->di['url']->adminLink('order', ['show_filter' => 1]),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/order', 'get_index', [], get_class($this));
        $app->get('/order/', 'get_index', [], get_class($this));
        $app->get('/order/index', 'get_index', [], get_class($this));
        $app->get('/order/manage/:id', 'get_order', ['id' => '[0-9]+'], get_class($this));
        $app->post('/order/new', 'get_new', [], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_order_index');
    }

    public function get_new(\Box_App $app)
    {
        $api = $this->di['api_admin'];
        $product = $api->product_get(['id' => $this->di['request']->getPost('product_id')]);
        $client = $api->client_get(['id' => $this->di['request']->getPost('client_id')]);

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
            $set['plugin'] = 'plugin_'.$order['plugin'].'_manage.html.twig';
        }

        return $app->render('mod_order_manage', $set);
    }
}
