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
use FOSSBilling\ProductType\Custom\Entity\Custom;
use FOSSBilling\ProductType\Custom\Repository\CustomRepository;
use Pimple\Container;
use Symfony\Component\Filesystem\Path;

class CustomHandler implements ProductTypeHandlerInterface
{
    private ?Container $di = null;
    private ?CustomRepository $repository = null;

    public function setDi(Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?Container
    {
        return $this->di;
    }

    protected function getRepository(): CustomRepository
    {
        if ($this->repository === null) {
            $this->repository = $this->di['em']->getRepository(Custom::class);
        }

        return $this->repository;
    }

    public function loadEntity(int $id): Custom
    {
        $entity = $this->getRepository()->find($id);
        if (!$entity instanceof Custom) {
            throw new Exception('Custom service not found');
        }

        return $entity;
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

    public function create(\Model_ClientOrder $order): Custom
    {
        $product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

        $custom = new Custom($order->client_id);
        $custom->setPlugin($product->plugin);
        $custom->setPluginConfig($product->plugin_config);
        $custom->setConfig($order->config);

        $em = $this->di['em'];
        $em->persist($custom);
        $em->flush();

        return $custom;
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

        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();

        return true;
    }

    public function getConfig(Custom $model): array
    {
        return json_decode($model->getConfig() ?? '', true) ?? [];
    }

    public function toApiArray(Custom $model): array
    {
        $data = $this->getConfig($model);
        $data['id'] = $model->getId();
        $data['client_id'] = $model->getClientId();
        $data['plugin'] = $model->getPlugin();
        $data['updated_at'] = $model->getUpdatedAt()?->format('Y-m-d H:i:s');
        $data['created_at'] = $model->getCreatedAt()?->format('Y-m-d H:i:s');

        return $data;
    }

    public function customCall(Custom $model, $method, $params = [])
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
        $model->setConfig(json_encode($config));
        $this->touch($model);

        $this->di['logger']->info('Custom service updated #%s', $model->getId());
    }

    public function getServiceCustomByOrderId($orderId): Custom
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $orderId, 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof Custom) {
            throw new Exception('Order is not activated');
        }

        return $s;
    }

    private function callOnAdapter(Custom $model, $method, $params = [])
    {
        $plugin = $model->getPlugin();
        if (empty($plugin)) {
            return null;
        }

        $file = Path::join('Plugin', $plugin, "{$plugin}.php");
        if (!Environment::isTesting() && !file_exists(Path::join(PATH_LIBRARY, $file))) {
            $e = new Exception('Plugin class file :file was not found', [':file' => $file], 3124);
            if (DEBUG) {
                error_log($e->getMessage());
            }

            return null;
        }

        require_once Path::normalize($file);

        $pluginConfig = json_decode($model->getPluginConfig() ?? '', true) ?? [];

        $adapter = new $plugin($pluginConfig);

        if (!method_exists($adapter, $method)) {
            throw new Exception('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function getOrderService(\Model_ClientOrder $order): Custom
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof Custom) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        return $model;
    }

    private function touch(Custom $model): void
    {
        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
    }
}
