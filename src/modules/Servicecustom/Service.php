<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicecustom;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicecustom\Entity\ServiceCustom;
use Box\Mod\Servicecustom\Repository\ServiceCustomRepository;
use FOSSBilling\Environment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'manage' => [
                'type' => 'bool',
                'display_name' => __trans('Manage custom services'),
                'description' => __trans('Allows the staff member to update custom service configurations and call custom service methods.'),
            ],
        ];
    }

    public function getServiceCustomRepository(): ServiceCustomRepository
    {
        return $this->di['em']->getRepository(ServiceCustom::class);
    }

    public function validateCustomForm(array &$data, array $product): void
    {
        if ($product['form_id']) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm((int) $product['form_id']);
            foreach ($form['fields'] as $field) {
                if (($field['required'] ?? 0) == 1) {
                    $field_name = $field['name'];
                    if (!isset($data[$field_name]) || empty($data[$field_name])) {
                        throw new \FOSSBilling\InformationException('You must fill in all required fields. ' . $field['label'] . ' is missing', null, 9684);
                    }
                }

                if (($field['readonly'] ?? 0) == 1) {
                    $field_name = $field['name'];
                    if ($data[$field_name] != $field['default_value']) {
                        throw new \FOSSBilling\InformationException('Field ' . $field['label'] . ' is read only. You cannot change its value', null, 5468);
                    }
                }

                if (($field['type'] ?? null) === 'url') {
                    $field_name = $field['name'];
                    if (!empty($data[$field_name])) {
                        if (!is_string($data[$field_name])) {
                            throw new \FOSSBilling\InformationException('Field ' . $field['label'] . ' must be a valid URL with a TLD (e.g., https://example.com)', null, 1248);
                        }

                        $formbuilderService = $this->di['mod_service']('formbuilder');
                        if (!$formbuilderService->validateUrlField($data[$field_name])) {
                            throw new \FOSSBilling\InformationException('Field ' . $field['label'] . ' must be a valid URL with a TLD (e.g., https://example.com)', null, 1248);
                        }
                    }
                }
            }
        }
    }

    /**
     * @return ServiceCustom
     */
    public function action_create(Order $order)
    {
        $product = $this->di['mod_service']('product')->findProductById((int) $order->getProductId());
        if (!$product instanceof Product) {
            throw new \FOSSBilling\InformationException('Product not found');
        }

        $model = new ServiceCustom();
        $model->setClientId($order->getClientId());
        $model->setPlugin($product->getPlugin());
        $model->setPluginConfig($product->getPluginConfig());
        $model->setConfig($order->getConfig());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceCustom) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created', null, 7456);
        }

        $this->callOnAdapter($model, 'activate');

        return true;
    }

    public function action_renew(Order $order): bool
    {
        $model = $this->_getOrderService($order);
        $this->callOnAdapter($model, 'renew');

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_suspend(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'suspend');

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_unsuspend(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'unsuspend');

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_cancel(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'cancel');

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_uncancel(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'uncancel');

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_delete(Order $order): bool
    {
        try {
            $model = $this->_getOrderService($order);
        } catch (\Exception $e) {
            error_log($e->getMessage());

            return true;
        }

        $this->callOnAdapter($model, 'delete');
        $this->di['em']->remove($model);
        $this->di['em']->flush();

        return true;
    }

    public function getConfig(ServiceCustom $model): array
    {
        $config = $model instanceof ServiceCustom ? $model->getConfig() : $model->getConfig();

        return json_decode($config ?? '', true) ?? [];
    }

    public function toApiArray(ServiceCustom $model): array
    {
        $data = $this->getConfig($model);
        $data['id'] = $model instanceof ServiceCustom ? $model->getId() : $model->getId();
        $data['client_id'] = $model instanceof ServiceCustom ? $model->getClientId() : $model->getClientId();
        $data['plugin'] = $model instanceof ServiceCustom ? $model->getPlugin() : $model->getPlugin();
        $data['updated_at'] = $model instanceof ServiceCustom ? $model->getUpdatedAt() : $model->getUpdatedAt();
        $data['created_at'] = $model instanceof ServiceCustom ? $model->getCreatedAt() : $model->getCreatedAt();

        return $data;
    }

    public function customCall(ServiceCustom $model, $method, $params = [])
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
            throw new \FOSSBilling\Exception('Custom plugin method :method is forbidden', [':method' => $method], 403);
        }

        return $this->callOnAdapter($model, $method, $params);
    }

    public function updateConfig($orderId, $config): void
    {
        if (!is_array($config)) {
            throw new \FOSSBilling\Exception('Config must be an array');
        }

        $model = $this->getServiceCustomByOrderId($orderId);
        $this->_setModelProperty($model, 'config', json_encode($config));
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $modelId = $model instanceof ServiceCustom ? $model->getId() : $model->getId();
        $this->di['logger']->info('Custom service updated #%s', $modelId);
    }

    public function getServiceCustomByOrderId($orderId, $clientId = null)
    {
        if ($clientId !== null) {
            $order = $this->di['em']->getRepository(Order::class)->findOneBy(['id' => $orderId, 'clientId' => $clientId]);
            if (!$order) {
                throw new \FOSSBilling\InformationException('Order not found');
            }

            if ($order->getStatus() !== Order::STATUS_ACTIVE) {
                throw new \FOSSBilling\InformationException('Order is not activated');
            }
        } else {
            $order = $this->di['em']->getRepository(Order::class)->find($orderId) ?? throw new \FOSSBilling\InformationException('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);

        if (!$s instanceof ServiceCustom) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $s;
    }

    private function callOnAdapter(ServiceCustom $model, $method, $params = [])
    {
        $plugin = $model instanceof ServiceCustom ? $model->getPlugin() : $model->getPlugin();
        if (empty($plugin)) {
            return null;
        }

        $file = Path::join('Plugin', $plugin, "{$plugin}.php");
        if (!Environment::isTesting() && !$this->filesystem->exists(Path::join(PATH_LIBRARY, $file))) {
            $e = new \FOSSBilling\InformationException('Plugin class file :file was not found', [':file' => $file], 3124);
            if (DEBUG) {
                error_log($e->getMessage());
            }

            return null;
        }

        require_once Path::normalize($file);

        $pluginConfig = $model instanceof ServiceCustom ? $model->getPluginConfig() : $model->getPluginConfig();
        $config = json_decode($pluginConfig ?? '', true) ?? [];

        $adapter = new $plugin($config);

        if (!method_exists($adapter, $method)) {
            throw new \FOSSBilling\Exception('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function _getOrderService(Order $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceCustom) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->getId()]);
        }

        return $model;
    }

    private function _getModelProperty(ServiceCustom $model, string $property): mixed
    {
        return match ($property) {
            'id' => $model->getId(),
            'client_id' => $model->getClientId(),
            'plugin' => $model->getPlugin(),
            'plugin_config' => $model->getPluginConfig(),
            'config' => $model->getConfig(),
            'created_at' => $model->getCreatedAt(),
            'updated_at' => $model->getUpdatedAt(),
            default => null,
        };
    }

    private function _setModelProperty(ServiceCustom $model, string $property, mixed $value): void
    {
        match ($property) {
            'id' => $model->setId($value),
            'client_id' => $model->setClientId($value),
            'plugin' => $model->setPlugin($value),
            'plugin_config' => $model->setPluginConfig($value),
            'config' => $model->setConfig($value),
            'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
            'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
            default => null,
        };
    }
}
