<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedownloadable\Api;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadable;
use Box\Mod\Servicedownloadable\Entity\ServiceDownloadableFile;
use FOSSBilling\Validation\Api\RequiredParams;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloadable service management.
 */
class Admin extends \FOSSBilling\Api\AbstractApi
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
        $this->checkPermissions('servicedownloadable', 'manage');

        $model = $this->di['mod_service']('product')->findProductById((int) $data['id']);

        $request = $this->getDi()['request'];
        if (!$request->files->has('file_data')) {
            throw new \FOSSBilling\Exception('File was not uploaded.');
        }

        $service = $this->getService();

        return $service->uploadProductFile($model, $data);
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
        $this->checkPermissions('servicedownloadable', 'manage');

        $order = $this->getDi()['em']->getRepository(Order::class)->find($data['order_id']) ?? throw new \FOSSBilling\InformationException('Order not found');

        $orderService = $this->getDi()['mod_service']('order');
        $serviceDownloadable = $orderService->getOrderService($order);
        if (!$serviceDownloadable instanceof ServiceDownloadable) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $service = $this->getService();

        return $service->uploadOrderFile($serviceDownloadable, $order, $data);
    }

    #[RequiredParams(['id' => 'Product ID was not passed', 'file_id' => 'File ID was not passed'])]
    public function file_update($data): bool
    {
        $this->checkPermissions('servicedownloadable', 'manage');
        $product = $this->di['mod_service']('product')->findProductById((int) $data['id']);

        return $this->getService()->updateProductFile($product, $data);
    }

    #[RequiredParams(['id' => 'Product ID was not passed', 'file_id' => 'File ID was not passed'])]
    public function file_delete($data): bool
    {
        $this->checkPermissions('servicedownloadable', 'manage');
        $product = $this->di['mod_service']('product')->findProductById((int) $data['id']);

        return $this->getService()->removeProductFile($product, $data['file_id']);
    }

    #[RequiredParams(['order_id' => 'Order ID was not passed', 'file_id' => 'File ID was not passed'])]
    public function order_file_delete($data): bool
    {
        $this->checkPermissions('servicedownloadable', 'manage');
        $order = $this->getDi()['em']->getRepository(Order::class)->find((int) $data['order_id']);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }
        $service = $this->getDi()['mod_service']('order')->getOrderService($order);
        if (!$service instanceof ServiceDownloadable) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return $this->getService()->removeOrderFile($service, $order, (int) $data['file_id']);
    }

    /**
     * Save configuration for product.
     *
     **/
    #[RequiredParams(['id' => 'Product ID was not passed'])]
    public function config_save($data)
    {
        $this->checkPermissions('servicedownloadable', 'manage');

        $model = $this->di['mod_service']('product')->findProductById((int) $data['id']);

        $service = $this->getService();

        return $service->saveProductConfig($model, $data);
    }

    /**
     * Send file for download for a specific product.
     *
     * @param array{id:int|string, file_id:string} $data data required to send the product file
     *
     * @return Response the product file download response
     *
     * @throws \FOSSBilling\Exception if the product cannot be found or the file cannot be sent
     */
    #[RequiredParams(['id' => 'Product ID was not passed', 'file_id' => 'File ID was not passed'])]
    public function send_file($data): Response
    {
        $this->checkPermissions('servicedownloadable', 'manage');

        $model = $this->di['mod_service']('product')->findProductById((int) $data['id']);

        $service = $this->getService();

        return $service->sendProductFile($model, $data['file_id']);
    }

    #[RequiredParams(['order_id' => 'Order ID was not passed', 'file_id' => 'File ID was not passed'])]
    public function send_order_file($data): Response
    {
        $this->checkPermissions('servicedownloadable', 'manage');
        $order = $this->getDi()['em']->getRepository(Order::class)->find((int) $data['order_id']);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\InformationException('Order not found');
        }
        $service = $this->getDi()['mod_service']('order')->getOrderService($order);
        if (!$service instanceof ServiceDownloadable) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        $file = $this->getDi()['em']->getRepository(ServiceDownloadableFile::class)->find((int) $data['file_id']);
        if (!$file instanceof ServiceDownloadableFile) {
            throw new \FOSSBilling\InformationException('File not found');
        }

        return $this->getService()->sendFile($file, false);
    }
}
