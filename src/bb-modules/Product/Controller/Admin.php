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

namespace Box\Mod\Product\Controller;

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
                'index' => 401,
                'location' => 'products',
                'label' => 'Products',
                'uri' => $this->di['url']->adminLink('products'),
                'class' => 'pic',
                'sprite_class' => 'dark-sprite-icon sprite-blocks',
            ],
            'subpages' => [
                [
                    'location' => 'products',
                    'index' => 110,
                    'label' => 'Products / Services',
                    'uri' => $this->di['url']->adminLink('product'),
                    'class' => '',
                ],
                [
                    'location' => 'products',
                    'index' => 120,
                    'label' => 'Product addons',
                    'uri' => $this->di['url']->adminLink('product/addons'),
                    'class' => '',
                ],
                [
                    'location' => 'products',
                    'index' => 130,
                    'label' => 'Product promotions',
                    'uri' => $this->di['url']->adminLink('product/promos'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Box_App &$app)
    {
        $app->get('/product', 'get_index', [], get_class($this));
        $app->get('/product/promos', 'get_promos', [], get_class($this));
        $app->get('/product/promo/:id', 'get_promo', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/manage/:id', 'get_manage', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/addons', 'get_addons', [], get_class($this));
        $app->get('/product/addon/:id', 'get_addon_manage', ['id' => '[0-9]+'], get_class($this));
        $app->get('/product/category/:id', 'get_cat_manage', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_index');
    }

    public function get_addons(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_addons');
    }

    public function get_addon_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $addon = $api->product_addon_get(['id' => $id]);

        return $app->render('mod_product_addon_manage', ['addon' => $addon, 'product' => $addon]);
    }

    public function get_cat_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat = $api->product_category_get(['id' => $id]);

        return $app->render('mod_product_category', ['category' => $cat]);
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $product = $api->product_get(['id' => $id]);

        $addons = [];
        foreach ($product['addons'] as $addon) {
            $addons[] = $addon['id'];
        }

        return $app->render('mod_product_manage', ['product' => $product, 'assigned_addons' => $addons]);
    }

    public function get_promo(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $promo = $api->product_promo_get(['id' => $id]);

        return $app->render('mod_product_promo', ['promo' => $promo]);
    }

    public function get_promos(\Box_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_product_promos');
    }
}
