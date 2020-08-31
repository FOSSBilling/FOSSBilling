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

namespace Box\Mod\Servicedownloadable\Api;
/**
 * Downloadable service management
 */
class Admin extends \Api_Abstract
{
    /**
     * Upload file to product. Uses $_FILES array so make sure your form is
     * enctype="multipart/form-data"
     *
     * @param int $id - product id
     * @param file $file_data - <input type="file" name="file_data" /> field content
     *
     * @return bool
     */
    public function upload($data)
    {
        $required = array(
            'id' => 'Product ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('Product', $data['id'], 'Product not found');

        if(!isset($_FILES['file_data'])) {
            throw new \Box_Exception('File was not uploaded');
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
     * @param int $order_id - order id
     * @param file $file_data - <input type="file" name="file_data" /> field content
     *
     * @return bool
     */
    public function update($data)
    {
        $required = array(
            'order_id' => 'Order ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');

        $orderService = $this->di['mod_service']('order');
        $serviceDownloadable = $orderService->getOrderService($order);
        if(!$serviceDownloadable instanceof \Model_ServiceDownloadable) {
            throw new \Box_Exception('Order is not activated');
        }

        $service = $this->getService();
        return $service->updateProductFile($serviceDownloadable, $order);
    }
}