<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Custom;

use FOSSBilling\Environment;
use FOSSBilling\Exception;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use Pimple\Container;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class CustomHandler implements ProductTypeHandlerInterface
{
    private ?Container $di = null;
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    public function validateCustomForm(array &$data, array $product): void
    {
        if ($product['form_id']) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm($product['form_id']);
            foreach ($form['fields'] as $field) {
                if ($field['required'] == 1) {
                    $field_name = $field['name'];
                    if (!isset($data[$field_name]) || empty($data[$field_name])) {
                        throw new \FOSSBilling\InformationException('You must fill in all required fields. ' . $field['label'] . ' is missing', null, 9684);
                    }
                }

                if ($field['readonly'] == 1) {
                    $field_name = $field['name'];
                    if ($data[$field_name] != $field['default_value']) {
                        throw new \FOSSBilling\InformationException('Field ' . $field['label'] . ' is read only. You cannot change its value', null, 5468);
                    }
                }
            }
        }
    }

    public function create(\Model_ClientOrder $order)
    {
        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $model = $this->di['db']->dispense('ExtProductCustom');
        $model->client_id = $order->client_id;
        $model->plugin = $product->plugin;
        $model->plugin_config = $product->plugin_config;
        $model->config = $order->config;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    public function activate(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'activate');

        return true;
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'renew');
        $this->touch($model);

        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'suspend');
        $this->touch($model);

        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'unsuspend');
        $this->touch($model);

        return true;
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'cancel');
        $this->touch($model);

        return true;
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        $model = $this->getOrderService($order);
        $this->callOnAdapter($model, 'uncancel');
        $this->touch($model);

        return true;
    }

    public function delete(\Model_ClientOrder $order): bool
    {
        try {
            $model = $this->getOrderService($order);
        } catch (\Exception $e) {
            error_log($e->getMessage());

            return true;
        }

        $this->callOnAdapter($model, 'delete');
        $this->di['db']->trash($model);

        return true;
    }

    public function getConfig(\Model_ExtProductCustom $model): array
    {
        return json_decode($model->config ?? '', true) ?? [];
    }

    public function toApiArray(\Model_ExtProductCustom $model): array
    {
        $data = $this->getConfig($model);
        $data['id'] = $model->id;
        $data['client_id'] = $model->client_id;
        $data['plugin'] = $model->plugin;
        $data['updated_at'] = $model->updated_at;
        $data['created_at'] = $model->created_at;

        return $data;
    }

    public function customCall(\Model_ExtProductCustom $model, $method, $params = [])
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
        if (in_array($method, $forbidden_methods, true)) {
            throw new Exception('Custom plugin method :method is forbidden', [':method' => $method], 403);
        }

        return $this->callOnAdapter($model, $method, $params);
    }

    public function updateConfig($orderId, $config): void
    {
        if (!is_array($config)) {
            throw new Exception('Config must be an array');
        }

        $model = $this->getServiceCustomByOrderId($orderId);
        $model->config = json_encode($config);
        $this->touch($model);

        $this->di['logger']->info('Custom service updated #%s', $model->id);
    }

    public function getServiceCustomByOrderId($orderId)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof \Model_ExtProductCustom) {
            throw new Exception('Order is not activated');
        }

        return $s;
    }

    private function callOnAdapter(\Model_ExtProductCustom $model, $method, $params = [])
    {
        $plugin = $model->plugin;
        if (empty($plugin)) {
            return null;
        }

        $file = Path::join('Plugin', $plugin, "{$plugin}.php");
        if (!Environment::isTesting() && !$this->filesystem->exists(Path::join(PATH_LIBRARY, $file))) {
            $e = new Exception('Plugin class file :file was not found', [':file' => $file], 3124);
            if (DEBUG) {
                error_log($e->getMessage());
            }

            return null;
        }

        require_once Path::normalize($file);

        $config = json_decode($model->plugin_config ?? '', true) ?? [];

        $adapter = new $plugin($config);

        if (!method_exists($adapter, $method)) {
            throw new Exception('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function getOrderService(\Model_ClientOrder $order): \RedBeanPHP\SimpleModel
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        return $model;
    }

    private function touch(\Model_ExtProductCustom $model): void
    {
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
    }
}
