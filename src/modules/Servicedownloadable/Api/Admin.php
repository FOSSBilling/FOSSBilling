<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Api;

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
    public function upload($data)
    {
        $required = [
            'id' => 'Product ID is missing',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        if (!isset($_FILES['file_data'])) {
            throw new \FOSSBilling\Exception('File was not uploaded');
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
    public function update($data)
    {
        $required = [
            'order_id' => 'Order ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

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
    public function config_save($data)
    {
        $required = [
            'id' => 'Product ID is missing',
        ];

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        $service = $this->getService();

        return $service->saveProductConfig($model, $data);
    }
}
