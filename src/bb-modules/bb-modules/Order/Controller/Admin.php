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
            'group' => array(
                'index' => 300,
                'location' => 'order',
                'label' => 'Orders',
                'uri' => $this->di['url']->adminLink('order'),
                'class'     => 'orders',
                'sprite_class' => 'dark-sprite-icon sprite-basket',
                ),
            'subpages' => array(
                array(
                    'location'  => 'order',
                    'index' =>  100,
                    'label' => 'Overview',
                    'uri' => $this->di['url']->adminLink('order'),
                    'class'     => '',
                ),
                array(
                    'location'  => 'order',
                    'index' =>  200,
                    'label' => 'Advanced search',
                    'uri' => $this->di['url']->adminLink('order', array('show_filter' => 1)),
                    'class'     => '',
                ),
            ),
        );
    }
    
    public function register(\Box_App &$app)
    {
        $app->get('/order',            'get_index', array(), get_class($this));
        $app->get('/order/',           'get_index', array(), get_class($this));
        $app->get('/order/index',      'get_index', array(), get_class($this));
        $app->get('/order/manage/:id', 'get_order', array('id'=>'[0-9]+'), get_class($this));
        $app->post('/order/new', 'get_new', array(), get_class($this));
    }

    public function get_index(\Box_App $app)
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_order_index');
    }

    public function get_new(\Box_App $app)
    {
        $api = $this->di['api_admin'];
        $product = $api->product_get(array('id' => $this->di['request']->getPost('product_id')));
        $client = $api->client_get(array('id' => $this->di['request']->getPost('client_id')));
        return $app->render('mod_order_new', array('product' => $product, 'client' => $client));
    }
    
    public function get_order(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $data = array(
            'id'    =>  $id,
        );
        $order = $api->order_get($data);
        $set = array('order'=>$order);
        
        if(isset($order['plugin']) && !empty($order['plugin'])) {
            $set['plugin'] = 'plugin_'.$order['plugin'].'_manage.phtml';
        }
        
        return $app->render('mod_order_manage', $set);
    }
}