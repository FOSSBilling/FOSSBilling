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


namespace Box\Mod\Order\Controller;

class Client implements \Box\InjectionAwareInterface
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

    public function register(\Box_App &$app)
    {

        $app->get('/order', 'get_products', array(), get_class($this));
        $app->get('/order/service', 'get_orders', array(), get_class($this));
        $app->get('/order/:id', 'get_configure_product', array('id'=>'[0-9]+'), get_class($this));
        $app->get('/order/:slug', 'get_configure_product_by_slug', array('slug' => '[a-z0-9-]+'), get_class($this));
        $app->get('/order/service/manage/:id', 'get_order', array('id'=>'[0-9]+'), get_class($this));
    }
    
    public function get_products(\Box_App $app)
    {
        return $app->render('mod_order_index');
    }

    public function get_configure_product_by_slug(\Box_App $app, $slug)
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(array('slug'=>$slug));
        $tpl = 'mod_service'.$product['type'].'_order';
        if($api->system_template_exists(array('file'=>$tpl.'.phtml'))) {
            return $app->render($tpl, array('product'=>$product));
        }
        return $app->render('mod_order_product', array('product'=>$product));
    }

    public function get_configure_product(\Box_App $app, $id)
    {
        $api = $this->di['api_guest'];
        $product = $api->product_get(array('id'=>$id));
        $tpl = 'mod_service'.$product['type'].'_order';
        if($api->system_template_exists(array('file'=>$tpl.'.phtml'))) {
            return $app->render($tpl, array('product'=>$product));
        }
        return $app->render('mod_order_product', array('product'=>$product));
    }
    
    public function get_orders(\Box_App $app)
    {
        $this->di['is_client_logged'];
        return $app->render('mod_order_list');
    }

    public function get_order(\Box_App $app, $id)
    {
        $api = $this->di['api_client'];
        $data = array(
            'id'    =>  $id,
        );
        $order = $api->order_get($data);
        return $app->render('mod_order_manage', array('order'=>$order));
    }

}