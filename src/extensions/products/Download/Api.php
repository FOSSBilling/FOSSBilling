<?php

declare(strict_types=1);

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Download;

use FOSSBilling\Validation\Api\RequiredParams;
use FOSSBilling\Validation\Api\RequiredRole;

class Api extends \Api_Abstract
{
    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function admin_upload($data)
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $request = $this->di['request'];
        if (!$request->files->has('file_data')) {
            throw new \FOSSBilling\Exception('File was not uploaded.');
        }

        $service = $this->getService();

        return $service->uploadProductFile($model);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['order_id' => 'Order ID (order_id) was not passed'])]
    public function admin_update($data)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $serviceDownload = $orderService->getOrderService($order);
        if (!$serviceDownload instanceof \Model_ExtProductDownload) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return $service->updateProductFile($serviceDownload, $order);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function admin_config_save($data)
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return $service->saveProductConfig($model, $data);
    }

    #[RequiredRole(['admin'])]
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function admin_send_file($data): bool
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return (bool) $service->sendProductFile($model);
    }

    #[RequiredRole(['client'])]
    public function client_send_file($data): bool
    {
        if (!isset($data['order_id'])) {
            throw new \FOSSBilling\Exception('Order ID is required');
        }
        $identity = $this->getIdentity();
        $order = $this->di['db']->findOne('ClientOrder', 'id = :id AND client_id = :client_id', [':id' => $data['order_id'], ':client_id' => $identity->id]);
        if (!$order instanceof \Model_ClientOrder) {
            throw new \FOSSBilling\Exception('Order not found');
        }

        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ExtProductDownload || $order->status !== 'active') {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return (bool) $service->sendFile($s);
    }
}
