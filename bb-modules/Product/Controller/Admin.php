<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
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
        return array(
            'group'  =>  array(
                'index' => 401,
                'location' => 'products',
                'label' => 'Products',
                'uri' => $this->di['url']->adminLink('products'),
                'class' => 'pic',
                'sprite_class' => 'dark-sprite-icon sprite-blocks',
            ),
            'subpages'  =>  array(
                array(
                    'location'  => 'products',
                    'index'     => 110,
                    'label' => 'Products / Services',
                    'uri'   => $this->di['url']->adminLink('product'),
                    'class' => '',
                ),
                array(
                    'location'  => 'products',
                    'index'     => 120,
                    'label' => 'Product addons',
                    'uri'   => $this->di['url']->adminLink('product/addons'),
                    'class' => '',
                ),
                array(
                    'location'  => 'products',
                    'index'     => 130,
                    'label' => 'Product promotions',
                    'uri'   => $this->di['url']->adminLink('product/promos'),
                    'class' => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/product', 'get_index', array(), get_class($this));
        $app->get('/product/promos', 'get_promos', array(), get_class($this));
        $app->get('/product/promo/:id', 'get_promo', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/product/manage/:id', 'get_manage', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/product/addons', 'get_addons', array(), get_class($this));
        $app->get('/product/addon/:id', 'get_addon_manage', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/product/category/:id', 'get_cat_manage', array('id'=>'[0-9]+'), get_class($this));
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
        $addon= $api->product_addon_get(array('id'=>$id));
        return $app->render('mod_product_addon_manage', array('addon'=>$addon, 'product'=>$addon));
    }

    public function get_cat_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $cat= $api->product_category_get(array('id'=>$id));
        return $app->render('mod_product_category', array('category'=>$cat));
    }

    public function get_manage(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $product= $api->product_get(array('id'=>$id));

        $addons = array();
        foreach ($product['addons'] as $addon) {
            $addons[] = $addon['id'];
        }

        return $app->render('mod_product_manage', array('product'=>$product, 'assigned_addons'=>$addons));
    }
    
    public function get_promo(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $promo= $api->product_promo_get(array('id'=>$id));
        return $app->render('mod_product_promo', array('promo'=>$promo));
    }

    public function get_promos(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_product_promos');
    }
        
}