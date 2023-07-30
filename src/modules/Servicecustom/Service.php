<?php
/**
 * Copyright 2022-2023 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicecustom;

class Service implements \FOSSBilling\InjectionAwareInterface
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

    public function validateCustomForm(array &$data, array $product)
    {
        if ($product['form_id']) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm($product['form_id']);
            foreach ($form['fields'] as $field) {
                if ($field['required'] == 1) {
                    $field_name = $field['name'];
                    if (!isset($data[$field_name]) || empty($data[$field_name])) {
                        throw new \Box_Exception('You must fill in all required fields. ' . $field['label'] . ' is missing', null, 9684);
                    }
                }

                if ($field['readonly'] == 1) {
                    $field_name = $field['name'];
                    if ($data[$field_name] != $field['default_value']) {
                        throw new \Box_Exception('Field ' . $field['label'] . ' is read only. You can not change its value', null, 5468);
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    public function action_create(\Model_ClientOrder $order)
    {
        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $model = $this->di['db']->dispense('ServiceCustom');
        $model->client_id = $order->client_id;
        $model->plugin = $product->plugin;
        $model->plugin_config = $product->plugin_config;
        $model->config = $order->config;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    /**
     * @return bool
     */
    public function action_activate(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }

        $this->callOnAdapter($model, 'activate');

        return true;
    }

    /**
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);
        $this->callOnAdapter($model, 'renew');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'suspend');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'unsuspend');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'cancel');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        // move expiration period to future
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'uncancel');

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return bool
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        try {
            $model = $this->_getOrderService($order);
        } catch (\Exception $e) {
            error_log($e);

            return true;
        }

        $this->callOnAdapter($model, 'delete');
        $this->di['db']->trash($model);

        return true;
    }

    public function getConfig(\Model_ServiceCustom $model)
    {
        return $this->di['tools']->decodeJ($model->config);
    }

    public function toApiArray(\Model_ServiceCustom $model)
    {
        $data = $this->getConfig($model);
        $data['id'] = $model->id;
        $data['client_id'] = $model->client_id;
        $data['plugin'] = $model->plugin;
        $data['updated_at'] = $model->updated_at;
        $data['created_at'] = $model->created_at;

        return $data;
    }

    public function customCall(\Model_ServiceCustom $model, $method, $params = [])
    {
        $forbidden_methods = [
            'delete',
            'cancel',
            'uncancel',
            'suspend',
            'unsuspend',
            'renew',
            'activate',
        ];
        if (in_array($method, $forbidden_methods)) {
            throw new \Box_Exception('Custom plugin method :method is forbidden', [':method' => $method], 403);
        }

        return $this->callOnAdapter($model, $method, $params);
    }

    public function updateConfig($orderId, $config)
    {
        if (!is_array($config)) {
            throw new \Box_Exception('Config must be an array');
        }

        $model = $this->getServiceCustomByOrderId($orderId);
        $model->config = json_encode($config);
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Custom service updated #%s', $model->id);
    }

    public function getServiceCustomByOrderId($orderId)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof \Model_ServiceCustom) {
            throw new \Box_Exception('Order is not activated');
        }

        return $s;
    }

    private function callOnAdapter(\Model_ServiceCustom $model, $method, $params = [])
    {
        $plugin = $model->plugin;
        if (empty($plugin)) {
            // error_log('Plugin is not used for this custom service');
            return null;
        }

        // check if plugin exists. If plugin does not exist, do not throw error. Simply add to log
        $file = sprintf('Plugin/%s/%s.php', $plugin, $plugin);
        if (APPLICATION_ENV != 'testing' && !file_exists(PATH_LIBRARY . DIRECTORY_SEPARATOR . $file)) {
            $e = new \Box_Exception('Plugin class file :file was not found', [':file' => $file], 3124);
            if (BB_DEBUG) {
                error_log($e->getMessage());
            }

            return null;
        }

        require_once $file;

        $config = $this->di['tools']->decodeJ($model->plugin_config);
        $adapter = new $plugin($config);

        if (!method_exists($adapter, $method)) {
            throw new \Box_Exception('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function _getOrderService(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \Box_Exception('Order :id has no active service', [':id' => $order->id]);
        }

        return $model;
    }
}
