<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 */

namespace FOSSBilling\ProductType\Download\Api;

use FOSSBilling\Validation\Api\RequiredParams;

final class Admin extends \Api_Abstract
{
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function upload($data)
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $request = $this->di['request'];
        if (!$request->files->has('file_data')) {
            throw new \FOSSBilling\Exception('File was not uploaded.');
        }

        $service = $this->getService();

        return $service->uploadProductFile($model);
    }

    #[RequiredParams(['order_id' => 'Order ID (order_id) was not passed'])]
    public function update($data)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $serviceDownload = $orderService->getOrderService($order);
        if (!$serviceDownload instanceof \Model_ServiceDownload) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return $service->updateProductFile($serviceDownload, $order);
    }

    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function config_save($data)
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return $service->saveProductConfig($model, $data);
    }

    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function send_file($data): bool
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return (bool) $service->sendProductFile($model);
    }
}
