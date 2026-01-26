<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Api;

use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Downloadable service management.
 */
class Admin extends \Api_Abstract
{
    /**
     * Upload file to product. Uses $_FILES array so make sure your form is
     * enctype="multipart/form-data".
     *
     * @return bool
     */
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

    /**
     * Update downloadable product order with new file.
     * This will change only this order file.
     *
     * Uses $_FILES array so make sure your form is
     * enctype="multipart/form-data"
     *
     * @return bool
     */
    #[RequiredParams(['order_id' => 'Order ID (order_id) was not passed'])]
    public function update($data)
    {
        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $serviceDownloadable = $orderService->getOrderService($order);
        if (!$serviceDownloadable instanceof \Model_ServiceDownloadable) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return $service->updateProductFile($serviceDownloadable, $order);
    }

    /**
     * Save configuration for product.
     *
     **/
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function config_save($data)
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return $service->saveProductConfig($model, $data);
    }

    /**
     * Send file for download for a specific product.
     *
     * @param array{id:int|string} $data data required to send the product file, must contain the product ID as `id`
     *
     * @return bool true if the product file was successfully sent
     *
     * @throws \FOSSBilling\Exception if the product cannot be found or the file cannot be sent
     */
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function send_file($data): bool
    {
        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return (bool) $service->sendProductFile($model);
    }
}
