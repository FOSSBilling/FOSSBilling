<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicecustom;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicecustom\Entity\ServiceCustom;
use FOSSBilling\System\Environment;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class Service implements \FOSSBilling\Interfaces\InjectionAwareInterface
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

    public function validateCustomForm(array &$data, array $product): void
    {
        if ($product['form_id']) {
            $formbuilderService = $this->di['mod_service']('formbuilder');
            $form = $formbuilderService->getForm((int) $product['form_id']);
            foreach ($form['fields'] as $field) {
                if (($field['required'] ?? 0) == 1) {
                    $field_name = $field['name'];
                    if (!isset($data[$field_name]) || empty($data[$field_name])) {
                        throw new \FOSSBilling\Exception\InformationException('You must fill in all required fields. ' . $field['label'] . ' is missing', null, 9684);
                    }
                }

                if (($field['readonly'] ?? 0) == 1) {
                    $field_name = $field['name'];
                    if ($data[$field_name] != $field['default_value']) {
                        throw new \FOSSBilling\Exception\InformationException('Field ' . $field['label'] . ' is read only. You cannot change its value', null, 5468);
                    }
                }

                if (($field['type'] ?? null) === 'url') {
                    $field_name = $field['name'];
                    if (!empty($data[$field_name])) {
                        if (!is_string($data[$field_name])) {
                            throw new \FOSSBilling\Exception\InformationException('Field ' . $field['label'] . ' must be a valid URL with a TLD (e.g., https://example.com)', null, 1248);
                        }

                        $formbuilderService = $this->di['mod_service']('formbuilder');
                        if (!$formbuilderService->validateUrlField($data[$field_name])) {
                            throw new \FOSSBilling\Exception\InformationException('Field ' . $field['label'] . ' must be a valid URL with a TLD (e.g., https://example.com)', null, 1248);
                        }
                    }
                }
            }
        }
    }

    public function action_create(Order $order): ServiceCustom
    {
        $product = $this->di['mod_service']('product')->findProductById((int) $order->getProductId());
        if (!$product instanceof Product) {
            throw new \FOSSBilling\Exception\InformationException('Product not found');
        }

        $model = new ServiceCustom();
        $model->setClientId((int) $order->getClientId());
        $model->setPlugin($product->getPlugin());
        $model->setPluginConfig($product->getPluginConfig());
        $model->setConfig($order->getConfig());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(Order $order): bool
    {
        $model = $this->_getOrderService($order);
        $this->callOnAdapter($model, 'activate');

        return true;
    }

    public function action_renew(Order $order): bool
    {
        $model = $this->_getOrderService($order);
        $this->callOnAdapter($model, 'renew');

        $this->di['em']->flush();

        return true;
    }

    public function action_suspend(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'suspend');

        $this->di['em']->flush();

        return true;
    }

    public function action_unsuspend(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'unsuspend');

        $this->di['em']->flush();

        return true;
    }

    public function action_cancel(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'cancel');

        $this->di['em']->flush();

        return true;
    }

    public function action_uncancel(Order $order): bool
    {
        $model = $this->_getOrderService($order);

        $this->callOnAdapter($model, 'uncancel');

        $this->di['em']->flush();

        return true;
    }

    public function action_delete(Order $order): bool
    {
        try {
            $model = $this->_getOrderService($order);
        } catch (\Exception $e) {
            $this->di['logger']->error($e->getMessage());

            return true;
        }

        $this->callOnAdapter($model, 'delete');
        $this->di['em']->remove($model);
        $this->di['em']->flush();

        return true;
    }

    public function getConfig(ServiceCustom $model): array
    {
        return json_decode($model->getConfig() ?? '', true) ?? [];
    }

    public function toApiArray(ServiceCustom $model): array
    {
        $data = $this->getConfig($model);
        $data['id'] = $model->getId();
        $data['client_id'] = $model->getClientId();
        $data['plugin'] = $model->getPlugin();
        $data['updated_at'] = $model->getUpdatedAt()?->format('Y-m-d H:i:s');
        $data['created_at'] = $model->getCreatedAt()?->format('Y-m-d H:i:s');

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
            throw new \FOSSBilling\Exception\BaseException('Custom plugin method :method is forbidden', [':method' => $method], 403);
        }

        return $this->callOnAdapter($model, $method, $params);
    }

    public function updateConfig($orderId, $config): void
    {
        if (!is_array($config)) {
            throw new \FOSSBilling\Exception\BaseException('Config must be an array');
        }

        $model = $this->getServiceCustomByOrderId($orderId);
        $model->setConfig(json_encode($config));

        $this->di['em']->flush();

        $this->di['logger']->info('Custom service updated #{model_id}', ['model_id' => $model->getId()]);
    }

    public function getServiceCustomByOrderId($orderId, $clientId = null): ?ServiceCustom
    {
        $orderService = $this->di['mod_service']('order');

        if ($clientId !== null) {
            $order = $this->di['em']->getRepository(Order::class)->findOneBy(['id' => $orderId, 'clientId' => $clientId]);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception\InformationException('Order not found');
            }

            $orderService->assertOrderUsable($order);

            if ($order->getStatus() !== Order::STATUS_ACTIVE) {
                throw new \FOSSBilling\Exception\InformationException('Order is not activated');
            }
        } else {
            $order = $this->di['em']->getRepository(Order::class)->find($orderId);
            if (!$order instanceof Order) {
                throw new \FOSSBilling\Exception\InformationException('Order not found');
            }
        }

        $s = $orderService->getOrderService($order);

        if (!$s instanceof ServiceCustom) {
            throw new \FOSSBilling\Exception\BaseException('Order is not activated');
        }

        return $s;
    }

    private function callOnAdapter(ServiceCustom $model, $method, $params = [])
    {
        $plugin = $model->getPlugin();
        if (empty($plugin)) {
            return null;
        }

        // check if plugin exists. If plugin does not exist, do not throw error. Simply add to log
        $file = Path::join('Plugin', $plugin, "{$plugin}.php");
        if (!Environment::isTesting() && !$this->filesystem->exists(Path::join(PATH_LIBRARY, $file))) {
            $e = new \FOSSBilling\Exception\InformationException('Plugin class file :file was not found', [':file' => $file], 3124);
            // @phpstan-ignore if.alwaysFalse (DEBUG is a runtime constant that may be true during debugging)
            if (DEBUG) {
                $this->di['logger']->debug($e->getMessage());
            }

            return null;
        }

        require_once Path::normalize($file);

        $config = json_decode($model->getPluginConfig() ?? '', true) ?? [];

        $adapter = new $plugin($config);

        if (!method_exists($adapter, $method)) {
            throw new \FOSSBilling\Exception\BaseException('Plugin :plugin does not support action :action', [':plugin' => $plugin, ':action' => $method], 3125);
        }

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        $order_data = $orderService->toApiArray($order);
        $data = $this->toApiArray($model);

        return $adapter->$method($data, $order_data, $params);
    }

    private function _getOrderService(Order $order): ServiceCustom
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceCustom) {
            throw new \FOSSBilling\Exception\BaseException('Order :id has no active service', [':id' => $order->getId()]);
        }

        return $model;
    }
}
